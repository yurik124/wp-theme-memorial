=== Memorial Plugin ===
Contributors: memorial
Tags: funeral, catalog, blocks, dataviews, abilities
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site features for the Memorial funeral services site: catalog, prices, reviews, works, call requests, settings, SEO and analytics — as blocks.

== Description ==

The plugin holds what the site can do; the Memorial Modern theme holds how it looks. The blocks work in any block theme: colours and fonts come from the theme’s presets, with fallbacks.

* Blocks: catalog, catalog tiles and sub-sections, product, prices and packages, cremation, works, reviews (sites, Yandex Maps, Rewio, guest book), questions and answers, “Good to know”, cemeteries, memorial meal halls, contacts, maps, header and footer parts, call-to-action, laurel and icons.
* Site values inside text: starting price, number of models, prices, years in business, phone, company details.
* Admin screens on DataViews and DataForm (@wordpress/admin-ui): products, types and categories, works, call requests, guest book, search statistics, all settings.
* Abilities API: 12 abilities (list and update products, change prices, show or hide, create from photos, export to CSV, call requests, convert content, search statistics) — used by the screens, the Command Palette (Ctrl/Cmd + K) and REST.
* Interactivity API for the behaviour of blocks; scripts and styles load only on pages with the plugin’s blocks.
* Floating parts (phone bar, “Call me back” window, cookie banner) join the footer by Block Hooks.
* Yandex Metrica with a cookie consent banner (152-FZ), SEOPress variables.
* Translation ready; Russian translation included.

Old [mem_…] shortcodes still work until 3.0.0; Memorial → Convert to blocks moves pages to blocks with a preview and a rollback.

== Installation ==

1. Upload the `memorial-plugin` folder to `wp-content/plugins/` (the GitHub workflow “Деплой на сайт” does it).
2. Activate the plugin. If Memorial Core 1.x is active, the plugin turns it off and switches the theme to `memorial-theme` (see MIGRATION.md).
3. Open Memorial → Analytics and save to confirm the Yandex Metrica mode.
4. Optional: Memorial → Convert to blocks for pages that still use shortcodes.

Building from the repository: `npm install && npm run build` (JS into `build/`).

== Frequently Asked Questions ==

= What is removed when the plugin is deleted? =

Scheduled events, temporary data and the cached Rewio reviews. Products, works, guest book, call requests, categories and settings stay. To delete them too, add `define( 'MEMORIAL_PLUGIN_DELETE_DATA', true );` to wp-config.php before deleting the plugin (make a database backup first).

= How do I roll back? =

Deactivate Memorial Plugin, activate Memorial Core 1.15 and the theme from the `memorial_speshu` folder. The data is shared, nothing is lost. Pages converted to blocks are rolled back on Memorial → Convert to blocks.

= Where are the translations? =

`languages/` inside the plugin: `memorial-plugin.pot`, the Russian `.po`, `.mo` and the JSON files for the admin scripts. After changing strings run `php tools/i18n.php pot`, update the `.po`, then `php tools/i18n.php build`.

== Changelog ==

= Unreleased =
* Fix: “Error loading block” in the editor for plugin blocks with an extra CSS class or style (blocks are registered before the block supports).
* The [mem_…] shortcodes report a deprecation and name the block that replaces them; the dashboard shows how many posts still use them.
* All blocks: colour, typography, spacing and border supports, keywords and previews.
* The home page and the Contacts page keep their blocks in the page content (one-time move with a revision and a rollback ability).
* The line under the site title is the site tagline (Settings → General).
* “Moving the catalog from pages” removed; test pages and empty “New product” items moved to the trash once.
* Admin fixes: Prices layout, full-width Reviews and Cemeteries, no default filter in What people search for, the Rewio key as a password field, translated list controls; no jQuery in the Rewio panel; inline scripts through the WordPress API.
* WordPress Coding Standards for all the code.

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

= 2.0.0 =
Make a backup first. Activating the plugin turns off Memorial Core and switches the theme to Memorial Modern 5.0 — see MIGRATION.md for the steps and the rollback.
