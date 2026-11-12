=== Memorial Modern ===
Contributors: memorial
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Version: 5.0.1
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: block-patterns, block-styles, full-site-editing, style-variations, translation-ready, accessibility-ready

Block theme of the Memorial funeral services site: “Bright memory” — light header and footer, laurel graphics, warm paper and sage green.

== Description ==

Templates, template parts and patterns are edited in Appearance → Editor; colours, fonts and sizes are in theme.json (style variations Bronze, Stone and Dark). The data of the site — contacts, catalog, prices, reviews — comes from the blocks of Memorial Plugin; without the plugin the theme still works, only the data blocks show nothing.

Fonts (Cormorant Garamond, Lora) are bundled with the theme: nothing loads from external CDNs.

== Installation ==

Upload the `memorial-theme` folder to `wp-content/themes/` and activate it (Memorial Plugin 2.0 does it when switching over from Memorial Core). The settings of the old “Memorial → Design” screen move into the site styles once, on the first visit to the admin.

== Changelog ==

= 5.0.0 =
* Block theme: theme.json v3, HTML templates and parts, patterns, style variations; menus moved to the Navigation block.
* Folder `memorial-theme` (was `memorial_speshu`), prefix `memorial_theme_`.
* Accessibility: contrast checked for every style variation, visible focus, keyboard menu.
* English strings with a Russian translation; header, footer and the “Nothing found” text are translatable patterns.

= 4.1.0 =
* Text size setting, cremation card next to the packages.

== Copyright ==

Memorial Modern, (C) Memorial. Distributed under the terms of the GNU GPL v2 or later.
Cormorant Garamond and Lora are licensed under the SIL Open Font License 1.1.
