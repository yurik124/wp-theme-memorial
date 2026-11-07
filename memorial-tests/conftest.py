"""
Общие настройки тестов сайта «Мемориал».

Запуск (из этой папки):
    python -m pytest                                   — проверки тестового сайта (страницы — выборкой)
    python -m pytest --all-pages                       — все страницы из карты сайта, включая каждый товар
    python -m pytest --base-url https://memorialbel.ru — другой сайт
    python -m pytest -m "not slow"                     — быстро, без обхода страниц
    python -m pytest --headed                          — видеть окно браузера (по умолчанию — без окна)
    python -m pytest -k admin                          — только админка (нужен файл secrets.ini, см. README)

После запуска открывается отчёт reports/<дата>/отчет.html: что проверено, что упало, снимки страниц.
"""
import configparser
import html as htmlmod
import json
import os
import re
import ssl
import sys
import time
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from urllib.parse import urljoin, urlparse

import pytest
from playwright.sync_api import sync_playwright

HERE = Path(__file__).parent
RUN = HERE / "reports" / time.strftime("%Y-%m-%d_%H-%M-%S")   # папка этого запуска
RESULTS = []                                                  # для отчёта
ISSUES = {}   # найденные проблемы без повторов: подпись → {"text", "pages", "tests", "shot"}


def issue_key(text):
    """Подпись проблемы: без адресов и чисел — одна и та же ошибка на разных страницах считается одной"""
    t = re.sub(r"https?://\S+", "", text)
    t = re.sub(r"\d+", "N", t)
    return re.sub(r"\s+", " ", t).strip()


def add_issue(text, url="", test="", shot_path=""):
    """Записать проблему. True — новая, False — такая уже была (на другой странице или в другом тесте)"""
    k = issue_key(text)
    new = k not in ISSUES
    it = ISSUES.setdefault(k, {"text": text, "pages": [], "tests": [], "shot": shot_path})
    if url and url not in it["pages"]:
        it["pages"].append(url)
    if test and test not in it["tests"]:
        it["tests"].append(test)
    if shot_path and not it["shot"]:
        it["shot"] = shot_path
    return new

_SSL = ssl.create_default_context()
_SSL.check_hostname = False
_SSL.verify_mode = ssl.CERT_NONE  # тестовый сайт может быть с самоподписанным сертификатом

DEFAULT_URL = os.environ.get("MEM_BASE_URL", "https://memorialbel.test-handyhost.ru")

# Windows-консоль: вывод по-русски и со значками
for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8")
    except Exception:
        pass


def say(msg):
    print(msg, file=sys.stderr, flush=True)


# ---------------------------------------------------------------- параметры запуска
def pytest_addoption(parser):
    g = parser.getgroup("memorial")
    g.addoption("--base-url", default=DEFAULT_URL, help="адрес сайта (по умолчанию тестовый)")
    g.addoption("--all-pages", action="store_true", help="проверять все страницы из карты сайта (иначе товары — выборкой)")
    g.addoption("--products", type=int, default=12, help="сколько товаров брать в выборку")
    g.addoption("--headed", action="store_true", help="показывать окно браузера")
    g.addoption("--slowmo", type=int, default=0, help="замедлить действия в браузере, мс (вместе с --headed)")
    g.addoption("--submit-forms", action="store_true", help="по-настоящему отправить тестовую заявку «Перезвоните мне»")
    g.addoption("--no-open", action="store_true", help="не открывать отчёт после запуска")


def pytest_collection_modifyitems(config, items):
    if not config.getoption("--submit-forms"):
        skip = pytest.mark.skip(reason="нужен флаг --submit-forms")
        for it in items:
            if "forms" in it.keywords:
                it.add_marker(skip)


def pytest_sessionstart(session):
    RUN.mkdir(parents=True, exist_ok=True)
    say(f"\n  Сайт: {session.config.getoption('--base-url')}\n  Отчёт будет в: {RUN / 'отчет.html'}\n")


# ---------------------------------------------------------------- наглядный вывод
def _title(item):
    doc = (item.function.__doc__ or "").strip().splitlines()
    name = doc[0] if doc else item.originalname
    params = getattr(item, "callspec", None)
    if params:
        name += "  [" + params.id.replace("-", ", ") + "]"
    return name


