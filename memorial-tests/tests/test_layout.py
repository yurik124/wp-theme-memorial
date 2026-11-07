"""Вёрстка на ПК и телефоне: шапка, меню, подвал, ширина страниц, картинки, снимки страниц в отчёт."""
import pytest

from conftest import add_issue, fail_shot

# Какую страницу взять (кусок HTML, «url:часть адреса» или «kind:вид из карты сайта») → как назвать в отчёте.
KINDS = [
    (None, "Главная"),
    ('class="mcat', "Каталог на странице"),
    ("kind:раздел каталога", "Раздел каталога (товары)"),
    ("kind:товар", "Товар"),
    ('class="works', "Наши работы"),
    ('class="mem-wcards', "Примеры работ карточками"),
    ('class="rwo', "Отзывы Rewio"),
    ('class="mfaq', "Вопрос–ответ"),
    ('class="pkg', "Пакеты услуг"),
    ("url:kontakty", "Контакты"),
    ("url:ceny", "Цены"),
    ("url:otzyvy", "Отзывы"),
]

LAYOUT_JS = """() => {
  // ширина экрана устройства: телефон может «раздвинуть» страницу под широкий элемент, поэтому берём меньшее
  const W = Math.min(document.documentElement.clientWidth, screen.width || 1e9), H = window.innerHeight;
  const out = {wide: [], text: [], broken: [], huge: [], width: document.documentElement.scrollWidth - W};
  const name = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') +
     (typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split(/\\s+/).slice(0, 2).join('.') : '');
  const clipped = el => { for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
      const cs = getComputedStyle(p); if (cs.overflowX !== 'visible' || cs.position === 'fixed') return true; } return false; };
  document.querySelectorAll('body *').forEach(el => {
    const cs = getComputedStyle(el);
    if (cs.position === 'fixed' || cs.display === 'none' || clipped(el)) return;
    const r = el.getBoundingClientRect();
    if (r.width && r.right > W + 1) out.wide.push(name(el) + ' (вылезает на ' + Math.round(r.right - W) + ' px)');
    // текст не помещается в свой блок (длинное слово, ссылка): блок не шире экрана, а содержимое — шире
    if (cs.overflowX === 'visible' && el.scrollWidth > el.clientWidth + 2 && el.clientWidth && /^(H[1-6]|P|A|LI|TD|TH|SPAN|STRONG|B|DIV|LABEL|BUTTON)$/.test(el.tagName)
        && el.getBoundingClientRect().left + el.scrollWidth > W + 1)
      out.text.push(name(el) + ' «' + (el.innerText || '').trim().slice(0, 40) + '»');
  });
  document.querySelectorAll('img').forEach(i => {
    if (i.complete && i.getAttribute('src') && !i.naturalWidth && i.offsetWidth) out.broken.push(i.getAttribute('src').split('/').pop());
    if (i.offsetHeight > H * 1.6 && !i.closest('.lb, dialog')) out.huge.push(name(i.parentElement) + ' ' + i.offsetHeight + ' px');
  });
  out.wide = [...new Set(out.wide)].slice(0, 6);
  out.text = [...new Set(out.text)].slice(0, 6);
  return out;
}"""


def test_header_desktop(page, base_url):
    """Шапка на ПК: логотип и телефон"""
    page.goto(base_url)
    assert page.locator("#header .logo").first.is_visible(), "нет логотипа"
    assert page.locator("a[href^='tel:']").count() > 0, "нет ссылки-телефона"
    assert not page.js_errors, page.js_errors


def test_burger_menu_mobile(mobile, base_url, request):
    """Меню на телефоне открывается"""
    mobile.goto(base_url)
    burger = mobile.locator("#burger")
    assert burger.is_visible(), "на телефоне нет кнопки меню"
    burger.click()
    mobile.wait_for_timeout(500)
    assert mobile.locator("#nav a:visible").count() > 3, "меню на телефоне не открылось"
    assert not mobile.js_errors, mobile.js_errors


