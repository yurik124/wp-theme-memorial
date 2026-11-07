"""Фото не обрезаются; кэш страниц; сжатые стили; заголовки кэширования."""
import pytest

CROP_JS = """() => [...document.images].filter(i => i.offsetWidth > 200 && i.naturalWidth && getComputedStyle(i).objectFit === 'cover')
  .filter(i => !i.closest('.cat, .hub, .page-tiles, .hero, .mem-card'))   // плитки разделов обрезаются намеренно (точка фокуса)
  .map(i => { const n = i.naturalWidth / i.naturalHeight, b = i.clientWidth / i.clientHeight;
              const path = []; for (let x = i.parentElement; x && path.length < 3; x = x.parentElement) if (typeof x.className === 'string' && x.className) path.push(x.className.split(' ')[0]);
              return { src: i.currentSrc.split('/').pop(), vis: Math.min(n, b) / Math.max(n, b), cls: path.join(' < ') }; })
  .filter(x => x.vis < 0.8)"""


@pytest.mark.parametrize("marker", ['class="work__img', 'class="product__media', 'class="mcard__img'])
@pytest.mark.parametrize("device", ["page", "mobile"])
def test_photos_not_cropped(request, find_page, marker, device):
    """Крупные фото не обрезаны"""
    p = request.getfixturevalue(device)
    p.goto(find_page(marker))
    p.evaluate("document.querySelectorAll('img[loading=lazy]').forEach(i => i.loading = 'eager')")
    p.wait_for_timeout(1500)
    bad = p.evaluate(CROP_JS)
    assert not bad, "крупные фото обрезаны (видно < 80%): " + ", ".join(f"{b['src']} ({b['vis']:.0%}, {b['cls']})" for b in bad[:5])


def test_page_cache(api, base_url):
    """Кэш страниц: повторное открытие — из кэша, браузер не хранит старую страницу"""
    api.get(base_url)
    r = api.get(base_url)
    cache = r.headers.get("x-mem-cache")
    if cache is None:
        pytest.skip("кэш страниц выключен (или страницу отдаёт кэш хостинга)")
    assert cache == "HIT", f"повторный запрос не из кэша: X-Mem-Cache={cache}"
    assert "no-cache" in (r.headers.get("cache-control") or ""), "браузеру разрешено хранить страницу без проверки"


def test_assets_minified(page, base_url):
    """Стили и скрипты темы и плагина — сжатые (.min)"""
    page.goto(base_url)
    css = page.evaluate("[...document.styleSheets].map(s => s.href || '').filter(h => /memorial/.test(h))")
    js = page.evaluate("[...document.scripts].map(s => s.src).filter(h => /memorial/.test(h))")
    not_min = [h for h in css + js if ".min." not in h and "/assets/" in h]
    assert not not_min, "подключены несжатые файлы: " + ", ".join(not_min)
