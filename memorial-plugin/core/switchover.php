<?php
/**
 * Переход со старых папок (Memorial Core в memorial-core/, тема в memorial_speshu/) на новые.
 *
 * Как это работает:
 *  — пока активен старый плагин memorial-core, новый ничего не загружает (иначе типы записей,
 *    шорткоды и расписания регистрировались бы дважды) и показывает подсказку в админке;
 *  — при включении Memorial Plugin (хук активации) он сам: выключает старый плагин (его хук
 *    выключения снимает свои расписания и кэш), снимает оставшиеся старые расписания,
 *    копирует настройки темы theme_mods_memorial_speshu → theme_mods_memorial-theme и включает тему memorial-theme;
 *  — на следующем обычном запросе доделывает то, для чего нужны модули плагина (пересборка ссылок, расписание отзывов).
 *
 * Старые папки остаются на хостинге до 2.1.0 — откат описан в MIGRATION.md (данные общие, имена ключей в базе прежние).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

const MEMORIAL_PLUGIN_LEGACY_PLUGIN = 'memorial-core/memorial-core.php';
const MEMORIAL_PLUGIN_LEGACY_THEME  = 'memorial_speshu';
const MEMORIAL_PLUGIN_THEME         = 'memorial-theme';

/**
 * Активен ли старый плагин Memorial Core.
 *
 * @return bool
 */
function memorial_plugin_legacy_active() {
	return in_array( MEMORIAL_PLUGIN_LEGACY_PLUGIN, (array) get_option( 'active_plugins', array() ), true );
}

/**
 * Подсказка в админке, пока активен старый плагин.
 */
function memorial_plugin_legacy_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Memorial Plugin is paused because the old Memorial Core plugin is active. To switch over, deactivate Memorial Plugin and activate it again: it turns off Memorial Core and switches the theme. To roll back, just deactivate Memorial Plugin.', 'memorial-plugin' )
	);
}

/**
 * Хук активации: выключить старый плагин, снять его расписания, перенести настройки темы и включить новую тему.
 */
function memorial_plugin_switchover() {
	$log = array(
		'time'        => time(),
		'from_plugin' => false,
		'from_theme'  => '',
		'theme_mods'  => 'skipped',
	);

	if ( memorial_plugin_legacy_active() ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// Не тихо: хук выключения старого плагина уберёт свои расписания и кэш.
		deactivate_plugins( MEMORIAL_PLUGIN_LEGACY_PLUGIN );
		$log['from_plugin'] = true;
	}

	// Расписания под старыми именами хуков: после переименования их никто не обрабатывает.
	foreach ( array( 'mem_rewio_cron', 'mem_rewio_cron_once', 'mem_logos_cron', 'mem_img_job_tick' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	if ( get_stylesheet() === MEMORIAL_PLUGIN_LEGACY_THEME && wp_get_theme( MEMORIAL_PLUGIN_THEME )->exists() ) {
		$log['from_theme'] = MEMORIAL_PLUGIN_LEGACY_THEME;
		$old_mods          = get_option( 'theme_mods_' . MEMORIAL_PLUGIN_LEGACY_THEME, false );
		// Не перезаписываем: если новая тема уже включалась, её настройки главнее. Старая опция остаётся до 2.1.0 (откат).
		if ( false !== $old_mods && false === get_option( 'theme_mods_' . MEMORIAL_PLUGIN_THEME, false ) ) {
			add_option( 'theme_mods_' . MEMORIAL_PLUGIN_THEME, $old_mods );
			$log['theme_mods'] = 'copied';
		}
		switch_theme( MEMORIAL_PLUGIN_THEME );
	}

	update_option( 'memorial_plugin_switchover', $log, false );
	update_option( 'memorial_plugin_after_switch', 1, false );
	// Пересборка адресов товаров и работ (ключ прежний).
	update_option( 'mem_core_flush', 1, false );
}

/**
 * Следующий обычный запрос после переключения: то, для чего нужны загруженные модули.
 */
function memorial_plugin_after_switch() {
	if ( ! get_option( 'memorial_plugin_after_switch' ) ) {
		return;
	}
	delete_option( 'memorial_plugin_after_switch' );
	if ( function_exists( 'memorial_plugin_rewio_schedule' ) ) {
		// Регулярная загрузка отзывов под новым именем хука.
		memorial_plugin_rewio_schedule();
	}
}
