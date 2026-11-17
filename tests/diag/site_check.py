"""Проверка ответов сайта для diag.yml: адреса из urls.txt и все адреса карт сайта SEOPress.

Только чтение (GET без перехода по редиректам). Итог — out/http.json.
"""
import json
import re
import sys
from concurrent.futures import ThreadPoolExecutor

import requests

site = open(sys.argv[1], encoding='utf-8').read().strip().rstrip('/')
here = sys.argv[2]
S = requests.Session()
S.headers['User-Agent'] = 'Mozilla/5.0 (compatible; MemorialDiag/1.0)'


def get(url):
    try:
        r = S.get(url, allow_redirects=False, timeout=40)
        head = r.text[:400] if 'text' in r.headers.get('Content-Type', '') or 'xml' in r.headers.get('Content-Type', '') else ''
        robots = r.headers.get('X-Robots-Tag', '')
        m = re.search(r'<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)', r.text[:30000], re.I)
        c = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)', r.text[:30000], re.I)
        t = re.search(r'<title>([^<]*)</title>', r.text[:30000], re.I)
        dsc = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)', r.text[:30000], re.I)
        return {
            'code': r.status_code,
            'to': r.headers.get('Location', ''),
            'x_robots': robots,
            'meta_robots': m.group(1) if m else '',
            'canonical': c.group(1) if c else '',
            'title': t.group(1).strip() if t else '',
            'desc': dsc.group(1) if dsc else '',
            'ctype': r.headers.get('Content-Type', ''),
            'body': r.text if url.endswith(('robots.txt', 'llms.txt')) else head,
        }
    except Exception as e:  # noqa: BLE001
        return {'error': str(e)}


paths = [l.strip() for l in open(here + '/urls.txt', encoding='utf-8') if l.strip() and not l.startswith('#')]
out = {'site': site, 'paths': {}}
with ThreadPoolExecutor(6) as ex:
    for p, res in zip(paths, ex.map(lambda p: get(site + p), paths)):
        out['paths'][p] = res

# Карты сайта SEOPress: индекс → дочерние карты → адреса; у каждого адреса — код ответа.
maps = {}
idx = S.get(site + '/sitemaps.xml', timeout=40)
children = re.findall(r'<loc>([^<]+)</loc>', idx.text) if idx.status_code == 200 else []
urls = []
for ch in children:
    if not ch.endswith('.xml'):
        continue
    r = S.get(ch, timeout=40)
    locs = re.findall(r'<loc>([^<]+)</loc>', r.text)
    maps[ch] = {'code': r.status_code, 'count': len(locs)}
    urls += locs
urls = list(dict.fromkeys(urls))[:400]
bad = []
pages = {}
with ThreadPoolExecutor(6) as ex:
    for u, res in zip(urls, ex.map(get, urls)):
        pages[u] = {'title': res.get('title'), 'desc': res.get('desc')}
        if res.get('code') != 200 or 'noindex' in (res.get('meta_robots', '') + res.get('x_robots', '')):
            bad.append({'url': u, **{k: res.get(k) for k in ('code', 'to', 'meta_robots', 'x_robots', 'error')}})
out['sitemaps'] = {'index_code': idx.status_code, 'maps': maps, 'urls': len(urls), 'bad': bad, 'pages': pages}

open('out/http.json', 'w', encoding='utf-8').write(json.dumps(out, ensure_ascii=False, indent=1))
print(json.dumps(out, ensure_ascii=False, indent=1)[:40000])
