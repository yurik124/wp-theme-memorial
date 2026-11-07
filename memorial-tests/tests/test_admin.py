"""Админка: все страницы «Мемориала», каталога и работ открываются без ошибок. Ничего не сохраняет и не меняет.
Нужен логин — файл secrets.ini (см. README)."""
import re
from urllib.parse import urljoin

import pytest

from conftest import shot

PHP_ERR = re.compile(r"(Fatal error|Warning|Notice|Deprecated|Parse error)</b>:|Uncaught |Stack trace:")


def _admin_links(admin, base_url):
    admin.goto(urljoin(base_url, "wp-admin/index.php"))
    hrefs = admin.locator("#adminmenu a").evaluate_all("els => els.map(e => [e.href, e.innerText.trim()])")
    out, seen = [], set()
    for href, text in hrefs:
        if re.search(r"page=mem|post_type=mem_|taxonomy=mem_", href) and href not in seen:
            seen.add(href)
            out.append((href, text.split("\n")[0]))
    return out


def test_admin_pages_open(admin, base_url, request):
    """Все пункты меню «Мемориал», «Каталог», «Наши работы» открываются без ошибок PHP и JavaScript"""
    links = _admin_links(admin, base_url)
    assert links, "в меню админки не нашлось пунктов «Мемориал» — плагин Memorial Core включён?"
    problems = []
    for href, text in links:
        admin.js_errors.clear()
        resp = admin.goto(href)
        admin.wait_for_timeout(800)
        html = admin.content()
        if not resp or resp.status >= 400:
            problems.append(f"«{text}»: код {resp.status if resp else '—'}")
        if PHP_ERR.search(html):
            problems.append(f"«{text}»: на странице ошибка PHP")
        if admin.js_errors:
            problems.append(f"«{text}»: ошибки JavaScript: {admin.js_errors[:2]}")
        shot(request, admin, text)
    assert not problems, f"проверено {len(links)} страниц:\n" + "\n".join(problems)


def test_catalog_manager(admin, base_url, request):
    """Каталог в админке: таблица товаров загружается (перебор вкладок типов), сортировка работает"""
    admin.goto(urljoin(base_url, "wp-admin/edit.php?post_type=mem_product&page=mem-products"))
    admin.wait_for_timeout(1500)
    tabs = admin.locator(".mcm__tabs a[href]").evaluate_all("els => els.map(e => e.href)")
    rows = admin.locator("tr[data-id]")
    for href in [None] + tabs:          # тип по умолчанию может быть пустым — смотрим другие вкладки
        if href:
            admin.goto(href)
            admin.wait_for_timeout(1500)
        if rows.count():
            break
    assert rows.count() > 0, "ни в одной вкладке типов нет товаров — таблица не загрузилась?"
    sort = admin.locator("[data-sort]").first
    if sort.count():
        sort.click()
        admin.wait_for_timeout(400)
    shot(request, admin, "catalog")
    assert not admin.js_errors, admin.js_errors


def test_works_manager(admin, base_url, request):
    """«Наши работы» в админке: список работ загружается"""
    admin.goto(urljoin(base_url, "wp-admin/edit.php?post_type=mem_work&page=mem-works"))
    rows = admin.locator("tr[data-id]")
    rows.first.wait_for(state="attached", timeout=20000)
    assert rows.count() > 0, "список работ пустой"
    shot(request, admin, "works")
    assert not admin.js_errors, admin.js_errors


def test_shortcode_help(admin, base_url, request):
    """«Шорткоды и блоки»: расшифровка шорткода работает"""
    admin.goto(urljoin(base_url, "wp-admin/edit.php?post_type=page&page=mem-help"))
    field = admin.locator("#mh-decode")
    field.fill('[mem_works vid="карточки" chislo="3"]')
    admin.wait_for_timeout(1500)
    text = admin.locator("body").inner_text()
    assert "карточк" in text.lower(), "расшифровка шорткода не появилась"
    shot(request, admin, "help")
    assert not admin.js_errors, admin.js_errors
