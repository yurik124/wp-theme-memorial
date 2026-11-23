"""Проверка микроразметки сайта для Google и Яндекса.

Разбирает скачанные страницы (fetch_pages.py) так же, как валидаторы: JSON-LD, микроданные,
OpenGraph и Twitter, RDFa и микроформаты (последние два — только учёт). Проверяет:

  * словарь schema.org — тип и поле существуют, поле подходит типу, вложенный объект нужного типа
    (то, что валидатор Яндекса пишет как «тип неизвестен» и «некорректный тип данных»);
  * правила Google и Яндекса из их справки (ссылка на страницу справки — у каждого правила, RULES);
  * правила проекта (schema_rules.json): что должно быть на одной странице, чего не должно быть
    дважды на странице и на сайте, совпадение микроданных и JSON-LD.

    python3 tests/diag/schema_check.py out/pages --vocab out/schemaorg.json --out out/schema.json

Ошибки → код выхода 1 (CI красный), предупреждения — только в отчёте.
--require-og — OpenGraph обязателен (на сайте с SEOPress); без него разбирается, только если есть.
"""
import argparse
import json
import os
import re
import sys
from collections import defaultdict
from urllib.parse import urldefrag, urlparse

from lxml import html as lhtml

CHECKED = '2026-11-23'  # когда правила сверялись со справкой
DOCS = {
    'schema': 'https://schema.org/docs/schemas.html',
    'g-general': 'https://developers.google.com/search/docs/appearance/structured-data/sd-policies',
    'g-local': 'https://developers.google.com/search/docs/appearance/structured-data/local-business',
    'g-org': 'https://developers.google.com/search/docs/appearance/structured-data/organization',
    'g-product': 'https://developers.google.com/search/docs/appearance/structured-data/product-snippet',
    'g-breadcrumb': 'https://developers.google.com/search/docs/appearance/structured-data/breadcrumb',
    'g-faq': 'https://developers.google.com/search/docs/appearance/structured-data/faqpage',
    'y-org': 'https://yandex.ru/support/webmaster/ru/search-appearance/organization',
    'y-network': 'https://yandex.ru/support/webmaster/ru/addresses-organizations/network-organization',
    'y-jsonld': 'https://yandex.ru/support/webmaster/ru/json-ld/about',
    'y-validator': 'https://yandex.ru/support/webmaster/ru/yandex-indexing/validator',
    'ogp': 'https://ogp.me/',
    'project': 'https://github.com/yurik124/wp-theme-memorial/blob/block-migration/tests/diag/schema_rules.json',
}
# id правила: (уровень, что проверяется, справка)
RULES = {
    'json-parse': ('error', 'JSON-LD не разбирается', 'g-general'),
    'type-unknown': ('error', 'Тип, которого нет в schema.org', 'schema'),
    'type-attic': ('warning', 'Устаревший тип или поле schema.org (attic / supersededBy)', 'schema'),
    'prop-unknown': ('error', 'Поле, которого нет в schema.org', 'schema'),
    'prop-domain': ('error', 'Поле не относится к этому типу', 'schema'),
    'prop-range': ('error', 'Значение поля — объект неподходящего типа', 'schema'),
    'enum-range': ('warning', 'Значение перечисления не из того перечисления', 'schema'),
    'g-breadcrumb': ('error', 'BreadcrumbList: у ListItem нужны position, name и item (кроме последнего)', 'g-breadcrumb'),
    'g-faq': ('error', 'FAQPage: у каждого Question нужны name и acceptedAnswer.text', 'g-faq'),
    'g-product': ('error', 'Product: нужны name и одно из offers / review / aggregateRating', 'g-product'),
    'g-offer': ('error', 'Offer: нужны price (или priceSpecification.price) и priceCurrency; AggregateOffer — lowPrice и priceCurrency', 'g-product'),
    'g-local': ('error', 'LocalBusiness: нужны name и address', 'g-local'),
    'g-local-rec': ('warning', 'LocalBusiness: рекомендуемые поля (telephone, url, geo, openingHoursSpecification, полный адрес)', 'g-local'),
    'g-logo': ('warning', 'Логотип организации — ссылка на картинку', 'g-org'),
    'y-org': ('error', 'Организация в микроданных: название, адрес (город, улица и дом) и телефон', 'y-network'),
    'y-hours': ('error', 'openingHours — в формате ISO 8601 («Mo-Sa 09:00-17:00»)', 'y-org'),
    'y-linked': ('error', 'Страница с микроразметкой организации должна быть доступна по ссылке с главной', 'y-org'),
    'og-required': ('error', 'OpenGraph: обязательны og:title, og:type, og:image, og:url', 'ogp'),
    'og-prefix': ('error', 'Префикс свойства (article:, fb: …) не объявлен атрибутом prefix', 'y-validator'),
    'og-prefix-og': ('warning', 'Префикс og: не объявлен атрибутом prefix', 'y-validator'),
    'og-dup': ('error', 'Свойство OpenGraph / Twitter / canonical повторяется', 'ogp'),
    'og-image': ('warning', 'Картинка OpenGraph: SVG, с чужого сайта или без og:image:alt', 'ogp'),
    'og-url': ('warning', 'og:url не совпадает с canonical', 'ogp'),
    'p-funeralhome': ('error', 'Тип FuneralHome (его нет в schema.org) — нужен LocalBusiness', 'project'),
    'p-once-page': ('error', 'Разметка, которой на странице должно быть не больше одной', 'project'),
    'p-id-conflict': ('error', 'Один @id / itemid — разные типы или названия', 'project'),
    'p-org-once': ('error', 'Полное описание организации в JSON-LD — только на главной; на других страницах — ссылка (@id и название)', 'project'),
    'p-website': ('error', 'WebSite — только на главной', 'project'),
    'p-service-page': ('error', 'Услуга (Service) — только на своей странице, её url — адрес этой страницы', 'project'),
    'p-service-price': ('error', 'У этой услуги цены в разметке быть не должно', 'project'),
    'p-faq-once': ('error', 'Вопрос FAQ размечен больше чем на одной странице', 'project'),
    'p-product-once': ('error', 'Товар размечен больше чем на одной странице или не на своей', 'project'),
    'p-micro-match': ('error', 'Микроданные организации не совпадают с JSON-LD (адрес, индекс, телефон, часы)', 'project'),
    'p-micro-once': ('error', 'Микроданные одной организации (филиала) на странице дважды', 'project'),
    'p-price-low': ('warning', 'Цена 0 или 1 ₽', 'project'),
    'p-desc-repeat': ('warning', 'В описании повторяется одно и то же предложение', 'project'),
}

