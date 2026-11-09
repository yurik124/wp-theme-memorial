<?php
/**
 * «Пустые» версии функций плагина Memorial Core — чтобы шаблоны темы работали без плагина.
 * Подключается из inc/no-plugin.php только при показе страниц сайта, когда плагина нет.
 */
defined('ABSPATH') || exit;

if (!function_exists('memorial_plugin_addrs')) { function memorial_plugin_addrs(...$a) { return []; } }
if (!function_exists('memorial_plugin_phones')) { function memorial_plugin_phones(...$a) { return []; } }
if (!function_exists('memorial_plugin_emails')) { function memorial_plugin_emails(...$a) { return []; } }
if (!function_exists('memorial_plugin_messengers')) { function memorial_plugin_messengers(...$a) { return []; } }
if (!function_exists('memorial_plugin_legal_parts')) { function memorial_plugin_legal_parts(...$a) { return []; } }
if (!function_exists('memorial_plugin_night')) { function memorial_plugin_night(...$a) { return ''; } }
if (!function_exists('memorial_plugin_tel')) { function memorial_plugin_tel(...$a) { return ''; } }
if (!function_exists('memorial_plugin_opt')) { function memorial_plugin_opt(...$a) { return ''; } }
if (!function_exists('memorial_plugin_years')) { function memorial_plugin_years(...$a) { return 0; } }
if (!function_exists('memorial_plugin_yandex_org')) { function memorial_plugin_yandex_org(...$a) { return ''; } }
if (!function_exists('memorial_plugin_map_src')) { function memorial_plugin_map_src(...$a) { return ''; } }
if (!function_exists('memorial_plugin_banner_on')) { function memorial_plugin_banner_on(...$a) { return false; } }
if (!function_exists('memorial_plugin_settings')) { function memorial_plugin_settings(...$a) { return []; } }
if (!function_exists('memorial_plugin_home')) { function memorial_plugin_home(...$a) { return []; } }
if (!function_exists('memorial_plugin_home_blocks')) { function memorial_plugin_home_blocks(...$a) { return []; } }
if (!function_exists('memorial_plugin_catalog_url')) { function memorial_plugin_catalog_url(...$a) { return ''; } }
if (!function_exists('memorial_plugin_catalog_page')) { function memorial_plugin_catalog_page(...$a) { return 0; } }
if (!function_exists('memorial_plugin_menu_children')) { function memorial_plugin_menu_children(...$a) { return []; } }
if (!function_exists('memorial_plugin_packages')) { function memorial_plugin_packages(...$a) { return []; } }
if (!function_exists('memorial_plugin_services_items')) { function memorial_plugin_services_items(...$a) { return []; } }
if (!function_exists('memorial_plugin_services_grid')) { function memorial_plugin_services_grid(...$a) { return ''; } }
if (!function_exists('memorial_plugin_price_html')) { function memorial_plugin_price_html(...$a) { return ''; } }
if (!function_exists('memorial_plugin_product_cat')) { function memorial_plugin_product_cat(...$a) { return null; } }
if (!function_exists('memorial_plugin_sorts')) { function memorial_plugin_sorts(...$a) { return []; } }
if (!function_exists('memorial_plugin_attrs')) { function memorial_plugin_attrs(...$a) { return []; } }
if (!function_exists('memorial_plugin_cat_tiles')) { function memorial_plugin_cat_tiles(...$a) { return null; } }
if (!function_exists('memorial_plugin_page_tiles')) { function memorial_plugin_page_tiles(...$a) { return null; } }
if (!function_exists('memorial_plugin_products_grid')) { function memorial_plugin_products_grid(...$a) { return null; } }
if (!function_exists('memorial_plugin_packages_html')) { function memorial_plugin_packages_html(...$a) { return null; } }
if (!function_exists('memorial_plugin_crumbs_html')) { function memorial_plugin_crumbs_html(...$a) { return null; } }
if (!function_exists('memorial_plugin_t')) { function memorial_plugin_t($s) { return (string) $s; } }
if (!function_exists('memorial_plugin_short_addr')) { function memorial_plugin_short_addr($a) { return (string) $a; } }
if (!function_exists('memorial_plugin_tel_of')) { function memorial_plugin_tel_of($n) { return preg_replace('/[^\d+]/', '', (string) $n); } }
