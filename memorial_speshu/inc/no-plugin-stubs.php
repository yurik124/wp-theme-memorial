<?php
/**
 * «Пустые» версии функций плагина Memorial Core — чтобы шаблоны темы работали без плагина.
 * Подключается из inc/no-plugin.php только при показе страниц сайта, когда плагина нет.
 */
defined('ABSPATH') || exit;

if (!function_exists('mem_addrs')) { function mem_addrs(...$a) { return []; } }
if (!function_exists('mem_phones')) { function mem_phones(...$a) { return []; } }
if (!function_exists('mem_emails')) { function mem_emails(...$a) { return []; } }
if (!function_exists('mem_messengers')) { function mem_messengers(...$a) { return []; } }
if (!function_exists('mem_legal_parts')) { function mem_legal_parts(...$a) { return []; } }
if (!function_exists('mem_night')) { function mem_night(...$a) { return ''; } }
if (!function_exists('mem_tel')) { function mem_tel(...$a) { return ''; } }
if (!function_exists('mem_opt')) { function mem_opt(...$a) { return ''; } }
if (!function_exists('mem_years')) { function mem_years(...$a) { return 0; } }
if (!function_exists('mem_yandex_org')) { function mem_yandex_org(...$a) { return ''; } }
if (!function_exists('mem_map_src')) { function mem_map_src(...$a) { return ''; } }
if (!function_exists('mem_banner_on')) { function mem_banner_on(...$a) { return false; } }
if (!function_exists('mem_settings')) { function mem_settings(...$a) { return []; } }
if (!function_exists('mem_home')) { function mem_home(...$a) { return []; } }
if (!function_exists('mem_home_blocks')) { function mem_home_blocks(...$a) { return []; } }
if (!function_exists('mem_catalog_url')) { function mem_catalog_url(...$a) { return ''; } }
if (!function_exists('mem_catalog_page')) { function mem_catalog_page(...$a) { return 0; } }
if (!function_exists('mem_menu_children')) { function mem_menu_children(...$a) { return []; } }
if (!function_exists('mem_packages')) { function mem_packages(...$a) { return []; } }
if (!function_exists('mem_services_items')) { function mem_services_items(...$a) { return []; } }
if (!function_exists('mem_services_grid')) { function mem_services_grid(...$a) { return ''; } }
if (!function_exists('mem_price_html')) { function mem_price_html(...$a) { return ''; } }
if (!function_exists('mem_product_cat')) { function mem_product_cat(...$a) { return null; } }
if (!function_exists('mem_sorts')) { function mem_sorts(...$a) { return []; } }
if (!function_exists('mem_attrs')) { function mem_attrs(...$a) { return []; } }
if (!function_exists('mem_cat_tiles')) { function mem_cat_tiles(...$a) { return null; } }
if (!function_exists('mem_page_tiles')) { function mem_page_tiles(...$a) { return null; } }
if (!function_exists('mem_products_grid')) { function mem_products_grid(...$a) { return null; } }
if (!function_exists('mem_packages_html')) { function mem_packages_html(...$a) { return null; } }
if (!function_exists('mem_crumbs_html')) { function mem_crumbs_html(...$a) { return null; } }
if (!function_exists('mem_t')) { function mem_t($s) { return (string) $s; } }
if (!function_exists('mem_short_addr')) { function mem_short_addr($a) { return (string) $a; } }
if (!function_exists('mem_tel_of')) { function mem_tel_of($n) { return preg_replace('/[^\d+]/', '', (string) $n); } }