@pytest.hookimpl(hookwrapper=True)
def pytest_runtest_makereport(item, call):
    outcome = yield
    rep = outcome.get_result()
    setattr(item, "rep_" + rep.when, rep)
    if rep.when == "call" or (rep.when == "setup" and rep.outcome != "passed"):
        status = {"passed": "ok", "failed": "fail", "skipped": "skip"}[rep.outcome]
        reason = ""
        if rep.outcome == "skipped" and isinstance(rep.longrepr, tuple):
            reason = rep.longrepr[2].replace("Skipped: ", "")
        elif rep.outcome == "failed":
            reason = _short_error(rep.longreprtext)
        mark = {"ok": "  ✔", "fail": "  ✘", "skip": "  –"}[status]
        say(f"{mark} {_title(item)}  ({rep.duration:.1f} с)" + (f"\n      → {reason}" if reason else ""))
        if status == "fail" and not getattr(item, "mem_issue_done", False):
            add_issue(f"{_title(item)}: {reason}", test=_title(item))
        RESULTS.append({"file": item.fspath.basename, "title": _title(item), "status": status, "time": rep.duration,
                        "reason": reason, "full": rep.longreprtext if status == "fail" else "",
                        "shots": getattr(item, "mem_shots", [])})


def _short_error(text):
    """Суть ошибки: строки «E …» без технических (assert …, Call log)"""
    out = []
    for line in text.splitlines():
        if not line.startswith("E "):
            continue
        l = line[1:].strip()
        if not l or l.startswith(("assert ", "Call log", "- ")) or l.startswith("+"):
            continue
        l = re.sub(r"^(AssertionError|playwright\._impl\._errors\.\w+): ", "", l)
        out.append(l)
        if len(out) == 3:
            break
    if not out:
        lines = [l for l in text.splitlines() if l.strip()]
        out = [lines[-1] if lines else ""]
    return " — ".join(out)[:400]


def pytest_report_teststatus(report, config):
    if report.when == "call" or (report.when == "setup" and report.skipped):
        return report.outcome, "", ""   # стандартные точки не нужны — печатаем своё


def pytest_sessionfinish(session, exitstatus):
    if not RESULTS:
        return
    path = _write_report(session.config.getoption("--base-url"))
    ok = sum(r["status"] == "ok" for r in RESULTS)
    bad = sum(r["status"] == "fail" for r in RESULTS)
    say(f"\n  Итого: ✔ {ok} прошло, ✘ {bad} ошибок, – {len(RESULTS) - ok - bad} пропущено."
        f"\n  Разных проблем: {len(ISSUES)} — список: {RUN / 'ошибки.txt'}\n  Отчёт: {path}\n")
    if not session.config.getoption("--no-open") and hasattr(os, "startfile"):
        try:
            os.startfile(path)  # Windows: открыть отчёт в браузере
        except Exception:
            pass


def _write_issues(site):
    """ошибки.txt — каждая проблема один раз, со списком страниц, где она есть"""
    lines = [f"Проблемы на сайте {site} — {time.strftime('%d.%m.%Y %H:%M')}", ""]
    if not ISSUES:
        lines.append("Проблем не найдено.")
    for i, it in enumerate(ISSUES.values(), 1):
        lines.append(f"{i}. {it['text']}")
        if it["pages"]:
            lines.append(f"   Страниц с этой проблемой: {len(it['pages'])}")
            lines += [f"     {u}" for u in it["pages"][:30]]
            if len(it["pages"]) > 30:
                lines.append(f"     … и ещё {len(it['pages']) - 30}")
        if it["tests"]:
            lines.append("   Нашли проверки: " + "; ".join(it["tests"][:5]))
        if it["shot"]:
            lines.append(f"   Снимок: {RUN / it['shot']}")
        lines.append("")
    (RUN / "ошибки.txt").write_text("\n".join(lines), encoding="utf-8")


