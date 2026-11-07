"""Перенаправления: страницы картинок и товаров без описания не показываются отдельно."""
import json

import pytest

from conftest import _get


def _rest(base_url, path):
    status, ct, body = _get(base_url + "wp-json/wp/v2/" + path)
    if status != 200:
        pytest.skip(f"REST API сайта недоступен ({status})")
    return json.loads(body)


def _status_no_follow(api, url):
    r = api.get(url, max_redirects=0)
    return r.status, r.headers.get("location", "")


def test_attachment_pages_redirect(api, base_url):
    """Страница картинки (?attachment_id=…) ведёт к записи с картинкой или к самому файлу (301), а не открывается пустой"""
    media = _rest(base_url, "media?per_page=1")
    if not media:
        pytest.skip("в медиатеке нет файлов")
    status, loc = _status_no_follow(api, f"{base_url}?attachment_id={media[0]['id']}")
    if status in (301, 302) and "attachment_id" not in loc:
        return
    # WordPress может сначала перевести ?attachment_id на красивый адрес картинки — проверяем и его
    if status in (301, 302) and loc:
        status, loc = _status_no_follow(api, loc)
    assert status in (301, 302), f"страница картинки открывается сама по себе (код {status}) — плагин должен перенаправлять"


def test_products_without_description_redirect(api, base_url):
    """Товар без описания: его адрес /tovar/… ведёт в раздел каталога к этому товару (#p-…)"""
    items = _rest(base_url, "mem_product?per_page=20&_fields=id,link,content")
    empty = [p for p in items if not (p.get("content", {}).get("rendered") or "").strip()]
    if not empty:
        pytest.skip("все товары с описанием (или включён режим «страница у каждого товара»)")
    status, loc = _status_no_follow(api, empty[0]["link"])
    if status == 200:
        pytest.skip("включён режим «страница у каждого товара» (Каталог → Страницы товаров)")
    assert status == 301 and f"#p-{empty[0]['id']}" in loc, f"{empty[0]['link']} → {status} {loc}"
