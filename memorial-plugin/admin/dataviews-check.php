<?php
/**
 * Проверочная страница сборки DataViews и @wordpress/admin-ui (этап 1).
 *
 * Есть только при WP_DEBUG: «Инструменты → Memorial: DataViews check». Её открывает e2e-проверка в CI
 * на WordPress 7.1.4 (tests/e2e/dataviews-check.spec.js). Удаляется на этапе 4, когда появятся настоящие экраны.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Регистрирует скрытую страницу в разделе «Инструменты».
 */
function memorial_plugin_dataviews_check_menu() {
	$hook = add_management_page(
		__( 'Memorial: DataViews check', 'memorial-plugin' ),
		__( 'Memorial: DataViews check', 'memorial-plugin' ),
		'manage_options',
		'memorial-plugin-dataviews-check',
		'memorial_plugin_dataviews_check_render'
	);
	if ( $hook ) {
		add_action( 'load-' . $hook, 'memorial_plugin_dataviews_check_assets' );
	}
}
add_action( 'admin_menu', 'memorial_plugin_dataviews_check_menu' );

/**
 * Подключает собранный скрипт только на этой странице.
 */
function memorial_plugin_dataviews_check_assets() {
	add_action(
		'admin_enqueue_scripts',
		function () {
			$asset_file = MEMORIAL_PLUGIN_DIR . '/build/dataviews-check/index.asset.php';
			if ( ! is_readable( $asset_file ) ) {
				return;
			}
			$asset = require $asset_file;
			wp_enqueue_script(
				'memorial-plugin-dataviews-check',
				MEMORIAL_PLUGIN_URL . '/build/dataviews-check/index.js',
				$asset['dependencies'],
				$asset['version'],
				array( 'in_footer' => true )
			);
			wp_set_script_translations( 'memorial-plugin-dataviews-check', 'memorial-plugin' );
			wp_enqueue_style( 'wp-components' );
		}
	);
}

/**
 * Контейнер для React-приложения; без сборки — понятное сообщение.
 */
function memorial_plugin_dataviews_check_render() {
	if ( ! is_readable( MEMORIAL_PLUGIN_DIR . '/build/dataviews-check/index.asset.php' ) ) {
		echo '<div class="wrap"><p>' . esc_html__( 'The JavaScript build is missing. Run npm run build.', 'memorial-plugin' ) . '</p></div>';
		return;
	}
	echo '<div id="memorial-plugin-dataviews-check" class="wrap"></div>';
}