def _write_report(site):
    _write_issues(site)
    e = htmlmod.escape
    rows, groups = [], {}
    for r in RESULTS:
        groups.setdefault(r["file"], []).append(r)
    names = {"test_pages.py": "Страницы сайта", "test_layout.py": "Вёрстка: шапка, подвал, ширина, картинки",
             "test_blocks.py": "Блоки плагина", "test_tracking.py": "Cookie, Метрика, UTM", "test_callback.py": "Окно «Перезвоните мне»",
             "test_images_speed.py": "Фото и скорость", "test_admin.py": "Админка"}
    for f, items in groups.items():
        rows.append(f'<h2>{e(names.get(f, f))}</h2><table>')
        for r in items:
            icon = {"ok": "✔", "fail": "✘", "skip": "–"}[r["status"]]
            shots = "".join(f'<a href="{e(s)}" target="_blank"><img src="{e(s)}" loading="lazy"></a>' for s in r["shots"])
            detail = f'<div class="why">{e(r["reason"])}</div>' if r["reason"] else ""
            if r["full"]:
                detail += f'<details><summary>подробно</summary><pre>{e(r["full"])}</pre></details>'
            rows.append(f'<tr class="{r["status"]}"><td class="i">{icon}</td><td>{e(r["title"])}{detail}'
                        f'{"<div class=shots>" + shots + "</div>" if shots else ""}</td><td class="t">{r["time"]:.1f} с</td></tr>')
        rows.append("</table>")
    ok = sum(r["status"] == "ok" for r in RESULTS)
    bad = sum(r["status"] == "fail" for r in RESULTS)
    skip = len(RESULTS) - ok - bad
    issues = ""
    if ISSUES:
        li = []
        for it in ISSUES.values():
            pages = "".join(f'<li><a href="{e(u)}" target="_blank">{e(u)}</a></li>' for u in it["pages"][:15])
            more = f"<li>… и ещё {len(it['pages']) - 15}</li>" if len(it["pages"]) > 15 else ""
            img = f'<div class="shots"><a href="{e(it["shot"])}" target="_blank"><img src="{e(it["shot"])}"></a></div>' if it["shot"] else ""
            cnt = f" <small>— страниц: {len(it['pages'])}</small>" if it["pages"] else ""
            li.append(f'<div class="iss"><b>{e(it["text"])}</b>{cnt}<ul>{pages}{more}</ul>{img}</div>')
        issues = f'<h2>Найденные проблемы ({len(ISSUES)}) — каждая один раз, со списком страниц</h2>' + "".join(li) + '<p><a href="ошибки.txt">ошибки.txt</a></p>'
    page = f"""<!doctype html><meta charset="utf-8"><title>Тесты сайта — {e(time.strftime('%d.%m.%Y %H:%M'))}</title>
<style>body{{font:15px/1.5 system-ui,sans-serif;max-width:1100px;margin:24px auto;padding:0 16px;color:#222}}
h1{{font-size:22px}}h2{{font-size:17px;margin:28px 0 8px}}table{{width:100%;border-collapse:collapse}}
td{{border-top:1px solid #eee;padding:6px 8px;vertical-align:top}}td.i{{width:24px;font-weight:700}}td.t{{width:60px;color:#888;text-align:right}}
tr.ok td.i{{color:#1a7f37}}tr.fail td.i{{color:#c62828}}tr.fail{{background:#fff3f3}}tr.skip{{color:#888}}
.why{{color:#a33;font-size:13px}}tr.skip .why{{color:#888}}pre{{white-space:pre-wrap;font-size:12px;background:#f6f6f6;padding:8px}}
.sum b{{font-size:20px;margin-right:16px}}.shots{{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}}
.shots img{{height:160px;border:1px solid #ddd;border-radius:4px}}
.iss{{background:#fff3f3;border-left:4px solid #c62828;padding:8px 12px;margin:8px 0}}.iss ul{{margin:4px 0 0;font-size:13px}}</style>
<h1>Тесты сайта {e(site)}</h1><p>{e(time.strftime('%d.%m.%Y %H:%M'))}</p>
<p class="sum"><b style="color:#1a7f37">✔ {ok}</b><b style="color:#c62828">✘ {bad}</b><b style="color:#888">– {skip}</b></p>
{issues}
{''.join(rows)}"""
    path = RUN / "отчет.html"
    path.write_text(page, encoding="utf-8")
    return path


# ---------------------------------------------------------------- браузер
@pytest.fixture(scope="session")
def base_url(pytestconfig):
    return pytestconfig.getoption("--base-url").rstrip("/") + "/"


@pytest.fixture(scope="session")
def pw():
    with sync_playwright() as p:
        yield p


@pytest.fixture(scope="session")
def browser(pw, pytestconfig):
    b = pw.chromium.launch(headless=not pytestconfig.getoption("--headed"), slow_mo=pytestconfig.getoption("--slowmo"))
    yield b
    b.close()


@pytest.fixture(scope="session")
def api(pw, base_url):
    """HTTP-запросы без браузера (быстро): api.get(url)"""
    ctx = pw.request.new_context(base_url=base_url, ignore_https_errors=True,
                                 extra_http_headers={"User-Agent": "Mozilla/5.0 (memorial-tests)"})
    yield ctx
    ctx.dispose()


