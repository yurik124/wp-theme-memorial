=== Memorial Plugin ===
Contributors: memorial
Tags: funeral, catalog, blocks, dataviews, abilities
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site features for the Memorial funeral services site: catalog, prices, reviews, works, call requests, settings, SEO and analytics — as blocks.

== Description ==

The plugin holds what the site can do; the Memorial Modern theme holds how it looks. The blocks work in any block theme: colours and fonts come from the theme’s presets, with fallbacks.

* Blocks: catalog, catalog section cards and sub-sections, product parts, prices and funeral packages, cremation prices, works, reviews (sites, Yandex Maps, Rewio, guest book), questions and answers, “Good to know”, cemeteries, memorial meal halls, contacts, maps, header and footer parts, call-to-action, laurel and icons.
* Shared texts: phones, addresses and other values from the settings can be bound to paragraphs, headings and buttons (Block Bindings) and are edited right on the page.
* Site values inside text: starting price, number of models, prices (`{price:code}`), years in business, phone, company details; SEOPress variables.
* Admin screens on DataViews and DataForm (@wordpress/admin-ui): products, types and categories, works, call requests, Rewio reviews list, guest book, search statistics and all settings (contacts, prices, reviews, halls, cemeteries, documents, analytics, mail, permissions).
* Abilities API: 13 abilities (list and update products, change prices, show or hide, create from photos, export to CSV, call requests, hide a review, convert shortcodes and roll a conversion back, search statistics) — used by the screens, the Command Palette (Ctrl/Cmd + K) and REST.
* Interactivity API for the behaviour of blocks; scripts and styles load only on pages with the plugin’s blocks.
* Floating parts (phone bar, “Call me back” window, cookie banner) join the footer by Block Hooks.
* The footer menu columns, the breadcrumbs and the order of “Questions and answers” follow the site’s Navigation menu (Appearance → Editor → Navigation).
* Yandex Metrica with a cookie consent banner (152-FZ); request notifications by the hosting mail (wp_mail).
* Translation ready; Russian translation included.

Old [mem_…] shortcodes still work until 3.0.0 and report a deprecation; the editor command “Convert old codes to blocks” converts one post, with a rollback.

== Installation ==

1. Upload the `memorial-plugin` folder to `wp-content/plugins/` (the GitHub workflow “Деплой на сайт” does it) and activate it.
2. Activate the Memorial Modern theme (`memorial-theme`).
3. Open Memorial → Analytics and save to confirm the Yandex Metrica mode.

Building from the repository: `npm install && npm run build` (JS into `build/`), `composer dump-autoload --working-dir=memorial-plugin`.

== Frequently Asked Questions ==

= What is removed when the plugin is deleted? =

Scheduled events, temporary data and the cached Rewio reviews. Products, works, guest book, call requests, categories and settings stay. To delete them too, add `define( 'MEMORIAL_PLUGIN_DELETE_DATA', true );` to wp-config.php before deleting the plugin (make a database backup first).

= How do I roll back? =

Restore the backup of the site made before the update (UpdraftPlus: database and the plugins and themes folders). Since 2.1.0 the plugin no longer keeps the old Memorial Core 1.15 and its settings next to it.

= Where are the translations? =

`languages/` inside the plugin: `memorial-plugin.pot`, the Russian `.po`, `.mo` and the JSON files for the admin scripts. After changing strings run `php tools/i18n.php pot`, update the `.po`, then `php tools/i18n.php build`.

== Changelog ==

= 2.1.0 =
* Final release of the block version: the way back to Memorial Core 1.15 is removed. On the first admin visit the plugin deletes the settings of the removed screens, the logs of the one-time moves of 2.0 and the folders of the old plugin and theme (only if they are not active); the WebP choice is kept.
* Removed the one-time moves that have done their work: switching over from Memorial Core, cleaning after the speed modules, test pages, the home template, catalog tiles, the cremation package, the request mail address, the Metrica mode, the model tables of the catalog, the work and gallery cards and others.
* The footer menu columns, the breadcrumbs, the section cards and the order of “Questions and answers” use the Navigation menu of the site instead of the classic menu.
* Settings → Mail: the request notification template and a test message; Settings → Permissions for the Editor and Author roles.
* Reviews: the Rewio reviews list on DataViews, changed and removed reviews are marked, hiding by the Editor.
* Prices: packages in one list (cremation is a package), number of columns, `{price:code}` in texts.
* Fix: a `{price:…}` code that referred to itself made the home page fail.
* Fix: “Error loading block” in the editor for plugin blocks with an extra CSS class or style.
* The home page and the Contacts page keep their blocks in the page content; the line under the site title is the site tagline.
* All blocks: block.json, colour, typography, spacing and border supports, keywords and previews; WordPress Coding Standards for all the code.

= 2.0.0 =
* Block architecture for WordPress 7.1: blocks instead of shortcodes, admin on DataViews / DataForm, Abilities API and Command Palette, Interactivity API, theme presets with fallbacks, Block Hooks.
* Renamed from Memorial Core (`memorial-core`) to Memorial Plugin (`memorial-plugin`), prefix `memorial_plugin_`; the data in the database is unchanged.
* Removed: page cache, HTML minification, WebP conversion (done by hosting and WordPress), the “Shortcodes and blocks” help screen.
* Security: the IP address of call requests is no longer stored; rate limits for public endpoints.
* Accessibility: WCAG 2.2 AA contrast, visible focus, reduced motion, axe checks.
* English strings with a Russian translation; uninstall.php.

= 1.15.0 =
* Collapsible settings cards, home page block order, cremation card next to the funeral packages.

== Upgrade Notice ==

= 2.1.0 =
Make a backup first: after this update the old Memorial Core and the old theme are deleted and the only way back is the backup.
