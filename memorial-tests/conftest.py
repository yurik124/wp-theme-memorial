"""
Общие настройки тестов сайта «Мемориал».

Запуск (из этой папки):
    pytest                                   — все проверки тестового сайта
    pytest --base-url https://memorialbel.ru — другой сайт
    pytest -m "not slow"                     — быстро, без обхода всех страниц
    pytest --headed                          — видеть браузер
    pytest -k works                          — только тесты, в названии которых есть «works»
"""
import json
import os
import re
import xml.etree.ElementTree as ET
from urllib.parse import urljoin, urlparse

import pytest
from playwright.sync_api import sync_playwright

DEFAULT_URL = os.environ.get("MEM_BASE_URL", "https://memorialbel.test-handyhost.ru")


def pytest_addoption(parser):
    g = parser.getgroup("memorial")
    g.addoption("--base-url", default=DEFAULT_URL, help="адрес сайта (по умолчанию тестовый)")
    g.addoption("--max-pages", type=int, default=400, help="сколько страниц из карты сайта проверять")
    g.addoption("--headed", action="store_true", help="показывать окно браузера")
    g.addoption("--submit-forms", action="store_true", help="по-настоящему отправить тестовую заявку «Перезвоните мне»")


def pytest_collection_modifyitems(config, items):
    if not config.getoption("--submit-forms"):
        skip = pytest.mark.skip(reason="нужен флаг --submit-forms")
        for it in items:
            if "forms" in it.keywords:
                it.add_marker(skip)


@pytest.fixture(scope="session")
def base_url(pytestconfig):
    return pytestconfig.getoption("--base-url").rstrip("/") + "/"


@pytest.fixture(scope="session")
def pw():
    with sync_playwright() as p:
        yield p


@pytest.fixture(scope="session")
def browser(pw, pytestconfig):
    b = pw.chromium.launch(headless=not pytestconfig.getoption("--headed"))
    yield b
    b.close()


@pytest.fixture(scope="session")
def api(pw, base_url):
    """HTTP-запросы без браузера (быстро): api.get(url)"""
    ctx = pw.request.new_context(base_url=base_url, ignore_https_errors=True,
                                 extra_http_headers={"User-Agent": "Mozilla/5.0 (memorial-tests)"})
    yield ctx
    ctx.dispose()


def _new_page(browser, **ctx_opts):
    ctx = browser.new_context(ignore_https_errors=True, **ctx_opts)
    page = ctx.new_page()
    page.js_errors = []
    page.on("pageerror", lambda e: page.js_errors.append(str(e)))
    return ctx, page


@pytest.fixture
def page(browser):
    """Компьютер 1366×900"""
    ctx, p = _new_page(browser, viewport={"width": 1366, "height": 900})
    yield p
    ctx.close()


@pytest.fixture
def mobile(browser, pw):
    """Телефон (Android, сенсорный экран)"""
    ctx, p = _new_page(browser, **pw.devices["Galaxy S9+"])
    yield p
    ctx.close()


def _sitemap_urls(api, base_url, limit):
    """Адреса из карты сайта: All in One SEO (/sitemap.xml) или WordPress (/wp-sitemap.xml)"""
    seen, out = set(), []
    queue = [urljoin(base_url, "sitemap.xml"), urljoin(base_url, "wp-sitemap.xml")]
    host = urlparse(base_url).netloc
    while queue and len(out) < limit:
        sm = queue.pop(0)
        if sm in seen:
            continue
        seen.add(sm)
        r = api.get(sm)
        if not r.ok or b"<" not in r.body()[:200]:
            continue
        try:
            root = ET.fromstring(r.body())
        except ET.ParseError:
            continue
        for loc in root.iter():
            if not loc.tag.endswith("loc") or not loc.text:
                continue
            u = loc.text.strip()
            if urlparse(u).netloc != host:
                continue
            if u.endswith(".xml"):
                queue.append(u)
            elif u not in out and not re.search(r"\.(jpe?g|png|webp|pdf)$", u, re.I):
                out.append(u)
    if base_url not in out:
        out.insert(0, base_url)
    return out[:limit]


@pytest.fixture(scope="session")
def site(api, base_url, pytestconfig):
    """Все страницы сайта: {url: {"status": код, "html": текст}} — загружаются один раз за запуск"""
    urls = _sitemap_urls(api, base_url, pytestconfig.getoption("--max-pages"))
    pages = {}
    for u in urls:
        r = api.get(u)
        pages[u] = {"status": r.status, "html": r.text() if "html" in (r.headers.get("content-type") or "") else ""}
    return pages


@pytest.fixture(scope="session")
def find_page(site):
    """find_page('class="rwo') — первая страница сайта, где в HTML есть этот кусок (или пропуск теста)"""
    def f(marker):
        for u, d in site.items():
            if d["status"] == 200 and marker in d["html"]:
                return u
        pytest.skip(f"на сайте нет страницы с «{marker}»")
    return f


def ld_json(html):
    """Все блоки разметки JSON-LD страницы (как объекты Python)"""
    out = []
    for m in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', html, re.S):
        out.append(json.loads(m))
    return out
