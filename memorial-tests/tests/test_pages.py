"""Все страницы из карты сайта: открываются, без ошибок PHP, с заголовком, разметка без ошибок."""
import re

import pytest

from conftest import ld_json

PHP_ERR = re.compile(r"(Fatal error|Warning|Notice|Deprecated|Parse error)</b>:|Uncaught |Stack trace:")


def test_sitemap_found(site):
    assert len(site) > 5, "карта сайта не найдена или почти пустая (/sitemap.xml, /wp-sitemap.xml)"


@pytest.mark.slow
def test_all_pages_ok(site):
    bad = [f"{d['status']} {u}" for u, d in site.items() if d["status"] != 200]
    assert not bad, "страницы не открываются:\n" + "\n".join(bad)


@pytest.mark.slow
def test_no_php_errors(site):
    bad = [u for u, d in site.items() if PHP_ERR.search(d["html"])]
    assert not bad, "на страницах текст ошибок PHP:\n" + "\n".join(bad)


@pytest.mark.slow
def test_no_raw_shortcodes(site):
    """Шорткод не сработал — на странице видно «[mem_…]» (в т. ч. в заголовке и описании)"""
    bad = [u for u, d in site.items() if re.search(r"\[/?mem_[a-z_]+", d["html"])]
    assert not bad, "на страницах виден шорткод в квадратных скобках:\n" + "\n".join(bad)


@pytest.mark.slow
def test_title_and_h1(site):
    bad = []
    for u, d in site.items():
        h = d["html"]
        if not h:
            continue
        t = re.search(r"<title>(.*?)</title>", h, re.S)
        if not t or not t.group(1).strip():
            bad.append(f"нет <title>: {u}")
        n = len(re.findall(r"<h1[\s>]", h))
        if n != 1:
            bad.append(f"<h1> {n} шт.: {u}")
    assert not bad, "\n".join(bad)


@pytest.mark.slow
def test_jsonld_valid(site):
    bad = []
    for u, d in site.items():
        try:
            ld_json(d["html"])
        except ValueError as e:
            bad.append(f"{u}: {e}")
    assert not bad, "ошибки в разметке JSON-LD:\n" + "\n".join(bad)


def test_home_organization_markup(site, base_url):
    """На главной — карточка компании для поисковиков (FuneralHome) с телефоном и адресом"""
    data = ld_json(site[base_url]["html"])
    graph = [g for d in data for g in (d.get("@graph") or [d])]
    org = [g for g in graph if g.get("@type") == "FuneralHome"]
    assert org, "на главной нет разметки FuneralHome"
    assert org[0].get("telephone") and org[0].get("address"), "в разметке компании нет телефона или адреса"


def test_breadcrumbs_markup(site, base_url):
    inner = [u for u in site if u != base_url and site[u]["status"] == 200][:20]
    without = [u for u in inner if '"BreadcrumbList"' not in site[u]["html"]]
    assert len(without) < len(inner) / 2, "на внутренних страницах нет разметки «хлебных крошек»: " + ", ".join(without[:5])
