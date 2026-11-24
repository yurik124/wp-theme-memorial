# Страницы из репозитория

Один файл — одна страница. Путь файла повторяет адрес: `ritualnye-uslugi/katafalk.html` → `/ritualnye-uslugi/katafalk/`, `ceny.html` → `/ceny/`, `index.html` — главная.

Первая строка — заголовок в JSON внутри HTML-комментария, дальше — разметка блоков WordPress (как редактор хранит `post_content`):

```html
<!-- memorial-page {
  "slug": "katafalk",
  "parent": "ritualnye-uslugi",
  "title": "Катафалк на похороны",
  "seo_title": "Катафалк в Белгороде: заказать ритуальный автомобиль",
  "seo_description": "Ford Transit на 8 мест для провожающих. Входит в пакеты похорон, можно заказать отдельно.",
  "status": "publish",
  "template": "",
  "menu": { "parent": "/ritualnye-uslugi/", "label": "Катафалк", "after": "/ritualnye-uslugi/traurnyj-zal/" },
  "featured_image": "katafalk-ford-transit.jpg",
  "version": 1
} -->
<!-- wp:paragraph {"className":"is-style-lead"} -->
<p class="is-style-lead">…</p>
<!-- /wp:paragraph -->
```

| Поле | Что это |
|---|---|
| `slug` | адрес страницы, латиница |
| `parent` | путь родителя (`ritualnye-uslugi`), пусто — корень |
| `title` | H1: его выводит шаблон, в тексте H1 нет |
| `seo_title`, `seo_description` | SEOPress; `%%mem_код%%` можно |
| `status` | `draft` или `publish` |
| `template` | пусто — обычная страница |
| `menu` | куда добавить пункт меню; `null` — не добавлять |
| `featured_image` | имя файла из медиатеки, необязательно |
| `version` | **увеличить при каждой правке файла** — иначе страница на сайте не обновится |

Ссылки на страницы в атрибутах блоков — **адресами**, не ID: `"pages":"/ritualnye-uslugi/traurnyj-zal/,/ceny/"` у `cards` и `guide`, `"page":"/ceny/"` у `link-button`. Цены — только кодами `{price:код}`.

Как выкладывать:
1. Ветка `content/<slug>` от `block-migration`, файл сюда.
2. CI: `tools/content-lint.php` (заголовок, SEO в знаках и пикселях, блоки, цены, стоп-лист, ссылки). Коды цен и адреса страниц — `tools/content-site.json`; обновить: `git push origin <ветка>:sitedata/<ветка>`.
3. Слияние в `block-migration`, затем в `main`; деплой — владелец.
4. Владелец заходит в админку: новые и нетронутые страницы записываются сами, остальное — «Мемориал → Страницы из репозитория».
5. Переобход страницы в Яндекс Вебмастере.
