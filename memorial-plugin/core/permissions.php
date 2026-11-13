<?php
/**
 * «Мемориал → Настройки → Разрешения»: что из «Мемориала» доступно ролям «Редактор» и «Автор».
 *
 * Роли в базе не меняются: права плагина выдаются на лету фильтром user_has_cap по таблице
 * mem_modules_page['perms'] (ключ => роль => 0/1). Администратору доступно всё всегда. Поэтому после
 * удаления плагина у ролей не остаётся ничего лишнего.
 *
 * Права:
 *  - memorial_plugin_screen_<опция> — экран настроек «Мемориал → …» (Цены, Контакты, Отзывы…);
 *  - memorial_plugin_reviews        — «Список отзывов» (скрыть / показать отзыв Rewio);
 *  - memorial_edit_texts            — правка общих текстов прямо на странице (Block Bindings);
 *  - memorial_plugin_leads          — «Заявки на звонок»;
 *  - memorial_plugin_bookrev        — «Отзывы из книги».
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

/** Roles that can be given rights here (decision of 14.11.2026: only these two). */
const MEMORIAL_PLUGIN_PERM_ROLES = array( 'editor', 'author' );

/**
 * Rights that are not a settings screen: key => [capability, label]. Screens are added from the list of pages.
 *
 * @return array<string,array{0:string,1:string}>
 */
function memorial_plugin_perm_extra() {
	return array(
		'reviews' => array( 'memorial_plugin_reviews', __( 'Review list (hide / show)', 'memorial-plugin' ) ),
		'texts'   => array( 'memorial_edit_texts', __( 'Shared texts right on a page', 'memorial-plugin' ) ),
		'leads'   => array( 'memorial_plugin_leads', __( 'Call-back requests', 'memorial-plugin' ) ),
		'bookrev' => array( 'memorial_plugin_bookrev', __( 'Reviews from the guest book', 'memorial-plugin' ) ),
	);
}

/**
 * What the roles had before the table appeared: requests and the guest book — everyone who writes posts,
 * shared texts — editors; by the owner’s decision editors also get “Prices” and the review list.
 *
 * @return array<string,array<string,int>>
 */
function memorial_plugin_perm_defaults() {
	return array(
		'screen_mem_prices' => array(
			'editor' => 1,
			'author' => 0,
		),
		'reviews'           => array(
			'editor' => 1,
			'author' => 0,
		),
		'texts'             => array(
			'editor' => 1,
			'author' => 0,
		),
		'leads'             => array(
			'editor' => 1,
			'author' => 1,
		),
		'bookrev'           => array(
			'editor' => 1,
			'author' => 1,
		),
	);
}

/**
 * The table as saved, over the defaults.
 *
 * @return array<string,array<string,int>>
 */
function memorial_plugin_perms() {
	$saved = get_option( 'mem_modules_page' );
	$saved = is_array( $saved ) && is_array( $saved['perms'] ?? null ) ? $saved['perms'] : array();
	$out   = memorial_plugin_perm_defaults();
	foreach ( $saved as $key => $roles ) {
		foreach ( MEMORIAL_PLUGIN_PERM_ROLES as $role ) {
			if ( is_array( $roles ) && isset( $roles[ $role ] ) ) {
				$out[ $key ][ $role ] = empty( $roles[ $role ] ) ? 0 : 1;
			}
		}
	}
	return $out;
}

/**
 * Capability of a right.
 *
 * @param string $key Key of the table (screen_<option> or one of memorial_plugin_perm_extra()).
 * @return string
 */
function memorial_plugin_perm_cap( $key ) {
	$extra = memorial_plugin_perm_extra();
	return isset( $extra[ $key ] ) ? $extra[ $key ][0] : 'memorial_plugin_' . sanitize_key( $key );
}

/**
 * Capability of a settings screen «Мемориал → …»; «Настройки» itself stays for administrators.
 *
 * @param string $option Option of the page (mem_prices…).
 * @return string
 */
