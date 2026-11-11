<?php
/**
 * Модули скорости удалены (этап 3): кэш страниц, сжатие HTML, относительные адреса, кэш шорткодов,
 * пересжатие фото и автосжатие CSS/JS на сайте. Кэш и сжатие на хостинге уже есть (см. MIGRATION.md),
 * сжатые копии CSS/JS собираются при сборке (tools/minify.php).
 *
 * Здесь — разовая уборка того, что модули оставили на сайте: правила «Memorial cache» в .htaccess,
 * папка кэша страниц, задания расписания. Настройка mem_speed не удаляется до 2.1.0 (откат).
 * Журнал — опция memorial_plugin_speed_removed.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Папка, где модуль хранил кэш страниц.
 *
 * @return string
 */
function memorial_plugin_speed_cache_dir() {
	return trailingslashit( WP_CONTENT_DIR ) . 'cache/memorial';
}

/**
 * Удалить папку кэша страниц со всем содержимым (только её — путь фиксированный).
 *
 * @return bool true, если папки больше нет.
 */
function memorial_plugin_speed_remove_cache_dir() {
	$dir = memorial_plugin_speed_cache_dir();
	if ( ! is_dir( $dir ) ) {
		return true;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- своя папка кэша, WP_Filesystem недоступен без запроса доступа.
		} else {
			wp_delete_file( $item->getPathname() );
		}
	}
	return rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- см. выше.
}

/**
 * Убрать блок «Memorial cache» из .htaccess.
 *
 * @return string Результат для журнала.
 */
function memorial_plugin_speed_remove_htaccess() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$file = get_home_path() . '.htaccess';
	if ( ! is_file( $file ) || ! extract_from_markers( $file, 'Memorial cache' ) ) {
		return 'none';
	}
	if ( ! wp_is_writable( $file ) ) {
		return 'not-writable';
	}
	return insert_with_markers( $file, 'Memorial cache', array() ) ? 'removed' : 'failed';
}

/**
 * Разовая уборка после удаления модулей скорости.
 */
function memorial_plugin_speed_cleanup() {
	if ( get_option( 'memorial_plugin_speed_removed' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	foreach ( array( 'memorial_plugin_img_job_tick', 'mem_img_job_tick' ) as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
	$log = array(
		'time'      => time(),
		'htaccess'  => memorial_plugin_speed_remove_htaccess(),
		'cache_dir' => memorial_plugin_speed_remove_cache_dir() ? 'removed' : 'left',
	);
	update_option( 'memorial_plugin_speed_removed', $log, false );
}
add_action( 'admin_init', 'memorial_plugin_speed_cleanup' );
