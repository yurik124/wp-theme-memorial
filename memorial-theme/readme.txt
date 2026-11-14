=== Memorial Modern ===
Contributors: memorial
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Version: 5.1.0
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: block-patterns, block-styles, full-site-editing, style-variations, translation-ready, accessibility-ready

Block theme of the Memorial funeral services site: “Bright memory” — light header and footer, laurel graphics, warm paper and sage green.

== Description ==

Templates, template parts, the main menu (Navigation) and patterns are edited in Appearance → Editor; colours, fonts and sizes are in theme.json and the site styles (style variations: Laurel — the main one, Bronze, Stone and Dark). Pages open in the editor together with their template, so the Site Editor shows the whole page. The data of the site — contacts, catalog, prices, reviews — comes from the blocks of Memorial Plugin; without the plugin the theme still works, only the data blocks show nothing.

Fonts (Cormorant Garamond, Lora) are bundled with the theme: nothing loads from external CDNs.

== Installation ==

Upload the `memorial-theme` folder to `wp-content/themes/` and activate it. Memorial Plugin 2.1 is needed for the data blocks.

== Changelog ==

= 5.1.0 =
* Final release: the way back to the old theme (memorial_speshu) is removed. On the first admin visit the theme deletes the logs of the moves of 5.0 and the settings of the old theme, fixes the main Navigation menu and moves unused copies of it to the trash.
* Pages open with their template in the editor and in the Site Editor (header, footer, title).
* Classic menu locations removed: the main menu is the Navigation menu; the footer columns use it too.
* Theme screenshot; “Contacts page” template title.

= 5.0.2 =
* The same look of a block on any page; no text smaller than 16 px; fewer `!important`.

= 5.0.1 =
* Block groups by purpose, “What to show” settings of blocks, the home page and Contacts without the header and footer in the page editor.

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
Screenshot: the home page of the site, made by the project's own check workflow.