SCHEMA_RE = re.compile(r'^https?://schema\.org/')
ORG_TYPES = {'Organization', 'LocalBusiness'}
FULL_ORG_KEYS = {'openingHours', 'openingHoursSpecification', 'department', 'sameAs', 'taxID', 'legalName', 'paymentAccepted'}
DAY = {'Monday': 'Mo', 'Tuesday': 'Tu', 'Wednesday': 'We', 'Thursday': 'Th', 'Friday': 'Fr', 'Saturday': 'Sa', 'Sunday': 'Su'}
DAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']
SINGLE_META = {'og:title', 'og:description', 'og:url', 'og:type', 'og:site_name', 'og:locale', 'twitter:card', 'twitter:title', 'twitter:description'}


def short(t):
    return SCHEMA_RE.sub('', str(t).strip())


def types_of(node):
    t = node.get('@type', [])
    t = t if isinstance(t, list) else [t]
    return [short(x) for x in t if x]


def as_list(v):
    return v if isinstance(v, list) else ([] if v is None else [v])


def norm(s):
    return re.sub(r'[\s\u00a0\u202f]+', ' ', str(s)).strip().lower()


def path_of(url):
    p = urlparse(urldefrag(url)[0]).path or '/'
    return p if p.endswith('/') else p + '/'


# ---------------------------------------------------------------- разбор страницы

