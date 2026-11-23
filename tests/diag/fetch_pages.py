"""Скачать HTML страниц сайта для проверки разметки (schema_check.py).

Адреса — из карты сайта (SEOPress: /sitemaps.xml, иначе WordPress: /wp-sitemap.xml) и главная,
плюс --paths (через запятую). Только чтение: GET, без входа на сайт.

    python3 tests/diag/fetch_pages.py http://localhost:8888 out/pages
    python3 tests/diag/fetch_pages.py https://site.ru out/pages --ssh

--ssh — страницы запрашиваются curl-ом с самого хостинга по SSH (адреса GitHub хостинг не пускает);
нужны переменные SFTP_HOST, SFTP_PORT, SFTP_USER и SSH_KEY_FILE или SFTP_PASSWORD.
Итог: <папка>/NNN.html и <папка>/index.json — [{"url", "file", "code"}].
"""
import argparse
import json
import os
import re
import shlex
import sys
import time
from urllib.parse import urljoin, urlparse

UA = 'Mozilla/5.0 (compatible; MemorialSchemaCheck/1.0)'


class Http:
    def __init__(self):
        import requests

        self.s = requests.Session()
        self.s.headers['User-Agent'] = UA

    def get(self, url):
        try:
            r = self.s.get(url, timeout=60)
            return r.status_code, r.text, r.url
        except Exception as e:  # noqa: BLE001
            return 0, str(e), url


class Ssh:
    def __init__(self, host):
        import paramiko

        auth = {'pkey': paramiko.PKey.from_path(os.environ['SSH_KEY_FILE'])} if os.environ.get('SSH_KEY_FILE') else {'password': os.environ['SFTP_PASSWORD']}
        self.t = paramiko.Transport((os.environ['SFTP_HOST'], int(os.environ.get('SFTP_PORT') or 22)))
        self.t.connect(username=os.environ['SFTP_USER'], **auth)
        self.host = host
        self.mode = None

    def run(self, cmd, timeout=150):
        ch = self.t.open_session()
        ch.settimeout(timeout)
        ch.exec_command(cmd)
        out = b''
        while True:
            d = ch.recv(65536)
            if not d:
                break
            out += d
        return ch.recv_exit_status(), out.decode('utf-8', 'replace')

    def get(self, url):
        # Сначала напрямую; если хостинг не видит сам себя по имени — через 127.0.0.1.
        modes = [self.mode] if self.mode is not None else ['', f'--resolve {self.host}:443:127.0.0.1 --resolve {self.host}:80:127.0.0.1 -k ']
        for mode in modes:
            code, out = self.run(f"curl -sS -L {mode}-m 90 -A {shlex.quote(UA)} -w '\\n__META__ %{{http_code}} %{{url_effective}}' {shlex.quote(url)}")
            if code == 0:
                self.mode = mode
                body, _, meta = out.rpartition('\n__META__ ')
                st, _, final = meta.partition(' ')
                return int(st) if st.isdigit() else 0, body, final.strip() or url
        return 0, '', url


def sitemap_urls(client, base):
    urls = []
    for index in ('/sitemaps.xml', '/wp-sitemap.xml'):
        code, text, _ = client.get(base + index)
        if code != 200 or '<loc>' not in text:
            continue
        for child in re.findall(r'<loc>\s*([^<\s]+)\s*</loc>', text):
            if child.endswith('.xml'):
                c, t, _ = client.get(child)
                if c == 200:
                    urls += re.findall(r'<loc>\s*([^<\s]+)\s*</loc>', t)
            else:
                urls.append(child)
        if urls:
            break
    return urls


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('base')
    ap.add_argument('out')
    ap.add_argument('--ssh', action='store_true')
    ap.add_argument('--paths', default='')
    ap.add_argument('--limit', type=int, default=400)
    a = ap.parse_args()
    base = a.base.rstrip('/')
    client = Ssh(urlparse(base).hostname) if a.ssh else Http()
    urls = [base + '/'] + sitemap_urls(client, base) + [urljoin(base + '/', p.strip().lstrip('/')) for p in a.paths.split(',') if p.strip()]
    # адреса с другим хостом в карте сайта (копия сайта на localhost) — к своему
    host = urlparse(base)
    fixed = []
    for u in urls:
        p = urlparse(u)
        if p.path.endswith(('.xml', '.xsl', '.jpg', '.jpeg', '.png', '.webp', '.svg', '.pdf')):
            continue
        fixed.append(p._replace(scheme=host.scheme, netloc=host.netloc).geturl())
    urls = list(dict.fromkeys(fixed))[: a.limit]
    os.makedirs(a.out, exist_ok=True)
    index = []
    for i, u in enumerate(urls):
        code, body, final = client.get(u)
        f = f'{i:03d}.html'
        with open(os.path.join(a.out, f), 'w', encoding='utf-8') as fh:
            fh.write(body)
        index.append({'url': u, 'final': final, 'file': f, 'code': code})
        print(code, u)
        if a.ssh:
            time.sleep(0.3)
    with open(os.path.join(a.out, 'index.json'), 'w', encoding='utf-8') as fh:
        json.dump({'base': base, 'pages': index}, fh, ensure_ascii=False, indent=1)
    print(f'страниц: {len(index)}')
    if not index or all(p['code'] != 200 for p in index):
        sys.exit('ни одна страница не скачалась')


if __name__ == '__main__':
    main()