function memorial_plugin_screen_cap( $option ) {
	return 'mem_modules_page' === $option ? 'manage_options' : 'memorial_plugin_screen_' . sanitize_key( $option );
}

/**
 * May the current user open this settings screen?
 *
 * @param string $option Option of the page.
 * @return bool
 */
function memorial_plugin_can_screen( $option ) {
	return current_user_can( memorial_plugin_screen_cap( $option ) );
}

/**
 * The rights of the plugin for the user: all for administrators, from the table for editors and authors.
 *
 * @param array   $allcaps Capabilities of the user.
 * @param array   $caps    Asked capabilities.
 * @param array   $args    Arguments.
 * @param WP_User $user    User.
 * @return array
 */
function memorial_plugin_perm_caps( $allcaps, $caps, $args, $user ) {
	$asked = array_filter( (array) $caps, static fn( $c ) => is_string( $c ) && ( str_starts_with( $c, 'memorial_plugin_' ) || 'memorial_edit_texts' === $c ) );
	if ( ! $asked ) {
		return $allcaps;
	}
	if ( ! empty( $allcaps['manage_options'] ) ) {
		foreach ( $asked as $c ) {
			$allcaps[ $c ] = true;
		}
		return $allcaps;
	}
	$roles = array_intersect( MEMORIAL_PLUGIN_PERM_ROLES, $user instanceof WP_User ? (array) $user->roles : array() );
	if ( ! $roles ) {
		return $allcaps;
	}
	foreach ( memorial_plugin_perms() as $key => $by_role ) {
		$cap = memorial_plugin_perm_cap( $key );
		if ( ! in_array( $cap, $asked, true ) ) {
			continue;
		}
		foreach ( $roles as $role ) {
			if ( ! empty( $by_role[ $role ] ) ) {
				$allcaps[ $cap ] = true;
			}
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'memorial_plugin_perm_caps', 10, 4 );

/**
 * Rows of the table «Разрешения»: the screens of «Мемориал → …» and the other rights.
 *
 * @return array<int,array{key:string,label:string}>
 */
function memorial_plugin_perm_rows() {
	$rows = array();
	foreach ( memorial_plugin_admin_pages() as $opt => $p ) {
		if ( 'mem_modules_page' === $opt ) {
			continue;
		}
		$rows[] = array(
			'key'   => 'screen_' . $opt,
			'label' => wp_strip_all_tags( (string) ( $p['h1'] ?? $p['title'] ) ),
		);
	}
	foreach ( memorial_plugin_perm_extra() as $key => $e ) {
		$rows[] = array(
			'key'   => $key,
			'label' => $e[1],
		);
	}
	return $rows;
}

/**
 * Roles for the columns: id and the translated name.
 *
 * @return array<int,array{id:string,label:string}>
 */
function memorial_plugin_perm_roles() {
	$names = wp_roles()->get_names();
	$out   = array();
	foreach ( MEMORIAL_PLUGIN_PERM_ROLES as $role ) {
		if ( isset( $names[ $role ] ) ) {
			$out[] = array(
				'id'    => $role,
				'label' => translate_user_role( $names[ $role ] ),
			);
		}
	}
	return $out;
}

/**
 * Sanitize the table: known roles only, 0 / 1.
 *
 * @param mixed $v Value from the screen.
 * @return array<string,array<string,int>>
 */
function memorial_plugin_perm_clean( $v ) {
	$out = array();
	foreach ( is_array( $v ) ? $v : array() as $key => $roles ) {
		$key = sanitize_key( (string) $key );
		if ( '' === $key || ! is_array( $roles ) ) {
			continue;
		}
		foreach ( MEMORIAL_PLUGIN_PERM_ROLES as $role ) {
			$out[ $key ][ $role ] = empty( $roles[ $role ] ) ? 0 : 1;
		}
	}
	return $out;
}