def micro_value(el):
    tag = el.tag.lower() if isinstance(el.tag, str) else ''
    if tag == 'meta':
        return el.get('content', '')
    if tag in ('a', 'area', 'link'):
        return el.get('href', '')
    if tag in ('img', 'audio', 'embed', 'iframe', 'source', 'track', 'video'):
        return el.get('src', '')
    if tag == 'object':
        return el.get('data', '')
    if tag in ('data', 'meter'):
        return el.get('value', '')
    if tag == 'time' and el.get('datetime'):
        return el.get('datetime')
    return re.sub(r'\s+', ' ', el.text_content()).strip()


def micro_item(el, root):
    node = {'@type': [short(t) for t in (el.get('itemtype') or '').split()], '_format': 'microdata'}
    if el.get('itemid'):
        node['@id'] = el.get('itemid')

    def add(child):
        prop = child.get('itemprop')
        if prop:
            val = micro_item(child, root) if child.get('itemscope') is not None else micro_value(child)
            for p in prop.split():
                node.setdefault(short(p), []).append(val)
        if child.get('itemscope') is None:
            walk(child)

    def walk(e):
        for c in e:
            if isinstance(c.tag, str):
                add(c)

    walk(el)
    for ref in (el.get('itemref') or '').split():
        for r in root.xpath('//*[@id=$i]', i=ref):
            add(r)
    for k, v in list(node.items()):
        if isinstance(v, list) and not k.startswith('@') and len(v) == 1:
            node[k] = v[0]
    return node


def parse_page(text):
    doc = lhtml.document_fromstring(text or '<html></html>')
    out = {'jsonld': [], 'json_errors': [], 'microdata': [], 'meta': [], 'prefix': {}, 'canonical': [], 'rdfa': 0, 'microformats': 0, 'links': set()}
    for s in doc.xpath('//script[@type="application/ld+json"]'):
        raw = ''.join(s.itertext()).strip()
        try:
            data = json.loads(raw)
        except ValueError as e:
            out['json_errors'].append(f'{e}: {raw[:120]}')
            continue
        out['jsonld'] += data if isinstance(data, list) else [data]
    for el in doc.xpath('//*[@itemscope and not(@itemprop)]'):
        out['microdata'].append(micro_item(el, doc))
    for el in doc.xpath('//html[@prefix] | //head[@prefix]'):
        for pfx, uri in re.findall(r'([\w-]+):\s+(\S+)', el.get('prefix', '')):
            out['prefix'][pfx] = uri
    for m in doc.xpath('//meta[@property or @name]'):
        key = m.get('property') or m.get('name') or ''
        if ':' in key and (key.split(':')[0] in ('og', 'article', 'fb', 'twitter', 'profile', 'book', 'product', 'video', 'music') or m.get('property')):
            out['meta'].append((key, m.get('content', ''), bool(m.get('property'))))
    out['canonical'] = [l.get('href', '') for l in doc.xpath('//link[@rel="canonical"]')]
    out['rdfa'] = len(doc.xpath('//*[@typeof or @vocab] | //*[@property and not(self::meta)]'))
    out['microformats'] = len(doc.xpath('//*[contains(concat(" ", normalize-space(@class), " "), " h-card ") or contains(concat(" ", normalize-space(@class), " "), " vcard ") or contains(concat(" ", normalize-space(@class), " "), " h-entry ")]'))
    out['links'] = {path_of(a.get('href', '')) for a in doc.xpath('//a[@href]')}
    return out


def nodes_of(data, fmt):
    """Все объекты с @type (вложенные тоже): (узел, родитель, поле родителя)."""
    res = []

    def walk(v, parent, prop):
        if isinstance(v, list):
            for x in v:
                walk(x, parent, prop)
        elif isinstance(v, dict):
            if '@graph' in v:
                walk(v['@graph'], None, None)
            if '@type' in v:
                v.setdefault('_format', fmt)
                res.append((v, parent, prop))
                parent = v
            for k, x in v.items():
                if not k.startswith('@') and not k.startswith('_'):
                    walk(x, parent if '@type' in v else parent, k)

    walk(data, None, None)
    return res


# ---------------------------------------------------------------- словарь

