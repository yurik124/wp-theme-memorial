"""Шапка, меню, подвал, документы, горизонтальная прокрутка на телефоне, ошибки JavaScript."""
import pytest


def test_header_desktop(page, base_url):
    page.goto(base_url)
    assert page.locator("header .logo, .header .logo").first.is_visible(), "нет логотипа"
    assert page.locator("a[href^='tel:']").count() > 0, "нет ссылки-телефона"
    assert not page.js_errors, page.js_errors


def test_burger_menu_mobile(mobile, base_url):
    mobile.goto(base_url)
    burger = mobile.locator(".burger, [aria-controls='nav'], .header__burger").first
    assert burger.is_visible(), "на телефоне нет кнопки меню"
    burger.click()
    mobile.wait_for_timeout(400)
    assert mobile.locator("nav a:visible").count() > 3, "меню на телефоне не открылось"
    assert not mobile.js_errors, mobile.js_errors


def test_footer_documents(page, base_url, api):
    """В подвале — Политика обработки ПД и Политика cookie, ссылки открываются"""
    page.goto(base_url)
    links = page.locator(".footer__copy a[href^='http']")
    texts = [t.lower() for t in links.all_inner_texts()]
    assert any("персональн" in t or "конфиденц" in t for t in texts), "в подвале нет ссылки на политику обработки ПД"
    for href in links.evaluate_all("els => els.map(e => e.href)"):
        assert api.get(href).status == 200, f"документ не открывается: {href}"


@pytest.mark.slow
@pytest.mark.parametrize("n", range(12))
def test_no_horizontal_scroll_mobile(mobile, site, n):
    """Страница не шире экрана телефона (нет прокрутки вбок)"""
    urls = [u for u, d in site.items() if d["status"] == 200]
    if n >= len(urls):
        pytest.skip("страниц меньше")
    u = urls[n * max(1, len(urls) // 12)]
    mobile.goto(u)
    w = mobile.evaluate("document.documentElement.scrollWidth - window.innerWidth")
    assert w <= 1, f"{u}: страница шире экрана на {w}px"
    assert not mobile.js_errors, f"{u}: {mobile.js_errors}"


@pytest.mark.slow
def test_no_js_errors_key_pages(page, site, find_page):
    checked = 0
    for marker in ['class="rwo', 'class="mfaq', 'class="mcat', 'class="works', 'class="mem-wcards', 'class="pkg']:
        try:
            u = find_page(marker)
        except pytest.skip.Exception:
            continue
        page.goto(u)
        page.wait_for_timeout(300)
        checked += 1
        assert not page.js_errors, f"{u}: {page.js_errors}"
    assert checked, "не нашлось страниц с блоками"
