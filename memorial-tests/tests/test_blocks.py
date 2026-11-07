"""Блоки плагина: отзывы Rewio, вопрос–ответ, каталог, «Наши работы», карточки примеров работ."""
import pytest


def test_rewio_sort_and_filter(page, find_page):
    """Отзывы Rewio: показываются, сортировка и фильтр по площадке работают"""
    page.goto(find_page('class="rwo'))
    cards = page.locator(".rwo__card:visible, .rwo__item:visible")
    assert cards.count() > 0, "отзывы Rewio не показались"
    sort = page.locator("[data-rwo-sort]").first
    if sort.count():
        sort.click()
        assert sort.get_attribute("aria-pressed") == "true", "сортировка не включилась"
    src = page.locator("[data-rwo-src]")
    if src.count() > 1:
        src.nth(1).click()
        assert page.locator(".rwo__shown").first.is_visible(), "фильтр по площадке не сработал"
    assert not page.js_errors, page.js_errors


def test_faq_search(page, find_page):
    """Вопрос–ответ: поиск по вопросам находит нужные"""
    page.goto(find_page('class="mfaq'))
    q = page.locator("[data-faq-q]").first
    if not q.count():
        pytest.skip("нет поиска по вопросам")
    total = page.locator(".mfaq details:visible").count()
    q.fill("документ")
    page.wait_for_timeout(300)
    found = page.locator(".mfaq details:visible").count()
    assert 0 < found < total, f"поиск по вопросам: было {total}, найдено {found}"


def test_catalog_chip_scroll(page, find_page):
    """Кнопка раздела каталога прокручивает к разделу, заголовок не прячется под панелью"""
    page.goto(find_page('class="mcat__nav'))
    chips = page.locator(".mcat__nav a")
    if chips.count() < 2:
        pytest.skip("один раздел")
    chips.nth(1).click()
    page.wait_for_timeout(1200)
    href = chips.nth(1).get_attribute("href")
    top = page.evaluate("id => document.getElementById(id).getBoundingClientRect().top", href.split("#")[1])
    bar = page.evaluate("(document.querySelector('.mcat__bar')||{getBoundingClientRect:()=>({bottom:0})}).getBoundingClientRect().bottom")
    assert top >= bar - 2, f"заголовок раздела спрятан под панелью ({top} < {bar})"
    assert page.locator(".mcat__nav a.is-on").count() == 1


@pytest.mark.parametrize("device", ["page", "mobile"])
def test_works_thumbs(request, find_page, device):
    """«Наши работы»: миниатюры маленькие, по нажатию меняется большое фото (ПК и телефон)"""
    p = request.getfixturevalue(device)
    p.goto(find_page('class="work__thumb'))
    work = p.locator(".work").filter(has=p.locator(".work__thumb")).first
    work.scroll_into_view_if_needed()
    main = work.locator("a.work__img")
    before = main.get_attribute("href")
    thumbs = work.locator(".work__thumb")
    thumb = next((thumbs.nth(i) for i in range(thumbs.count()) if thumbs.nth(i).get_attribute("href") != before), thumbs.nth(1))
    box = thumb.bounding_box()
    assert box["width"] < 160, f"миниатюра слишком большая: {box['width']:.0f}px — стили блока не подключены?"
    thumb.tap() if device == "mobile" else thumb.click()
    p.wait_for_timeout(800)
    assert main.get_attribute("href") != before, "по нажатию на миниатюру большое фото не сменилось"
    assert not p.js_errors, p.js_errors


def test_work_cards_random(page, find_page):
    """[mem_works vid="карточки" poryadok="случайно"] — показано нужное число карточек"""
    u = find_page("data-wcards-rand")
    page.goto(u)
    box = page.locator("[data-wcards-rand]").first
    n = int(box.get_attribute("data-wcards-rand"))
    shown = box.locator(".mem-wcard:visible").count()
    assert 0 < shown <= n
    href = box.locator(".mem-wcard").first.get_attribute("href")
    assert href and "#" in href, "карточка не ведёт к работе"