class Vocab:
    def __init__(self, path):
        self.ok = bool(path) and os.path.exists(path)
        d = json.load(open(path, encoding='utf-8')) if self.ok else {}
        self.version = d.get('version', '')
        self.classes = d.get('classes', {})
        self.props = d.get('props', {})
        self.enums = d.get('enums', {})
        self.datatypes = set(d.get('datatypes', []))
        self.old = set(d.get('attic', [])) | set(d.get('superseded', {}))
        self._anc = {}

    def anc(self, t):
        if t not in self._anc:
            seen, stack = set(), [t]
            while stack:
                x = stack.pop()
                if x in seen:
                    continue
                seen.add(x)
                stack += self.classes.get(x, [])
            self._anc[t] = seen
        return self._anc[t]

    def known(self, t):
        return t in self.classes or t in self.datatypes


# ---------------------------------------------------------------- проверки

class Report:
    def __init__(self):
        self.items = []

    def add(self, rule, url, msg):
        level, title, doc = RULES[rule]
        self.items.append({'rule': rule, 'level': level, 'url': url, 'title': title, 'message': msg, 'doc': DOCS[doc]})


def check_vocab(rep, url, nodes, vocab):
    if not vocab.ok:
        return
    for node, parent, prop in nodes:
        ts = types_of(node)
        for t in ts:
            if not vocab.known(t):
                rep.add('type-unknown', url, f'{t}' + (f' (в поле {prop})' if prop else ''))
            elif t in vocab.old:
                rep.add('type-attic', url, t)
        anc = set().union(*[vocab.anc(t) for t in ts]) if ts else set()
        for k, v in node.items():
            if k.startswith('@') or k.startswith('_'):
                continue
            p = vocab.props.get(k)
            if p is None:
                rep.add('prop-unknown', url, f'{"/".join(ts)}.{k}')
                continue
            if k in vocab.old:
                rep.add('type-attic', url, f'{"/".join(ts)}.{k}')
            if ts and all(vocab.known(t) for t in ts) and not anc.intersection(p['domain']):
                rep.add('prop-domain', url, f'{"/".join(ts)}.{k} (поле для: {", ".join(p["domain"][:6])})')
            for x in as_list(v):
                if isinstance(x, dict) and '@type' in x:
                    cts = types_of(x)
                    if cts and all(vocab.known(c) for c in cts):
                        canc = set().union(*[vocab.anc(c) for c in cts])
                        if not canc.intersection(p['range']):
                            rep.add('prop-range', url, f'{"/".join(ts)}.{k} = {"/".join(cts)} (допустимо: {", ".join(p["range"][:6])})')
                elif isinstance(x, str) and SCHEMA_RE.match(x) and short(x) in vocab.enums:
                    et = set().union(*[vocab.anc(e) for e in vocab.enums[short(x)]])
                    if not et.intersection(p['range']) and not set(p['range']) & {'Text', 'URL'}:
                        rep.add('enum-range', url, f'{k} = {short(x)}')


def first(node, key):
    v = as_list(node.get(key))
    return v[0] if v else None


def num(v):
    try:
        return float(str(v).replace(',', '.').replace(' ', ''))
    except ValueError:
        return None


