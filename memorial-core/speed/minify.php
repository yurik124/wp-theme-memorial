<?php
/**
 * Лишние отступы между тегами в HTML страниц.
 */
defined('ABSPATH') || exit;

/* Лишние отступы между тегами в HTML страниц (≈3–5 КБ на странице). Текст и вид страницы не меняются:
   браузер всё равно схлопывает пробелы. Страницы с <pre>/<textarea> не трогаем. */
add_action('template_redirect', function () {
    if (is_admin() || is_feed() || is_customize_preview() || (defined('REST_REQUEST') && REST_REQUEST)) return;
    ob_start(function ($html) {
        if ($html === '' || stripos($html, '<pre') !== false || stripos($html, '<textarea') !== false) return $html;
        $out = preg_replace(['/>[ \t]*\n\s*</', '/\n[ \t]+/'], [">\n<", "\n"], $html);
        return is_string($out) ? $out : $html;
    });
}, 1);
