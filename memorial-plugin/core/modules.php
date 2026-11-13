<?php
/**
 * «Мемориал → Настройки»: вкладка «Функции» — включение и выключение частей плагина (список — memorial_plugin_optional() в memorial-core.php).
 * Выключенная функция не загружается вовсе: нет её настроек в меню, расписаний и нагрузки на сайт.
 * Её шорткоды на страницах ничего не выводят (а не показываются текстом). Данные (работы, отзывы, настройки) не удаляются —
 * включите обратно, и всё вернётся. Вкладка «Разрешения» — права ролей (core/permissions.php).
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modules schema.
 */
function memorial_plugin_modules_schema() {
	$f = array();
	foreach ( memorial_plugin_optional_labels() as $k => [$name, $desc] ) {
		$f[ $k ] = array(
			'type'  => 'checkbox',
			'label' => $name . ' — ' . $desc,
		);
	}
	return array(
		'on'    => array(
			'type'   => 'group',
			'tab'    => __( 'Features', 'memorial-plugin' ),
			'label'  => __( 'Plugin features', 'memorial-plugin' ),
			'help'   => __( 'Untick a feature and it turns off: its menu items disappear, its blocks on pages show nothing. The data is not deleted. The catalog, prices, contacts, documents and SEO are the base of the site, they always work.', 'memorial-plugin' ),
			'fields' => $f,
		),
		'perms' => array(
			'type'  => 'perms',
			'tab'   => __( 'Permissions', 'memorial-plugin' ),
			'label' => __( 'What editors and authors may do', 'memorial-plugin' ),
			'help'  => __( 'Tick what a role may open and change. An administrator may do everything. “Settings” (this screen) is only for administrators.', 'memorial-plugin' ),
		),
	);
}
/**
 * Modules page defaults.
 */
function memorial_plugin_modules_page_defaults() {
	return array(
		'on'    => array_fill_keys( array_keys( memorial_plugin_optional() ), 1 ),
		'perms' => memorial_plugin_perm_defaults(),
	);
}

add_filter(
	'memorial_plugin_admin_pages',
	function ( $pages ) {
		$pages['mem_modules_page'] = array(
			'slug'   => 'mem-modules',
			'title'  => __( 'Settings', 'memorial-plugin' ),
			'schema' => 'memorial_plugin_modules_schema',
			'intro'  => __( 'What of the “Memorial” plugin works on the site, and what editors and authors may do.', 'memorial-plugin' ),
		);
		return $pages;
	}
);

/* Настройки страницы хранятся в mem_modules_page['on'] — копируем в mem_modules, который читается до загрузки файлов */
add_action( 'update_option_mem_modules_page', 'memorial_plugin_modules_sync', 10, 2 );
add_action( 'add_option_mem_modules_page', fn( $n, $v ) => memorial_plugin_modules_sync( null, $v ), 10, 2 );
/**
 * Modules sync.
 *
 * @param array $old Old.
 * @param mixed $new New.
 */
function memorial_plugin_modules_sync( $old, $new ) {
	$on = array();
	foreach ( array_keys( memorial_plugin_optional() ) as $k ) {
		$on[ $k ] = (int) ! empty( $new['on'][ $k ] );
	}
	$was = get_option( 'mem_modules' );
	update_option( 'mem_modules', $on, true );
	if ( $was !== $on ) {
		update_option( 'mem_core_flush', 1, false );                          // адреса работ и т.п.
		if ( empty( $on['rewio'] ) ) {
			wp_clear_scheduled_hook( 'memorial_plugin_rewio_cron' );
			wp_clear_scheduled_hook( 'memorial_plugin_rewio_cron_once' ); }
	}
}

/* Шорткоды выключенных функций — пусто вместо текста «[mem_works …]» на странице */
add_action(
	'init',
	function () {
		$off = array_flip( memorial_plugin_disabled_files() );
		foreach ( memorial_plugin_optional() as $k => $m ) {
			if ( ! isset( $off[ $k ] ) ) {
				continue;
			}
			foreach ( $m[3] as $tag ) {
				if ( ! shortcode_exists( $tag ) ) {
					add_shortcode( $tag, fn() => '' );
				}
			}
		}
	},
	20
);