def check_google(rep, url, nodes):
    for node, parent, prop in nodes:
        ts = set(types_of(node))
        if 'BreadcrumbList' in ts:
            items = as_list(node.get('itemListElement'))
            for i, li in enumerate(items):
                if not isinstance(li, dict) or li.get('position') in (None, '') or not li.get('name') or (i < len(items) - 1 and not li.get('item')):
                    rep.add('g-breadcrumb', url, f'элемент {i + 1}')
        if 'FAQPage' in ts:
            for q in as_list(node.get('mainEntity')):
                a = first(q, 'acceptedAnswer') if isinstance(q, dict) else None
                if not isinstance(q, dict) or not q.get('name') or not isinstance(a, dict) or not a.get('text'):
                    rep.add('g-faq', url, str(q.get('name') if isinstance(q, dict) else q)[:80])
        if 'Product' in ts:
            if not node.get('name') or not (node.get('offers') or node.get('review') or node.get('aggregateRating')):
                rep.add('g-product', url, str(node.get('name') or node.get('url') or '?')[:80])
            desc = str(node.get('description') or '')
            sents = [s.strip() for s in re.split(r'(?<=[.!?…])\s+', desc) if len(s.strip()) >= 30]
            if len(sents) != len(set(sents)):
                rep.add('p-desc-repeat', url, str(node.get('name'))[:60])
        if ts & {'Offer', 'AggregateOffer'} and (prop == 'offers' or parent is None):
            agg = 'AggregateOffer' in ts
            spec = first(node, 'priceSpecification')
            price = node.get('lowPrice') if agg else (node.get('price') if node.get('price') not in (None, '') else (spec.get('price') or spec.get('minPrice') if isinstance(spec, dict) else None))
            cur = node.get('priceCurrency') or (spec.get('priceCurrency') if isinstance(spec, dict) else None)
            if price in (None, '') or not cur:
                rep.add('g-offer', url, f'{"/".join(ts)} у {str((parent or {}).get("name", ""))[:60]}')
            elif num(price) is not None and num(price) <= 1:
                rep.add('p-price-low', url, f'{num(price):g} ₽ — {str((parent or {}).get("name", ""))[:60]}')
        if is_full_org(node):
            if 'LocalBusiness' in ts or node.get('address'):
                if not node.get('name') or not node.get('address'):
                    rep.add('g-local', url, str(node.get('name') or node.get('@id')))
                miss = [k for k in ('telephone', 'url', 'geo', 'openingHoursSpecification') if not node.get(k) and not (k == 'openingHoursSpecification' and node.get('openingHours'))]
                a = first(node, 'address')
                if isinstance(a, dict):
                    miss += ['address.' + k for k in ('streetAddress', 'addressLocality', 'postalCode', 'addressCountry') if not a.get(k)]
                if miss:
                    rep.add('g-local-rec', url, f'{node.get("name")}: нет {", ".join(miss)}')
                for d in as_list(node.get('department')):
                    if isinstance(d, dict):
                        if not d.get('name') or not d.get('address'):
                            rep.add('g-local', url, f'department {d.get("name") or d.get("@id")}')
            logo = node.get('logo')
            if logo is not None and not (isinstance(logo, str) and logo.startswith('http')) and not (isinstance(logo, dict) and str(logo.get('url', '')).startswith('http')):
                rep.add('g-logo', url, str(logo)[:80])


HOURS_RE = re.compile(r'^(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))?(,(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))?)* \d\d:\d\d-\d\d:\d\d$')


def check_yandex_micro(rep, url, micro):
    for item in micro:
        if not is_org(item):
            continue
        a = first(item, 'address')
        ok = item.get('name') and item.get('telephone') and isinstance(a, dict) and a.get('streetAddress') and a.get('addressLocality')
        if not ok:
            rep.add('y-org', url, str(item.get('name') or item.get('@id')))
        for h in as_list(item.get('openingHours')):
            if not HOURS_RE.match(str(h).strip()):
                rep.add('y-hours', url, f'{item.get("name")}: «{h}»')


def check_og(rep, url, page, require, base_host):
    meta = page['meta']
    keys = [k for k, _, _ in meta]
    if not any(k.startswith('og:') for k in keys) and not require:
        return
    for k in ('og:title', 'og:type', 'og:image', 'og:url'):
        if k not in keys:
            rep.add('og-required', url, k)
    told = set()
    for k, _, is_prop in meta:
        pfx = k.split(':')[0]
        if is_prop and pfx not in page['prefix'] and pfx != 'twitter' and pfx not in told:
            told.add(pfx)
            rep.add('og-prefix-og' if pfx == 'og' else 'og-prefix', url, pfx + ':')
    seen = defaultdict(list)
    for k, v, _ in meta:
        seen[k].append(v)
    for k, vs in seen.items():
        if (k in SINGLE_META and len(vs) > 1) or len(vs) != len(set(vs)):
            rep.add('og-dup', url, f'{k} ×{len(vs)}')
    if len(page['canonical']) > 1:
        rep.add('og-dup', url, f'canonical ×{len(page["canonical"])}')
    for img in seen.get('og:image', []):
        host = urlparse(img).hostname or ''
        if img.lower().split('?')[0].endswith('.svg'):
            rep.add('og-image', url, f'SVG: {img}')
        elif host and base_host and host != base_host:
            rep.add('og-image', url, f'с другого сайта: {img}')
    if 'og:image' in seen and not any(v.strip() for v in seen.get('og:image:alt', [])):
        rep.add('og-image', url, 'нет og:image:alt')
    if seen.get('og:url') and page['canonical'] and path_of(seen['og:url'][0]) != path_of(page['canonical'][0]):
        rep.add('og-url', url, f'{seen["og:url"][0]} ≠ {page["canonical"][0]}')