def _block_trackers(ctx):
    """Метрика и счётчики не получают визиты от тестов — статистика сайта не портится"""
    ctx.route(re.compile(r"mc\.yandex\.(ru|com)|google-analytics|googletagmanager|top-fwz1\.mail\.ru|vk\.com/rtrg"), lambda r: r.abort())


def _patch_goto(page):
    """Не ждать полной загрузки чужих виджетов (карты, отзывы): страница готова, когда построен HTML"""
    page.set_default_navigation_timeout(60000)
    page.set_default_timeout(20000)
    orig = page.goto
    page.goto = lambda url, **kw: orig(url, **{"wait_until": "domcontentloaded", **kw})


def _new_page(browser, **ctx_opts):
    ctx = browser.new_context(ignore_https_errors=True, **ctx_opts)
    _block_trackers(ctx)
    page = ctx.new_page()
    _patch_goto(page)
    page.js_errors = []
    page.on("pageerror", lambda e: page.js_errors.append(str(e)))
    return ctx, page


def fail_shot(page, name):
    """Снимок страницы с проблемой (только при ошибке) → путь для отчёта"""
    d = RUN / "ошибки"
    d.mkdir(parents=True, exist_ok=True)
    fn = re.sub(r"[^\w.-]+", "_", name)[:120] + ".jpg"
    try:
        page.screenshot(path=str(d / fn), type="jpeg", quality=70)
        return f"ошибки/{fn}"
    except Exception:
        return ""


def _save_failure(request, page, label):
    """При ошибке: снимок всей страницы + её код + описание — в reports/<дата>/ошибки/<тест>/"""
    rep = getattr(request.node, "rep_call", None)
    if not rep or not rep.failed:
        return
    name = re.sub(r"[^\w.-]+", "_", request.node.name)[:80]
    d = RUN / "ошибки" / f"{name}_{label}"
    d.mkdir(parents=True, exist_ok=True)
    try:
        page.screenshot(path=str(d / "screenshot.png"), full_page=True)
        (d / "page.html").write_text(page.content(), encoding="utf-8")
        request.node.mem_shots = getattr(request.node, "mem_shots", []) + [f"ошибки/{name}_{label}/screenshot.png"]
    except Exception as e:  # страница могла закрыться
        (d / "page.html").write_text(f"не удалось сохранить: {e}", encoding="utf-8")
    (d / "info.txt").write_text(
        f"Тест: {request.node.nodeid}\nАдрес: {page.url}\nЭкран: {label}\n\nОшибки JavaScript:\n"
        + ("\n".join(page.js_errors) or "нет") + f"\n\nОшибка теста:\n{rep.longreprtext}", encoding="utf-8")
    # снимок уже упавшего теста в отчёт: допишем к последней записи
    for r in reversed(RESULTS):
        if r["title"] == _title(request.node):
            r["shots"] = getattr(request.node, "mem_shots", [])
            break


@pytest.fixture
def page(browser, request):
    """Компьютер 1366×900"""
    ctx, p = _new_page(browser, viewport={"width": 1366, "height": 900})
    yield p
    _save_failure(request, p, "pc")
    ctx.close()


@pytest.fixture
def mobile(browser, pw, request):
    """Телефон (Android, сенсорный экран)"""
    ctx, p = _new_page(browser, **pw.devices["Galaxy S9+"])
    yield p
    _save_failure(request, p, "phone")
    ctx.close()


# ---------------------------------------------------------------- страницы сайта
def _get(url, timeout=30):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (memorial-tests)"})
    try:
        with urllib.request.urlopen(req, timeout=timeout, context=_SSL) as r:
            ct = r.headers.get("content-type") or ""
            body = r.read()
            return r.status, ct, body
    except urllib.error.HTTPError as e:
        return e.code, "", b""
    except Exception as e:
        return 0, "", f"<!-- ошибка загрузки: {e} -->".encode()


def _sitemap(base_url):
    """[(адрес, тип)] из карты сайта: All in One SEO (/sitemap.xml) или WordPress (/wp-sitemap.xml).
       Тип — по названию файла карты: товары (mem_product), разделы каталога, страницы и т. д."""
    seen, out = set(), []
    queue = [(urljoin(base_url, "sitemap.xml"), ""), (urljoin(base_url, "wp-sitemap.xml"), "")]
    host = urlparse(base_url).netloc
    while queue:
        sm, kind = queue.pop(0)
        if sm in seen:
            continue
        seen.add(sm)
        status, ct, body = _get(sm)
        if status != 200 or b"<" not in body[:200]:
            continue
        try:
            root = ET.fromstring(body)
        except ET.ParseError:
            continue
        for loc in root.iter():
            if not loc.tag.endswith("loc") or not loc.text:
                continue
            u = loc.text.strip()
            if urlparse(u).netloc != host:
                continue
            if u.endswith(".xml"):
                fn = u.rsplit("/", 1)[-1]
                if "attachment" in fn:
                    continue   # страницы картинок не проверяем: сайт перенаправляет их (см. test_redirects)
                k = "товар" if "mem_product" in fn else "раздел каталога" if "mem_cat" in fn else "страница" if "page" in fn else "другое"
                queue.append((u, k))
            elif not re.search(r"\.(jpe?g|png|webp|pdf)$|[?&]attachment_id=", u, re.I) and u not in [x[0] for x in out]:
                out.append((u, kind or "другое"))
    return out


