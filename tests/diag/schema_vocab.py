"""Словарь schema.org для проверки разметки (schema_check.py).

Скачивает выпуск schema.org (schemaorg-all-https.jsonld из репозитория schemaorg/schemaorg) и
сохраняет сжатый словарь: типы и их родители, свойства с областью (domainIncludes) и значениями
(rangeIncludes), члены перечислений, пометки «pending» (не утверждено) и «attic» (устарело).

    python3 tests/diag/schema_vocab.py latest            — номер последнего выпуска;
    python3 tests/diag/schema_vocab.py build 30.0 out.json — словарь выпуска 30.0 в out.json.

Номер закреплённого выпуска — tests/diag/schemaorg-version.txt (обновляет schema-vocab.yml).
"""
import json
import re
import sys
import urllib.request

RAW = 'https://raw.githubusercontent.com/schemaorg/schemaorg/main/data/releases/{v}/schemaorg-all-https.jsonld'
API = 'https://api.github.com/repos/schemaorg/schemaorg/contents/data/releases'


def get(url):
    req = urllib.request.Request(url, headers={'User-Agent': 'memorial-schema-check'})
    with urllib.request.urlopen(req, timeout=120) as r:
        return r.read().decode('utf-8')


def latest():
    names = [e['name'] for e in json.loads(get(API)) if e.get('type') == 'dir']
    nums = [n for n in names if re.fullmatch(r'\d+(\.\d+)*', n)]
    return max(nums, key=lambda n: tuple(int(x) for x in n.split('.')))


def short(v):
    v = v['@id'] if isinstance(v, dict) else v
    return re.sub(r'^(schema:|https?://schema\.org/)', '', v)


def ids(v):
    if v is None:
        return []
    return [short(x) for x in (v if isinstance(v, list) else [v])]


def build(version):
    graph = json.loads(get(RAW.format(v=version)))['@graph']
    classes, props, enums, pending, attic, superseded = {}, {}, {}, [], [], {}
    datatypes = {'Text', 'URL', 'Number', 'Integer', 'Float', 'Boolean', 'Date', 'DateTime', 'Time', 'CssSelectorType', 'XPathType', 'PronounceableText'}
    for n in graph:
        if not str(n.get('@id', '')).startswith('schema:'):
            continue
        name = short(n['@id'])
        types = ids(n.get('@type'))
        part = ' '.join(ids(n.get('schema:isPartOf')))
        if 'pending.schema.org' in part:
            pending.append(name)
        if 'attic.schema.org' in part:
            attic.append(name)
        for s in ids(n.get('schema:supersededBy')):
            superseded[name] = s
        if 'rdfs:Class' in types:
            classes[name] = ids(n.get('rdfs:subClassOf'))
        elif 'rdf:Property' in types:
            props[name] = {'domain': ids(n.get('schema:domainIncludes')), 'range': ids(n.get('schema:rangeIncludes'))}
        if 'DataType' in types:
            datatypes.add(name)
        # член перечисления: его тип — само перечисление (schema:InStock → ItemAvailability)
        for t in types:
            if t not in ('rdfs:Class', 'rdf:Property') and not t.startswith('rdf'):
                enums.setdefault(name, []).append(t)
    enums = {k: v for k, v in enums.items() if k not in classes and k not in props}
    return {
        'version': version,
        'classes': classes,
        'datatypes': sorted(datatypes),
        'props': props,
        'enums': enums,
        'pending': sorted(set(pending)),
        'attic': sorted(set(attic)),
        'superseded': superseded,
    }


if __name__ == '__main__':
    cmd = sys.argv[1] if len(sys.argv) > 1 else 'latest'
    if cmd == 'latest':
        print(latest())
    elif cmd == 'build':
        v = sys.argv[2]
        if v in ('', 'latest'):
            v = latest()
        data = build(v)
        with open(sys.argv[3], 'w', encoding='utf-8') as f:
            json.dump(data, f, ensure_ascii=False, separators=(',', ':'))
        print(f'schema.org {v}: типов {len(data["classes"])}, свойств {len(data["props"])}')
    else:
        sys.exit('команды: latest | build <версия> <файл>')