VOCAB = None  # словарь — для «это организация?» по родителям типа


def is_org(node):
    ts = set(types_of(node))
    if ts & ORG_TYPES or 'FuneralHome' in ts:
        return True
    return bool(VOCAB and VOCAB.ok and any('Organization' in VOCAB.anc(t) for t in ts))


def is_full_org(node):
    return is_org(node) and bool(FULL_ORG_KEYS & set(node))


def hours_set(node):
    out = set()
    for h in as_list(node.get('openingHours')):
        out.add(re.sub(r'\s+', ' ', str(h)).strip())
    for s in as_list(node.get('openingHoursSpecification')):
        if not isinstance(s, dict):
            continue
        days = [DAY.get(short(d), short(d)[:2]) for d in as_list(s.get('dayOfWeek'))]
        idx = sorted(DAYS.index(d) for d in days if d in DAYS)
        if not idx:
            continue
        rng = DAYS[idx[0]] + ('-' + DAYS[idx[-1]] if len(idx) > 1 else '') if idx == list(range(idx[0], idx[-1] + 1)) else ','.join(DAYS[i] for i in idx)
        out.add(f'{rng} {str(s.get("opens", ""))[:5]}-{str(s.get("closes", ""))[:5]}')
    return out


def addr_key(node):
    a = first(node, 'address')
    if not isinstance(a, dict):
        return (norm(a or ''), '', '')
    return (norm(a.get('streetAddress', '')), norm(a.get('postalCode', '')), norm(a.get('addressLocality', '')))


