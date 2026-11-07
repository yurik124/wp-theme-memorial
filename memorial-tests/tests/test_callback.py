"""Окно «Перезвоните мне»: открывается, проверяет поля, ссылки на документы."""
import pytest


def _open(page, base_url):
    page.goto(base_url)
    if not page.locator("dialog#callback").count():
        pytest.skip("окна «Перезвоните мне» нет на странице")
    # то же, что нажатие любой кнопки «Перезвоните мне» (data-callback)
    page.evaluate("() => { const a = document.createElement('a'); a.href = '#'; a.dataset.callback = 'test'; document.body.append(a); a.click(); a.remove(); }")
    dlg = page.locator("dialog#callback")
    dlg.wait_for(state="visible")
    return dlg


def test_callback_opens_and_validates(page, base_url):
    """Окно «Перезвоните мне» открывается и не отправляет пустую форму"""
    dlg = _open(page, base_url)
    dlg.locator("[type=submit]").click()
    assert dlg.locator(".cb__err").is_visible(), "пустая форма отправилась без ошибки"
    consent = dlg.locator(".cb__consent a")
    assert consent.count() >= 1, "у галочки согласия нет ссылки на документ"
    assert not page.js_errors, page.js_errors


def test_callback_phone_mask(page, base_url):
    """Окно «Перезвоните мне»: телефон оформляется как +7 (905) 123-45-67"""
    dlg = _open(page, base_url)
    dlg.locator("input[name=phone]").type("89051234567")
    assert dlg.locator("input[name=phone]").input_value() == "+7 (905) 123-45-67"


@pytest.mark.forms
def test_callback_submit(page, base_url):
    """Настоящая заявка — придёт уведомление! Только с флагом --submit-forms"""
    dlg = _open(page, base_url)
    dlg.locator("input[name=phone]").type("79000000000")
    dlg.locator("input[name=name]").fill("Тест автотестов")
    dlg.locator("input[name=consent]").check()
    page.wait_for_timeout(3000)  # защита от роботов: форму, заполненную быстрее 2 секунд, сайт не принимает
    dlg.locator("[type=submit]").click()
    dlg.locator(".cb__done").wait_for(state="visible", timeout=10000)