@pytest.fixture(scope="session")
def site(base_url, pytestconfig):
    """Страницы для проверки: {адрес: {"status", "html", "kind"}}.
       По умолчанию — все страницы и разделы, а из товаров (их сотни, все сделаны по одному шаблону) — выборка."""
    allp = _sitemap(base_url)
    if not pytestconfig.getoption("--all-pages"):
        n = pytestconfig.getoption("--products")
        prods = [x for x in allp if x[1] == "товар"]
        step = max(1, len(prods) // max(1, n))
        allp = [x for x in allp if x[1] != "товар"] + prods[::step][:n]
    if base_url not in [x[0] for x in allp]:
        allp.insert(0, (base_url, "страница"))
    kinds = {}
    for _, k in allp:
        kinds[k] = kinds.get(k, 0) + 1
    say("  Страниц для проверки: " + str(len(allp)) + " (" + ", ".join(f"{k}: {v}" for k, v in kinds.items()) + ")"
        + ("" if pytestconfig.getoption("--all-pages") else "  — все товары: --all-pages"))
    pages = {}

    def fetch(x):
        status, ct, body = _get(x[0])
        return x, status, body.decode("utf-8", "replace") if ("html" in ct or status == 0) else ""

    with ThreadPoolExecutor(max_workers=8) as pool:
        for i, ((u, k), status, html) in enumerate(pool.map(fetch, allp), 1):
            pages[u] = {"status": status, "html": html, "kind": k}
            if i % 20 == 0 or i == len(allp):
                say(f"    загружено {i} из {len(allp)}")
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
    return [json.loads(m) for m in re.findall(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', html, re.S)]


# ---------------------------------------------------------------- админка
def _secrets():
    """Логин и пароль админки: переменные MEM_ADMIN_USER / MEM_ADMIN_PASS или файл secrets.ini рядом:
       [admin]
       user = ваш_логин
       password = ваш_пароль"""
    user, pwd = os.environ.get("MEM_ADMIN_USER"), os.environ.get("MEM_ADMIN_PASS")
    f = HERE / "secrets.ini"
    if (not user or not pwd) and f.exists():
        c = configparser.ConfigParser()
        c.read(f, encoding="utf-8")
        user, pwd = c.get("admin", "user", fallback=None), c.get("admin", "password", fallback=None)
    return user, pwd


@pytest.fixture(scope="session")
def admin_state(browser, base_url):
    """Вход в админку один раз за запуск; дальше тесты работают с сохранёнными cookie"""
    user, pwd = _secrets()
    if not user or not pwd:
        pytest.skip("нет логина админки — создайте secrets.ini (см. README)")
    ctx = browser.new_context(ignore_https_errors=True, viewport={"width": 1440, "height": 900})
    p = ctx.new_page()
    p.goto(urljoin(base_url, "wp-login.php"))
    p.fill("#user_login", user)
    p.fill("#user_pass", pwd)
    p.click("#wp-submit")
    try:
        p.wait_for_url("**/wp-admin/**", timeout=20000)
    except Exception:
        ctx.close()
        pytest.fail("не удалось войти в админку: проверьте логин и пароль в secrets.ini (или на сайте капча / двухфакторный вход)")
    state = ctx.storage_state()
    ctx.close()
    return state


@pytest.fixture
def admin(browser, admin_state, request):
    """Страница браузера, вошедшая в админку"""
    ctx = browser.new_context(ignore_https_errors=True, viewport={"width": 1440, "height": 900}, storage_state=admin_state)
    _block_trackers(ctx)
    p = ctx.new_page()
    _patch_goto(p)
    p.js_errors = []
    p.on("pageerror", lambda e: p.js_errors.append(str(e)))
    yield p
    _save_failure(request, p, "admin")
    ctx.close()