def phones(node):
    return {re.sub(r'[^\d+]', '', str(p)) for p in as_list(node.get('telephone'))}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('pages')
    ap.add_argument('--vocab', default='')
    ap.add_argument('--rules', default=os.path.join(os.path.dirname(__file__), 'schema_rules.json'))
    ap.add_argument('--out', default='')
    ap.add_argument('--require-og', action='store_true')
    ap.add_argument('--require-vocab', action='store_true')
    ap.add_argument('--no-fail', action='store_true')
    a = ap.parse_args()

    global VOCAB
    vocab = VOCAB = Vocab(a.vocab)
    if a.require_vocab and not vocab.ok:
        sys.exit(f'нет словаря schema.org: {a.vocab}')
    rules = json.load(open(a.rules, encoding='utf-8')) if os.path.exists(a.rules) else {}
    idx = json.load(open(os.path.join(a.pages, 'index.json'), encoding='utf-8'))
    base = idx['base']
    base_host = urlparse(base).hostname
    rep = Report()
    inventory = {}
    site = defaultdict(lambda: defaultdict(set))  # что → ключ → страницы
    home_full, micro_all, pages_links = [], [], {}

    for p in idx['pages']:
        if p['code'] != 200:
            continue
        url = p['final'] or p['url']
        upath = path_of(url)
        page = parse_page(open(os.path.join(a.pages, p['file']), encoding='utf-8').read())
        pages_links[upath] = page['links']
        for e in page['json_errors']:
            rep.add('json-parse', url, e)
        jn = nodes_of(page['jsonld'], 'jsonld')
        mn = nodes_of(page['microdata'], 'microdata')
        inventory[upath] = {
            'jsonld': sorted({'/'.join(types_of(n)) for n, par, _ in jn if par is None}),
            'microdata': sorted({'/'.join(types_of(n)) for n, par, _ in mn if par is None}),
            'opengraph': len([1 for k, _, _ in page['meta'] if k.startswith('og:')]),
            'rdfa': page['rdfa'],
            'microformats': page['microformats'],
        }
        allnodes = jn + mn
        check_vocab(rep, url, allnodes, vocab)
        check_google(rep, url, jn)
        check_yandex_micro(rep, url, page['microdata'])
        check_og(rep, url, page, a.require_og, base_host)

        # проект: на странице
        for n, _, _ in allnodes:
            if 'FuneralHome' in types_of(n):
                rep.add('p-funeralhome', url, f'{n.get("_format")}: {n.get("name", "")}')
        top = [n for n, par, _ in jn if par is None]
        for t in ('BreadcrumbList', 'FAQPage', 'WebSite'):
            c = sum(1 for n in top if t in types_of(n))
            if c > 1:
                rep.add('p-once-page', url, f'{t} ×{c}')
        lists = [n for n in top if 'ItemList' in types_of(n) and any(isinstance(li, dict) and isinstance(li.get('item'), dict) and 'Product' in types_of(li['item']) for li in as_list(n.get('itemListElement')))]
        if len(lists) > 1:
            rep.add('p-once-page', url, f'список товаров ×{len(lists)}')
        full = [n for n, _, _ in jn if is_full_org(n)]
        if len(full) > 1:
            rep.add('p-once-page', url, f'полное описание организации в JSON-LD ×{len(full)}')
        if full:
            site['org'][''].add(upath)
            if upath == '/':
                home_full += full
        byid = defaultdict(list)
        for n, _, _ in allnodes:
            if n.get('@id'):
                byid[n['@id']].append(n)
        for i, ns in byid.items():
            tset = {tuple(sorted(types_of(n))) for n in ns}
            names = {norm(n['name']) for n in ns if isinstance(n.get('name'), str)}
            if len(tset) > 1 or len(names) > 1:
                rep.add('p-id-conflict', url, f'{i}: типы {sorted(tset)}, названия {sorted(names)}')
        mids = defaultdict(int)
        for item in page['microdata']:
            if is_org(item):
                mids[item.get('@id') or item.get('name') or '?'] += 1
                micro_all.append((upath, url, item))
        for i, c in mids.items():
            if c > 1:
                rep.add('p-micro-once', url, f'{i} ×{c}')

        # на сайте: что где размечено
        for n in top:
            if 'WebSite' in types_of(n):
                site['website'][''].add(upath)
        for n, _, _ in jn:
            ts = types_of(n)
            if 'Service' in ts:
                key = path_of(n.get('url') or url)
                site['service'][key].add(upath)
                if key != upath:
                    rep.add('p-service-page', url, f'{n.get("name")}: url {n.get("url")}')
                if any(upath == path_of(x) for x in rules.get('service_no_price', [])) and n.get('offers'):
                    rep.add('p-service-price', url, str(n.get('name')))
            if 'Question' in ts and n.get('name'):
                site['faq'][norm(n['name'])].add(upath)
            if 'Product' in ts:
                key = n.get('url') or n.get('@id') or norm(n.get('name', ''))
                site['product'][key].add(upath)
                pu = n.get('url')
                if pu and path_of(pu) != upath and urldefrag(pu)[1]:
                    rep.add('p-product-once', url, f'{n.get("name")}: товар раздела {pu}')

    # ---- на сайте
    org_pages = site['org']['']
    if org_pages and org_pages != {'/'}:
        rep.add('p-org-once', base, 'страницы: ' + ', '.join(sorted(org_pages)))
    if site['website'][''] - {'/'}:
        rep.add('p-website', base, ', '.join(sorted(site['website'][''])))
    for key, ps in site['service'].items():
        if len(ps) > 1:
            rep.add('p-service-page', base, f'{key} на страницах: {", ".join(sorted(ps))}')
    for key, ps in site['faq'].items():
        if len(ps) > 1:
            rep.add('p-faq-once', base, f'«{key[:70]}» на страницах: {", ".join(sorted(ps))}')
    for key, ps in site['product'].items():
        if len(ps) > 1:
            rep.add('p-product-once', base, f'{key} на страницах: {", ".join(sorted(ps))}')

    # микроданные ↔ JSON-LD главной
    branches = {}
    for n in home_full:
        branches[n.get('@id') or ''] = n
        for d in as_list(n.get('department')):
            if isinstance(d, dict):
                branches[d.get('@id') or ''] = {**d, 'telephone': d.get('telephone') or n.get('telephone')}
    checked = set()
    for upath, url, item in micro_all:
        key = (item.get('@id'), json.dumps(item, sort_keys=True, ensure_ascii=False))
        if key in checked:
            continue
        checked.add(key)
        if home_full:
            b = branches.get(item.get('@id') or '')
            if not b:
                rep.add('p-micro-match', url, f'itemid {item.get("@id")}: такого филиала нет в JSON-LD главной')
            else:
                diffs = []
                if addr_key(item) != addr_key(b):
                    diffs.append(f'адрес {addr_key(item)} ≠ {addr_key(b)}')
                if not phones(item) <= phones(b) | phones(home_full[0]):
                    diffs.append(f'телефон {sorted(phones(item))} ≠ {sorted(phones(b))}')
                if hours_set(item) != hours_set(b):
                    diffs.append(f'часы {sorted(hours_set(item))} ≠ {sorted(hours_set(b))}')
                if diffs:
                    rep.add('p-micro-match', url, f'{item.get("@id")}: ' + '; '.join(diffs))
    home_links = pages_links.get('/', set())
    micro_pages = defaultdict(set)
    for upath, _, item in micro_all:
        micro_pages[item.get('@id') or item.get('name')].add(upath)
    for i, ps in micro_pages.items():
        if not ps & (home_links | {'/'}):
            rep.add('y-linked', base, f'{i}: {", ".join(sorted(ps)[:5])}')

    errors = [x for x in rep.items if x['level'] == 'error']
    warnings = [x for x in rep.items if x['level'] == 'warning']
    result = {
        'base': base,
        'checked_rules': CHECKED,
        'schemaorg': vocab.version or 'нет словаря — проверка типов и полей пропущена',
        'pages': len(inventory),
        'errors': len(errors),
        'warnings': len(warnings),
        'rules': {k: {'level': v[0], 'title': v[1], 'doc': DOCS[v[2]]} for k, v in RULES.items()},
        'findings': rep.items,
        'inventory': inventory,
    }
    if a.out:
        os.makedirs(os.path.dirname(a.out) or '.', exist_ok=True)
        json.dump(result, open(a.out, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    # сводка: по правилу — сколько и первые примеры
    lines = [f'## Микроразметка: {base}', '', f'Страниц: {len(inventory)}, ошибок: {len(errors)}, предупреждений: {len(warnings)}, schema.org: {result["schemaorg"]}', '']
    by = defaultdict(list)
    for x in rep.items:
        by[x['rule']].append(x)
    for rule, xs in sorted(by.items(), key=lambda kv: (RULES[kv[0]][0] != 'error', kv[0])):
        lvl, title, doc = RULES[rule]
        lines.append(f'### {"❌" if lvl == "error" else "⚠️"} {title} — {len(xs)} ([справка]({DOCS[doc]}))')
        for x in xs[:8]:
            lines.append(f'- {path_of(x["url"])} — {x["message"]}')
        if len(xs) > 8:
            lines.append(f'- … ещё {len(xs) - 8}')
        lines.append('')
    summary = '\n'.join(lines)
    print(summary)
    if os.environ.get('GITHUB_STEP_SUMMARY'):
        with open(os.environ['GITHUB_STEP_SUMMARY'], 'a', encoding='utf-8') as f:
            f.write(summary + '\n')
    for x in errors[:30]:
        print(f'::error title=Микроразметка ({x["rule"]})::{path_of(x["url"])} — {x["message"]}')
    if errors and not a.no_fail:
        sys.exit(1)


if __name__ == '__main__':
    main()