def test_footer_documents(page, base_url, api):
    """В подвале — ссылки на документы (политики), и они открываются"""
    page.goto(base_url)
    links = page.locator(".footer__copy a[href^='http']")
    texts = [t.lower() for t in links.all_inner_texts()]
    assert any("персональн" in t or "конфиденц" in t for t in texts), "в подвале нет ссылки на политику обработки ПД"
    for href in links.evaluate_all("els => els.map(e => e.href)"):
        assert api.get(href).status == 200, f"документ не открывается: {href}"


def _pick(site, marker):
    for u, d in site.items():
        if marker is None:
            hit = True
        elif marker.startswith("url:"):
            hit = marker[4:] in u
        elif marker.startswith("kind:"):
            hit = d.get("kind") == marker[5:]
        else:
            hit = marker in d["html"]
        if d["status"] == 200 and hit:
            return u
    return None


@pytest.mark.parametrize("device", ["page", "mobile"], ids=["ПК", "телефон"])
@pytest.mark.parametrize("marker,label", KINDS, ids=[k[1].replace("-", "–") for k in KINDS])
def test_layout(request, site, base_url, device, marker, label):
    """Вёрстка не ломается: ничего не вылезает за экран, картинки загружаются, нет гигантских блоков"""
    p = request.getfixturevalue(device)
    p.add_init_script("try { localStorage.setItem('mem_consent_v1', 'no') } catch (e) {}")  # без плашки cookie на снимках
    u = base_url if marker is None else _pick(site, marker)
    if not u:
        pytest.skip(f"на сайте нет страницы «{label}»")
    p.goto(u)
    p.evaluate("document.querySelectorAll('img[loading=lazy]').forEach(i => i.loading = 'eager')")
    p.wait_for_timeout(1500)
    problems = _problems(p, p.evaluate(LAYOUT_JS))
    if not problems:
        return
    where = "ПК" if device == "page" else "телефон"
    path = fail_shot(p, f"layout_{label}_{where}")
    # одна и та же проблема на ПК и телефоне, на разных страницах — считается одной
    new = [t for t in problems if add_issue(t, u, f"Вёрстка [{label}, {where}]", path)]
    request.node.mem_issue_done = True
    if not new:   # та же проблема уже найдена на другой странице — не дублируем
        pytest.skip("та же проблема, что уже найдена на другой странице (см. «Найденные проблемы»)")
    assert False, f"{u}\n" + "\n".join(new)


def _problems(p, r):
    """Список проблем вёрстки — короткими фразами, без адреса (адрес добавляется отдельно)"""
    out = []
    if r["wide"] or r["width"] > 1:
        out.append(f"страница шире экрана на {r['width']} px" + (" из-за: " + "; ".join(r["wide"]) if r["wide"] else ""))
    if r["text"]:
        out.append("текст не помещается (длинное слово или ссылка): " + "; ".join(r["text"]))
    if r["broken"]:
        out.append("не загрузились картинки: " + ", ".join(r["broken"][:5]))
    if r["huge"]:
        out.append("слишком высокие картинки: " + "; ".join(r["huge"][:3]))
    if p.js_errors:
        out.append("ошибки JavaScript: " + "; ".join(p.js_errors[:3]))
    return out


@pytest.mark.slow
def test_no_horizontal_scroll_mobile(mobile, site, request):
    """Все страницы на телефоне — не шире экрана, текст помещается (одна и та же проблема — один раз, со списком страниц)"""
    urls = [u for u, d in site.items() if d["status"] == 200]
    new = []
    for u in urls:
        mobile.goto(u)
        r = mobile.evaluate(LAYOUT_JS)
        r["broken"], r["huge"] = [], []           # картинки проверяет test_layout
        for t in _problems(mobile, r):
            text = t
            if add_issue(text, u, "Все страницы на телефоне"):
                _first_shot(mobile, text)
                new.append(f"{t}  (впервые: {u})")
        mobile.js_errors.clear()
    request.node.mem_issue_done = True
    assert not new, f"проверено {len(urls)} страниц; разных проблем: {len(new)}:\n" + "\n".join(new[:10])


def _first_shot(page, text):
    """Снимок — только для первой страницы с этой проблемой"""
    from conftest import ISSUES, issue_key
    ISSUES[issue_key(text)]["shot"] = fail_shot(page, "phone_" + text[:60])
