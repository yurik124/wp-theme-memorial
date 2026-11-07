"""Плашка cookie, Яндекс Метрика, очистка адреса от UTM-меток."""
import pytest


def _ym_calls(page):
    return page.evaluate("(window.ym && ym.a || []).map(a => [...a].map(x => typeof x === 'object' ? JSON.stringify(x) : String(x)))")


def test_utm_removed_from_address(page, base_url):
    """UTM-метки убираются из адресной строки"""
    page.context.route("**/mc.yandex.ru/**", lambda r: r.abort())
    page.goto(base_url + "?utm_source=test&utm_medium=cpc&yclid=123")
    page.wait_for_timeout(300)
    if not page.evaluate("!!window.memOrigUrl"):
        pytest.skip("очистка меток выключена в «Мемориал → Аналитика»")
    assert "utm_" not in page.url and "yclid" not in page.url, f"метки остались в адресе: {page.url}"


def test_cookie_banner_and_metrika(page, base_url):
    """Плашка cookie и запуск Метрики"""
    page.context.route("**/mc.yandex.ru/**", lambda r: r.abort())
    page.goto(base_url + "?utm_source=test")
    page.wait_for_timeout(300)
    banner = page.locator("#consent")
    track = page.evaluate("window.memTrack || null")
    if not track:
        pytest.skip("Метрика и плашка выключены")
    mode = track.get("mode")
    if mode == "off":
        assert not banner.is_visible()
        return
    assert banner.is_visible(), "плашка cookie не показалась"
    assert banner.locator("a").count() >= 1, "в плашке нет ссылки на политику"
    if mode == "ask":
        assert not [c for c in _ym_calls(page) if c[1:2] == ["init"]], "Метрика запустилась до согласия"
        banner.locator("[data-consent=yes]").click()
    else:
        banner.locator("[data-consent=ok]").click()
    page.wait_for_timeout(200)
    assert not banner.is_visible(), "плашка не скрылась"
    if track.get("id"):
        calls = _ym_calls(page)
        assert any(c[1:2] == ["init"] for c in calls), "Метрика не запустилась"
        hits = [c for c in calls if c[1:2] == ["hit"]]
        if page.evaluate("!!window.memOrigUrl"):
            assert hits and "utm_source=test" in hits[0][2], "Метрика не получила адрес с метками"
    page.reload()
    page.wait_for_timeout(300)
    assert not banner.is_visible(), "плашка показывается снова после выбора"
