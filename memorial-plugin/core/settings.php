<?php
/**
 * Настройки сайта в админке: «Мемориал → Контакты» и «Мемориал → Главная страница».
 *
 * Каждая страница описана схемой полей (memorial_plugin_contacts_schema / memorial_plugin_home_schema).
 * По схеме строится форма, проверяются данные при сохранении и подставляются значения по умолчанию.
 * Чтобы добавить поле — допишите его в схему и в defaults, больше ничего не нужно.
 *
 * @package memorial-plugin
 */

defined( 'ABSPATH' ) || exit;

define( 'MEMORIAL_PLUGIN_MAX_URL', 'https://max.ru/u/f9LHodD0cOLf3sDsVZFTsBTdONk3kEAboq9CfefPcekCM_KZnnErTEhejHM' );

/*
=========================================================
 * Схемы
 * =======================================================
 */

/**
 * Contacts schema.
 */
function memorial_plugin_contacts_schema() {
	return array(
		'phones'     => array(
			'type'   => 'repeater',
			'label'  => __( 'Phones', 'memorial-plugin' ),
			'add'    => __( 'Add a phone', 'memorial-plugin' ),
			'help'   => __( 'The first number is the main one by itself: it is in the header, on the “Call” and “Order” buttons, in the “Site value → Main phone” of texts and in SEO titles as %%mem_phone%%. To make another number the main one, move it to the top with the arrows.', 'memorial-plugin' ),
			'fields' => array(
				'num'  => array(
					'type'        => 'text',
					'format'      => 'phone', // Checked when it is changed on a page (blocks/bindings.php).
					'label'       => __( 'Phone number', 'memorial-plugin' ),
					'placeholder' => '+7 (900) 000-00-00',
				),
				'note' => array(
					'type'        => 'text',
					'label'       => __( 'Note (optional)', 'memorial-plugin' ),
					'placeholder' => __( 'For example: round the clock', 'memorial-plugin' ),
				),
			),
		),
		'emails'     => array(
			'type'   => 'repeater',
			'label'  => __( 'Email', 'memorial-plugin' ),
			'add'    => __( 'Add an email', 'memorial-plugin' ),
			'fields' => array(
				'email' => array(
					'type'        => 'email',
					'label'       => 'E-mail',
					'placeholder' => 'name@mail.ru',
				),
			),
		),
		'addrs'      => array(
			'type'     => 'repeater',
			'label'    => __( 'Addresses and opening hours', 'memorial-plugin' ),
			'add'      => __( 'Add an address', 'memorial-plugin' ),
			'collapse' => 'addr',
			'open'     => true,
			'help'     => __( 'The first address is shown in the top bar of the site. Every address gets a tab with a map on the home page.', 'memorial-plugin' ),
			'fields'   => array(
				'addr'  => array(
					'type'        => 'text',
					'label'       => __( 'Address', 'memorial-plugin' ),
					'placeholder' => __( '308000, Belgorod, 40 Studencheskaya St.', 'memorial-plugin' ),
				),
				'hours' => array(
					'type'        => 'text',
					'label'       => __( 'Opening hours', 'memorial-plugin' ),
					'placeholder' => __( 'Daily from 9:00 to 18:00', 'memorial-plugin' ),
				),
				'title' => array(
					'type'        => 'text',
					'label'       => __( 'Map tab label (optional)', 'memorial-plugin' ),
					'placeholder' => __( 'By default — the street and the house', 'memorial-plugin' ),
				),
				'map'   => array(
					'type'  => 'map',
					'label' => __( 'Map (optional)', 'memorial-plugin' ),
					'help'  => __( 'Leave empty — the map finds the address itself. To set an exact marker, paste a link from Yandex Maps or the code from the “Map Constructor”.', 'memorial-plugin' ),
				),
			),
		),
		'night'      => array(
			'type'        => 'text',
			'label'       => __( 'Calls outside working hours', 'memorial-plugin' ),
			'placeholder' => __( 'We take calls round the clock', 'memorial-plugin' ),
			'help'        => __( 'Shown in the top bar, on the first screen, in “Contacts” and in the footer. Empty — not shown.', 'memorial-plugin' ),
		),
		'messengers' => array(
			'type'   => 'group',
			'label'  => __( 'Messengers', 'memorial-plugin' ),
			'help'   => __( 'Shown in the call-back window and in the “Contacts” block. Empty field — no button.', 'memorial-plugin' ),
			'fields' => array(
				'max'      => array(
					'type'        => 'text',
					'label'       => __( 'MAX — profile link', 'memorial-plugin' ),
					'placeholder' => 'https://max.ru/u/…',
					'help'        => __( 'In the MAX app: Profile → Share profile → Copy link.', 'memorial-plugin' ),
				),
				'telegram' => array(
					'type'        => 'text',
					'label'       => 'Telegram',
					'placeholder' => __( 'memorialbel or https://t.me/…', 'memorial-plugin' ),
				),
				'whatsapp' => array(
					'type'        => 'text',
					'label'       => __( 'WhatsApp — number', 'memorial-plugin' ),
					'placeholder' => '+7 900 000-00-00',
				),
			),
		),
		'call'       => array(
			'type'   => 'group',
			'label'  => __( '“Call” button on a computer', 'memorial-plugin' ),
			'help'   => __( 'On a phone the button simply calls. On a computer MAX opens first by the link above; the “Call me back” window appears if MAX did not open (the browser blocked the tab or the visitor came back to the site quickly). Requests come by email (where — “Memorial → Settings → Email”) and are kept in “Memorial → Call-back requests”.', 'memorial-plugin' ),
			'fields' => array(
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Window title', 'memorial-plugin' ),
				),
				'note'    => array(
					'type'  => 'text',
					'label' => __( 'Text under the title', 'memorial-plugin' ),
				),
				'max_app' => array(
					'type'        => 'text',
					'label'       => __( 'Link that starts the MAX app (advanced)', 'memorial-plugin' ),
					'placeholder' => __( 'for example max://…', 'memorial-plugin' ),
					'help'        => __( 'Usually leave it empty — the MAX profile link is used. If you know a link that starts the installed app right away, enter it.', 'memorial-plugin' ),
				),
			),
		),
		'legal'      => array(
			'type'   => 'group',
			'label'  => __( 'Company details', 'memorial-plugin' ),
			'help'   => __( 'Shown on the “Contacts” page and in the footer. Empty fields are not shown.', 'memorial-plugin' ),
			'fields' => array(
				'name' => array(
					'type'        => 'text',
					'label'       => __( 'Name', 'memorial-plugin' ),
					'placeholder' => __( 'For example: Sole proprietor Ivan Ivanov', 'memorial-plugin' ),
				),
				'inn'  => array(
					'type'  => 'text',
					'label' => __( 'TIN (INN)', 'memorial-plugin' ),
				),
				'ogrn' => array(
					'type'  => 'text',
					'label' => __( 'OGRN / OGRNIP', 'memorial-plugin' ),
				),
			),
		),
		'founded'    => array(
			'type'  => 'number',
			'label' => __( 'Year founded', 'memorial-plugin' ),
			'help'  => __( 'The “more than N years” on the site is counted from it.', 'memorial-plugin' ),
		),
	);
}

/**
 * Home schema.
 */
function memorial_plugin_home_schema() {
	$show    = array(
		'type'  => 'checkbox',
		'label' => __( 'Show this block', 'memorial-plugin' ),
	);
	$eyebrow = array(
		'type'  => 'text',
		'label' => __( 'Eyebrow (small letters above the title)', 'memorial-plugin' ),
	);
	return array(
		'layout'   => array(
			'type'    => 'order',
			'label'   => __( 'Order of the blocks on the home page', 'memorial-plugin' ),
			'help'    => __( 'Drag a row (or a block card below by ⋮⋮) with the mouse or a finger, or press ↑ ↓, then “Save order”. The first screen is always at the top. Blocks marked “hidden” are not visible on the site — turn on “Show this block” in their settings below.', 'memorial-plugin' ),
			'options' => memorial_plugin_home_blocks(),
		),
		'hero'     => array(
			'type'   => 'group',
			'label'  => __( 'First screen', 'memorial-plugin' ),
			'fields' => array(
				'eyebrow'    => $eyebrow,
				'title'      => array(
					'type'  => 'text',
					'label' => __( 'Title — first line', 'memorial-plugin' ),
				),
				'title_em'   => array(
					'type'  => 'text',
					'label' => __( 'Title — second line (italic, accent colour)', 'memorial-plugin' ),
				),
				'lead'       => array(
					'type'  => 'textarea',
					'label' => __( 'Text under the title', 'memorial-plugin' ),
					'rows'  => 3,
				),
				'trust'      => array(
					'type'   => 'repeater',
					'label'  => __( 'Advantages (with laurel leaves)', 'memorial-plugin' ),
					'add'    => __( 'Add an item', 'memorial-plugin' ),
					'fields' => array(
						'text' => array(
							'type'  => 'text',
							'label' => __( 'Text', 'memorial-plugin' ),
						),
					),
				),
				'help_page'  => array(
					'type'  => 'page',
					'label' => __( '“What to do” link under the buttons', 'memorial-plugin' ),
					'help'  => __( 'A link under the “Call” and “Call me back” buttons: a person immediately sees where to click if someone close has just died. Empty — the page “What to do when someone dies” (found automatically).', 'memorial-plugin' ),
				),
				'help_text'  => array(
					'type'        => 'text',
					'label'       => __( 'Text of the “what to do” link', 'memorial-plugin' ),
					'placeholder' => __( 'What to do when someone dies', 'memorial-plugin' ),
				),
				'help_off'   => array(
					'type'  => 'checkbox',
					'label' => __( 'Do not show the “what to do” link', 'memorial-plugin' ),
				),
				'img'        => array(
					'type'  => 'image',
					'label' => __( 'Photo on the right (mourning hall — light, daylight)', 'memorial-plugin' ),
					'help'  => __( 'Shown in a rounded frame next to the text, without darkening.', 'memorial-plugin' ),
				),
				'tint'       => array(
					'type'    => 'select',
					'label'   => __( 'Photo tint (not used in the light theme 4.0)', 'memorial-plugin' ),
					'options' => array(
						'grad' => __( 'Gradient: lighter at the top, darker at the bottom', 'memorial-plugin' ),
						'flat' => __( 'Even over the whole photo', 'memorial-plugin' ),
						'off'  => __( 'Off — the photo without a tint', 'memorial-plugin' ),
					),
				),
				'tint_color' => array(
					'type'      => 'color',
					'label'     => __( 'Tint colour (not used in the light theme 4.0)', 'memorial-plugin' ),
					'scheme'    => 'dark',
					'effective' => true,
				),
				'mobile'     => array(
					'type'    => 'select',
					'label'   => __( 'On a phone', 'memorial-plugin' ),
					'options' => array(
						'bg'  => __( 'Text and the call button first, the photo below (recommended)', 'memorial-plugin' ),
						'top' => __( 'A low strip of the photo first, the text below', 'memorial-plugin' ),
					),
				),
				'tint_op'    => array(
					'type'        => 'number',
					'label'       => __( 'Tint strength, % (not used in the light theme 4.0)', 'memorial-plugin' ),
					'placeholder' => '75',
				),
			),
		),
		'services' => array(
			'type'   => 'group',
			'label'  => __( '“Our services” block', 'memorial-plugin' ),
			'help'   => __( 'Cards with links to the service pages. The photo is optional: when it is not chosen, the featured image of the page is used.', 'memorial-plugin' ),
			'fields' => array(
				'show'    => $show,
				'eyebrow' => $eyebrow,
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
				'items'   => array(
					'type'     => 'repeater',
					'label'    => __( 'Services', 'memorial-plugin' ),
					'add'      => __( 'Add a service', 'memorial-plugin' ),
					'collapse' => 'title',
					'fields'   => array(
						'title' => array(
							'type'  => 'text',
							'label' => __( 'Name', 'memorial-plugin' ),
						),
						'text'  => array(
							'type'  => 'textarea',
							'label' => __( 'Short description', 'memorial-plugin' ),
							'rows'  => 2,
						),
						'page'  => array(
							'type'  => 'page',
							'label' => __( 'Page', 'memorial-plugin' ),
						),
						'price' => array(
							'type'        => 'text',
							'label'       => __( 'Price (optional)', 'memorial-plugin' ),
							'placeholder' => __( 'for example: from 58,000 ₽', 'memorial-plugin' ),
						),
						'img'   => array(
							'type'  => 'image',
							'label' => __( 'Photo (optional)', 'memorial-plugin' ),
						),
					),
				),
			),
		),
		'feats'    => array(
			'type'   => 'group',
			'label'  => __( '“Why people trust us” block', 'memorial-plugin' ),
			'fields' => array(
				'show'    => $show,
				'eyebrow' => $eyebrow,
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
				'items'   => array(
					'type'     => 'repeater',
					'label'    => __( 'Items', 'memorial-plugin' ),
					'add'      => __( 'Add an item', 'memorial-plugin' ),
					'collapse' => 'title',
					'fields'   => array(
						'title' => array(
							'type'  => 'text',
							'label' => __( 'Title', 'memorial-plugin' ),
						),
						'text'  => array(
							'type'  => 'textarea',
							'label' => __( 'Text', 'memorial-plugin' ),
							'rows'  => 3,
						),
						'icon'  => array(
							'type'    => 'select',
							'label'   => __( 'Icon', 'memorial-plugin' ),
							'options' => array( 'auto' => __( 'Choose by the title', 'memorial-plugin' ) ) + memorial_plugin_feat_icons(),
						),
						'img'   => array(
							'type'  => 'image',
							'label' => __( 'Photo instead of the icon (optional)', 'memorial-plugin' ),
						),
					),
				),
			),
		),
		'catalog'  => array(
			'type'   => 'group',
			'label'  => __( '“Catalog” block', 'memorial-plugin' ),
			'help'   => __( 'The tiles are the sub-items of the “Funeral goods” item of the main menu (Appearance → Editor → Navigation): names and order are taken from there. The photo of a tile is the “Featured image” of the page the item leads to; if there is none — the photo of the first product of that category.', 'memorial-plugin' ),
			'fields' => array(
				'show'    => $show,
				'eyebrow' => $eyebrow,
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
			),
		),
		'prices'   => array(
			'type'   => 'group',
			'label'  => __( '“Service packages” block', 'memorial-plugin' ),
			'help'   => __( 'The packages themselves, their contents and prices — in “Memorial → Prices”.', 'memorial-plugin' ),
			'fields' => array(
				'show'    => $show,
				'eyebrow' => $eyebrow,
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
			),
		),
		'stats'    => array(
			'type'   => 'group',
			'label'  => __( 'Block with numbers', 'memorial-plugin' ),
			'fields' => array(
				'show'  => $show,
				'items' => array(
					'type'   => 'repeater',
					'label'  => __( 'Numbers', 'memorial-plugin' ),
					'add'    => __( 'Add a number', 'memorial-plugin' ),
					'inline' => true,
					'fields' => array(
						'num'   => array(
							'type'  => 'number',
							'label' => __( 'Number', 'memorial-plugin' ),
						),
						'label' => array(
							'type'  => 'text',
							'label' => __( 'Caption', 'memorial-plugin' ),
						),
					),
				),
			),
		),
		'about'    => array(
			'type'   => 'group',
			'label'  => __( '“About the company” block', 'memorial-plugin' ),
			'fields' => array(
				'show'    => $show,
				'eyebrow' => $eyebrow,
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
				'text'    => array(
					'type'  => 'textarea',
					'label' => __( 'Text', 'memorial-plugin' ),
					'rows'  => 6,
					'html'  => true,
					'help'  => __( 'An empty line — a new paragraph. You can use <b>, <a> and other simple markup.', 'memorial-plugin' ),
				),
			),
		),
		'reviews'  => array(
			'type'   => 'group',
			'label'  => __( '“Reviews” block (from Yandex Maps)', 'memorial-plugin' ),
			'fields' => array(
				'show'    => $show,
				'eyebrow' => $eyebrow,
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
				'text'    => array(
					'type'  => 'textarea',
					'label' => __( 'Text next to the reviews', 'memorial-plugin' ),
					'rows'  => 3,
				),
				'org'     => array(
					'type'  => 'text',
					'label' => __( 'Organisation on Yandex Maps — number or link', 'memorial-plugin' ),
					'help'  => __( 'For example 75717068753 or a link like https://yandex.ru/maps/org/…/75717068753/', 'memorial-plugin' ),
				),
			),
		),
		'contacts' => array(
			'type'   => 'group',
			'label'  => __( '“Contacts” block', 'memorial-plugin' ),
			'help'   => __( 'Phones, email and addresses are taken from the “Memorial → Contacts” page.', 'memorial-plugin' ),
			'fields' => array(
				'show'    => $show,
				'eyebrow' => $eyebrow,
				'title'   => array(
					'type'  => 'text',
					'label' => __( 'Title', 'memorial-plugin' ),
				),
			),
		),
	);
}

/*
=========================================================
 * Значения по умолчанию (то, что было на сайте до появления настроек)
 * =======================================================
 */

/**
 * Contacts defaults.
 */
function memorial_plugin_contacts_defaults() {
	// Data migration: contacts once edited in the Customizer (classic theme) are read from its theme mods as defaults.
	$m     = fn( $k, $d ) => get_theme_mod( 'mem_' . $k, $d );
	$addrs = array();
	foreach ( array(
		1 => __( '308000, Belgorod, 40 Studencheskaya St.', 'memorial-plugin' ),
		2 => '308024, г. Белгород, ул. Щорса, 21',
	) as $n => $addr ) {
		$a = $m( 'addr' . $n, $addr );
		if ( ! $a ) {
			continue;
		}
		$map     = $m( 'map' . $n, '' );
		$addrs[] = array(
			'addr'  => $a,
			'hours' => $m( 'hours', __( 'Daily from 9:00 to 18:00', 'memorial-plugin' ) ),
			'title' => '',
			'map'   => strpos( $map, 'text=' ) !== false ? '' : $map, // карту по тексту адреса строим сами.
		);
	}
	return array(
		'phones'     => array(
			array(
				'num'  => $m( 'phone', '+7 (905) 675-22-24' ),
				'note' => '',
			),
			array(
				'num'  => '+7 (4722) 37-16-58',
				'note' => '',
			),
			array(
				'num'  => '+7 (910) 737-16-58',
				'note' => '',
			),
		),
		'legal'      => array(
			'name' => '',
			'inn'  => '312311885837',
			'ogrn' => '306312324200048',
		),
		'emails'     => array( array( 'email' => $m( 'email', 'memorialbel@mail.ru' ) ) ),
		'addrs'      => $addrs,
		'night'      => __( 'We take calls round the clock', 'memorial-plugin' ),
		'messengers' => array(
			'max'      => MEMORIAL_PLUGIN_MAX_URL,
			'telegram' => '',
			'whatsapp' => '',
		),
		'call'       => array(
			'title'   => 'Перезвоним вам',
			'note'    => 'Оставьте номер — перезвоним в ближайшее время.',
			'email'   => '',
			'max_app' => '',
		),
		'founded'    => (int) $m( 'founded', 2003 ),
	);
}

/** Блоки главной, которые можно переставлять */
function memorial_plugin_home_blocks() {
	return array(
		'services' => __( 'Our services', 'memorial-plugin' ),
		'feats'    => __( 'Why people trust us', 'memorial-plugin' ),
		'catalog'  => __( 'Catalog', 'memorial-plugin' ),
		'prices'   => __( 'Service packages', 'memorial-plugin' ),
		'stats'    => __( 'Block with numbers', 'memorial-plugin' ),
		'about'    => __( 'About the company', 'memorial-plugin' ),
		'reviews'  => __( 'Reviews', 'memorial-plugin' ),
		'contacts' => __( 'Contacts', 'memorial-plugin' ),
	);
}

/* Тексты главной по умолчанию — спокойные и конкретные: что делать и чем поможем, без «качественных услуг» и «не фирма-однодневка» */
const MEMORIAL_PLUGIN_HERO_LEAD  = 'Если умер близкий человек — позвоните, подскажем, что делать. Прямо сейчас ничего решать не нужно.';
const MEMORIAL_PLUGIN_HERO_TRUST = array( array( 'text' => 'Звонки круглосуточно' ), array( 'text' => 'Свой траурный зал — бесплатно при заказе похорон' ), array( 'text' => 'Цены и состав пакетов — на сайте' ) );
const MEMORIAL_PLUGIN_ABOUT_TEXT = "Мы работаем в Белгороде с {founded} года. Приедем в любое время, поможем с документами, проведём прощание в своём траурном зале.\n\nПодскажем по шагам, что нужно сделать, — выяснять самим ничего не придётся. Венки делаем сами, договор и документы для выплат оформляем официально.";

/** Вторая кнопка первого экрана: [адрес, надпись] или null */
function memorial_plugin_home_help_link() {
	$h = memorial_plugin_home( 'hero' );
	if ( ! empty( $h['help_off'] ) ) {
		return null;
	}
	$id = (int) ( $h['help_page'] ?? 0 );
	if ( ! $id || get_post_status( $id ) !== 'publish' ) {
		$id = 0;
		foreach ( get_pages( array( 'post_status' => 'publish' ) ) as $p ) {
			if ( preg_match( '~что делать.*умер~ui', $p->post_title ) || strpos( $p->post_name, 'chto-delat' ) === 0 ) {
				$id = $p->ID;
				break; }
		}
	}
	if ( ! $id ) {
		return null;
	}
	$text = trim( $h['help_text'] ?? '' );
	return array( get_permalink( $id ), '' !== $text ? $text : __( 'What to do when someone dies', 'memorial-plugin' ) );
}

/**
 * Home defaults.
 */
function memorial_plugin_home_defaults() {
	$p = fn( ...$rows ) => array_map(
		fn( $r ) => array(
			'name'  => $r[0],
			'price' => $r[1],
			'key'   => preg_match( '/^(Гроб|Крест|Катафалк|Венок|Услуги по захоронению)/u', $r[0] ) ? 1 : 0,
		),
		$rows
	);
	return array(
		'layout'   => array_keys( memorial_plugin_home_blocks() ),
		'hero'     => array(
			'eyebrow'    => 'Ритуальная компания в Белгороде · с {founded} года',
			'title'      => 'Ритуальные услуги в Белгороде',
			'title_em'   => 'Поможем на каждом шаге',
			'lead'       => MEMORIAL_PLUGIN_HERO_LEAD,
			'trust'      => MEMORIAL_PLUGIN_HERO_TRUST,
			'help_page'  => '',
			'help_text'  => __( 'What to do when someone dies', 'memorial-plugin' ),
			'help_off'   => 0,
			'img'        => get_theme_mod( 'mem_hero_img', '' ),
			'tint'       => 'grad',
			'tint_color' => '',
			'tint_op'    => 75,
			'mobile'     => 'bg',
		),
		'services' => array(
			'show'    => 1,
			'eyebrow' => 'Чем мы поможем',
			'title'   => __( 'Our services', 'memorial-plugin' ),
			'items'   => array(
				array(
					'title' => 'Организация похорон',
					'text'  => 'Возьмём на себя документы, морг, транспорт и проведение похорон.',
					'page'  => memorial_plugin_guess_page( array( 'organizaciya-pohoron', 'organizacziya-pohoron', 'pomoshh-v-organizacii-pohoron' ) ),
					'price' => '',
					'img'   => '',
				),
				array(
					'title' => 'Кремация',
					'text'  => 'Доставим в крематорий Тулы или Воронежа, выдадим прах в урне в день кремирования.',
					'page'  => memorial_plugin_guess_page( array( 'kremacziya', 'kremaciya' ) ),
					'price' => 'от 58 000 ₽',
					'img'   => '',
				),
				array(
					'title' => 'Траурный зал',
					'text'  => 'Собственный зал для прощания и отпевания на ул. Студенческой, 40.',
					'page'  => memorial_plugin_guess_page( array( 'traurnyj-zal' ) ),
					'price' => '',
					'img'   => '',
				),
				array(
					'title' => 'Груз 200',
					'text'  => 'Перевозка умерших в другие города и регионы.',
					'page'  => memorial_plugin_guess_page( array( 'gruz-200', 'perevozka-umershih', 'perevozka-umershego' ) ),
					'price' => '',
					'img'   => '',
				),
				array(
					'title' => 'Благоустройство захоронений',
					'text'  => 'Памятники, оградки, укладка плитки — под ключ.',
					'page'  => memorial_plugin_guess_page( array( 'blagoustrojstvo-zahoroneniya', 'blagoustrojstvo' ) ),
					'price' => '',
					'img'   => '',
				),
				array(
					'title' => 'Ритуальные товары',
					'text'  => 'Венки собственного производства, гробы, кресты.',
					'page'  => memorial_plugin_guess_page( array( 'ritualnye-tovary' ) ),
					'price' => '',
					'img'   => '',
				),
			),
		),
		'feats'    => array(
			'show'    => 1,
			'eyebrow' => __( 'Why people trust us', 'memorial-plugin' ),
			'title'   => 'Своё, а не через посредников',
			'items'   => array(
				array(
					'title' => 'Собственный траурный зал',
					'text'  => 'Предоставляем зал для прощания и панихиды бесплатно — в спокойной и достойной обстановке.',
					'icon'  => 'candle',
					'img'   => '',
				),
				array(
					'title' => 'Собственное производство венков',
					'text'  => 'Изготавливаем венки и композиции сами: любой размер и цвет, качество под контролем, без наценок посредников.',
					'icon'  => 'flower',
					'img'   => '',
				),
				array(
					'title' => 'Собственное здание',
					'text'  => 'Более {years} лет работаем в своём здании, а не во временном офисе. Мы не фирма-однодневка.',
					'icon'  => 'building',
					'img'   => '',
				),
				array(
					'title' => 'Документы для компенсации',
					'text'  => 'Заключаем официальный договор и выдаём полный пакет документов для получения компенсации через военкомат.',
					'icon'  => 'doc',
					'img'   => '',
				),
			),
		),
		'catalog'  => array(
			'show'    => 1,
			'eyebrow' => __( 'Catalog', 'memorial-plugin' ),
			'title'   => 'Ритуальные товары',
		),
		'prices'   => array(
			'show'    => 1,
			'eyebrow' => 'Стоимость',
			'title'   => 'Пакеты ритуальных услуг',
			'badge'   => 'Чаще выбирают',
			'btn'     => 'Заказать',
			'items'   => array(
				array(
					'name'     => 'Эконом',
					'featured' => 0,
					'rows'     => $p(
						array( 'Гроб атласный', 5000 ),
						array( 'Крест из сосны', 2500 ),
						array( 'Табличка на крест', 700 ),
						array( 'Шёлковый рушник', 350 ),
						array( 'Подушка в гроб', 400 ),
						array( 'Тюль', 450 ),
						array( 'Саван х/б', 450 ),
						array( 'Церковный набор', 250 ),
						array( 'Стропы для опускания гроба', 600 ),
						array( 'Катафалк', 6000 ),
						array( 'Услуги по захоронению', 20000 )
					),
				),
				array(
					'name'     => 'Стандарт',
					'featured' => 1,
					'rows'     => $p(
						array( 'Гроб бархат', 7500 ),
						array( 'Крест полированный', 4800 ),
						array( 'Табличка на крест', 700 ),
						array( 'Рушник габардин', 450 ),
						array( 'Подушка в гроб', 400 ),
						array( 'Комплект в гроб', 2150 ),
						array( 'Саван атласный', 550 ),
						array( 'Церковный набор', 250 ),
						array( 'Стропы для опускания гроба', 600 ),
						array( 'Катафалк', 6000 ),
						array( 'Венок с лентой', 2500 ),
						array( 'Услуги по захоронению', 22000 )
					),
				),
				array(
					'name'     => 'Премиум',
					'featured' => 0,
					'rows'     => $p(
						array( 'Гроб лаковый', 30000 ),
						array( 'Крест дубовый', 7000 ),
						array( 'Табличка на крест', 700 ),
						array( 'Рушник габардин', 550 ),
						array( 'Подушка в гроб', 400 ),
						array( 'Комплект в гроб', 2950 ),
						array( 'Саван атласный', 550 ),
						array( 'Церковный набор', 250 ),
						array( 'Стропы для опускания гроба', 600 ),
						array( 'Венок с лентой', 3700 ),
						array( 'Катафалк', 6000 ),
						array( 'Услуги по захоронению', 29000 )
					),
				),
			),
		),
		'stats'    => array(
			'show'  => 1,
			'items' => array(
				array(
					'num'   => 5200,
					'label' => 'организованных похорон',
				),
				array(
					'num'   => 2850,
					'label' => 'установленных оградок',
				),
				array(
					'num'   => 1250,
					'label' => 'благоустроенных захоронений',
				),
			),
		),
		'about'    => array(
			'show'    => 1,
			'eyebrow' => __( 'About the company', 'memorial-plugin' ),
			'title'   => 'Более {years} лет рядом в трудную минуту',
			'text'    => MEMORIAL_PLUGIN_ABOUT_TEXT,
		),
		'reviews'  => array(
			'show'    => 1,
			'eyebrow' => __( 'Reviews', 'memorial-plugin' ),
			'title'   => 'Что о нас пишут',
			'text'    => 'Это настоящие отзывы наших клиентов на Яндекс Картах — мы не можем их редактировать или удалять. Будем благодарны, если вы тоже поделитесь впечатлением.',
			'org'     => '75717068753',
		),
		'contacts' => array(
			'show'    => 1,
			'eyebrow' => __( 'Contacts', 'memorial-plugin' ),
			'title'   => 'Мы на связи',
		),
	);
}

/*
=========================================================
 * Чтение настроек
 * =======================================================
 */

/** Сохранённые значения поверх значений по умолчанию. Списки берутся целиком — чтобы удалённый пункт не возвращался.
 *
 * @param mixed $def Def.
 * @param mixed $saved Saved.
 */
function memorial_plugin_merge( $def, $saved ) {
	if ( ! is_array( $saved ) ) {
		return $def;
	}
	foreach ( $def as $k => $v ) {
		if ( ! array_key_exists( $k, $saved ) ) {
			continue;
		}
		$def[ $k ] = ( is_array( $v ) && $v && ! array_is_list( $v ) && is_array( $saved[ $k ] ) )
			? memorial_plugin_merge( $v, $saved[ $k ] )
			: $saved[ $k ];
	}
	return $def;
}

/**
 * Settings.
 *
 * @param string $name  Name.
 * @param bool   $flush Forget the remembered value (after the option is written).
 */
function memorial_plugin_settings( $name, $flush = false ) {
	static $cache = array();
	if ( $flush ) {
		// The option was just written: the next read takes it from the base again
		// (the save answer of the settings screen used to show the value from before the save).
		unset( $cache[ $name ] );
		return array();
	}
	if ( ! isset( $cache[ $name ] ) ) {
		// Значения по умолчанию: memorial_plugin_<имя без mem_>_defaults; тема подставляет свою функцию фильтром
		// memorial_plugin_settings_defaults. Имена настроек в базе (mem_…) прежние — переименованы только функции.
		// Список страниц (memorial_plugin_admin_pages) здесь спрашивать нельзя: его обработчики сами читают настройки.
		$fn             = apply_filters( 'memorial_plugin_settings_defaults', 'memorial_plugin_' . preg_replace( '/^mem_/', '', $name ) . '_defaults', $name );
		$def            = is_callable( $fn ) ? call_user_func( $fn ) : array();
		$cache[ $name ] = memorial_plugin_merge( $def, get_option( $name, null ) );
	}
	return $cache[ $name ];
}

// Any write of a mem_… option (settings screen, import, code) — the next read is fresh.
foreach ( array( 'added_option', 'updated_option', 'deleted_option' ) as $memorial_plugin_hook ) {
	add_action(
		$memorial_plugin_hook,
		static function ( $option ) {
			if ( is_string( $option ) && str_starts_with( $option, 'mem_' ) ) {
				memorial_plugin_settings( $option, true );
			}
		}
	);
}
unset( $memorial_plugin_hook );

/**
 * Contacts.
 */
function memorial_plugin_contacts() {
	return memorial_plugin_settings( 'mem_contacts' ); }
/**
 * Night.
 */
function memorial_plugin_night() {
	return trim( (string) ( memorial_plugin_contacts()['night'] ?? '' ) ); }

/** ID первой найденной страницы по адресам-кандидатам (для настроек по умолчанию)
 *
 * @param array $paths Paths.
 */
function memorial_plugin_guess_page( array $paths ) {
	foreach ( $paths as $path ) {
		$p = get_page_by_path( $path );
		if ( ! $p ) {
			$ids = get_posts(
				array(
					'post_type'      => 'page',
					'name'           => basename( $path ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'post_status'    => array( 'publish', 'draft' ),
				)
			);
			$p   = $ids ? get_post( $ids[0] ) : null;
		}
		if ( $p ) {
			return $p->ID;
		}
	}
	return '';
}
/**
 * Home.
 *
 * @param mixed $section Section.
 */
function memorial_plugin_home( $section ) {
	return memorial_plugin_settings( 'mem_home' )[ $section ] ?? array(); }

/**
 * Phones.
 */
function memorial_plugin_phones() {
	return array_values( array_filter( memorial_plugin_contacts()['phones'], fn( $p ) => trim( $p['num'] ?? '' ) !== '' ) );
}
/**
 * Emails.
 */
function memorial_plugin_emails() {
	return array_values( array_filter( wp_list_pluck( memorial_plugin_contacts()['emails'], 'email' ) ) );
}
/** Номер организации на Яндекс Картах */
function memorial_plugin_yandex_org() {
	$raw = (string) ( memorial_plugin_home( 'reviews' )['org'] ?? '' );
	return preg_match( '~(\d{6,})/?\s*$~', trim( $raw ), $m ) || preg_match( '~/(\d{6,})(?:/|$|\?)~', $raw, $m ) ? $m[1] : '';
}

/** Реквизиты строкой: «ИП … · ИНН … · ОГРНИП …» */
function memorial_plugin_legal_parts() {
	$l   = memorial_plugin_contacts()['legal'] ?? array();
	$out = array();
	if ( trim( $l['name'] ?? '' ) ) {
		$out[] = trim( $l['name'] );
	}
	$inn = preg_replace( '/\D/', '', $l['inn'] ?? '' );
	if ( $inn ) {
		$out[] = rtrim( __( 'TIN ', 'memorial-plugin' ) ) . "\u{00A0}" . $inn; // A non-breaking space: the label never leaves its number.
	}
	$ogrn = preg_replace( '/\D/', '', $l['ogrn'] ?? '' );
	if ( $ogrn ) {
		$out[] = rtrim( strlen( $ogrn ) === 15 ? __( 'OGRNIP ', 'memorial-plugin' ) : __( 'OGRN ', 'memorial-plugin' ) ) . "\u{00A0}" . $ogrn;
	}
	return $out;
}

/** Ссылки мессенджеров: ['max' => url, 'telegram' => url, 'whatsapp' => url] (только заполненные) */
function memorial_plugin_messengers() {
	$m   = memorial_plugin_contacts()['messengers'] ?? array();
	$out = array();
	$max = trim( $m['max'] ?? '' );
	if ( $max ) {
		$out['max'] = ( strpos( $max, 'http' ) === 0 ? $max : 'https://' . ltrim( $max, '/' ) );
	}
	$tg = trim( $m['telegram'] ?? '' );
	if ( $tg ) {
		$out['telegram'] = preg_match( '~^https?://~', $tg ) ? $tg : 'https://t.me/' . ltrim( preg_replace( '~^(t\.me/|telegram\.me/)~', '', $tg ), '@' );
	}
	$wa = preg_replace( '/\D/', '', $m['whatsapp'] ?? '' );
	if ( 11 === strlen( $wa ) && '8' === $wa[0] ) {
		$wa = '7' . substr( $wa, 1 );
	}
	if ( strlen( $wa ) >= 10 ) {
		$out['whatsapp'] = 'https://wa.me/' . $wa;
	}
	return array_map( 'esc_url_raw', $out );
}

/**
 * Addrs.
 */
function memorial_plugin_addrs() {
	return array_values( array_filter( memorial_plugin_contacts()['addrs'], fn( $a ) => trim( $a['addr'] ?? '' ) !== '' ) );
}

/** Номер для ссылки tel:
 *
 * @param int $num Num.
 */
function memorial_plugin_tel_of( $num ) {
	return preg_replace( '/[^\d+]/', '', $num );
}

/**
 * {price:код} in a text → the price now: a code of «Цены → Отдельные цены» ({price:lenta}), kremaciya —
 * the cremation package, or the name of a package ({price:Эконом}). An unknown code shows nothing to
 * visitors and stays as it is for those who may change the prices — so it is noticed.
 *
 * @param string $s Text.
 * @return string
 */
function memorial_plugin_price_tokens( $s ) {
	if ( false === strpos( (string) $s, '{price:' ) || ! function_exists( 'memorial_plugin_price_value' ) ) {
		return (string) $s;
	}
	// A price that takes its value from a text with {price:…} of itself (a service whose price is
	// “{price:<that service>}”) would loop for ever: inside a substitution no other one is made.
	static $depth = 0;
	if ( $depth > 0 ) {
		return (string) preg_replace( '/\{price:[^{}]{1,80}\}/u', '', (string) $s );
	}
	++$depth;
	$out = (string) preg_replace_callback(
		'/\{price:([^{}]{1,80})\}/u',
		static function ( $m ) {
			$code = trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
			$v    = memorial_plugin_price_value( $code );
			if ( '' === $v ) {
				$v = memorial_plugin_price_value( '', $code ); // the name of a package.
			}
			if ( '' === $v ) {
				return function_exists( 'memorial_plugin_can_screen' ) && memorial_plugin_can_screen( 'mem_prices' ) ? $m[0] : '';
			}
			return $v;
		},
		(string) $s
	);
	--$depth;
	return $out;
}

/**
 * {price:…} written in a block (a heading, a paragraph, a setting shown by a block) — on the site.
 *
 * @param string $html Block HTML.
 * @return string
 */
function memorial_plugin_price_tokens_block( $html ) {
	if ( is_admin() || false === strpos( (string) $html, '{price:' ) ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'/\{price:[^{}<>]{1,80}\}/u',
		static fn( $m ) => esc_html( memorial_plugin_price_tokens( $m[0] ) ),
		(string) $html
	);
}
add_filter( 'render_block', 'memorial_plugin_price_tokens_block', 20 );

/** Подстановки в тексты: {years}, {founded}, {addr}, {phone}
 *
 * @param array $s Section data.
 */
function memorial_plugin_t( $s ) {
	$a = memorial_plugin_addrs();
	$s = memorial_plugin_price_tokens( (string) $s );
	return strtr(
		(string) $s,
		array(
			'{years}'   => memorial_plugin_years(),
			'{founded}' => (int) memorial_plugin_contacts()['founded'],
			'{addr}'    => $a ? $a[0]['addr'] : '',
			'{phone}'   => memorial_plugin_opt( 'phone' ),
		)
	);
}

/*
=========================================================
 * Карты
 * =======================================================
 */

/** Из того, что вставили в поле «Карта», достаём ссылку.
 *
 * @param mixed $raw Raw.
 */
function memorial_plugin_clean_map( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '~src=["\']([^"\']+)~i', $raw, $m ) ) {
		$raw = html_entity_decode( $m[1] );
	}
	if ( strpos( $raw, '//' ) === 0 ) {
		$raw = 'https:' . $raw;
	}
	return esc_url_raw( $raw, array( 'https', 'http' ) );
}

/** Ссылка для iframe. Любая ссылка Яндекс.Карт приводится к виджету; если не получилось — ищем по адресу.
 *
 * @param array $map Map.
 * @param mixed $addr Addr.
 */
function memorial_plugin_map_src( $map, $addr ) {
	$by_addr = 'https://yandex.ru/map-widget/v1/?text=' . rawurlencode( preg_replace( '/^\d{6},\s*/', '', $addr ) ) . '&z=16';
	if ( ! $map ) {
		return $by_addr;
	}
	$u     = wp_parse_url( $map );
	$host  = $u['host'] ?? '';
	$path  = $u['path'] ?? '';
	$query = $u['query'] ?? '';
	if ( strpos( $host, 'yandex.' ) !== false ) {
		if ( strpos( $path, '/map-widget/' ) !== false ) {
			return $map;
		}
		// Конструктор карт: api-maps.yandex.ru/services/constructor/1.0/js/?um=...
		if ( strpos( $host, 'api-maps' ) !== false && preg_match( '/(?:^|&)um=([^&]+)/', $query, $m ) ) {
			return 'https://yandex.ru/map-widget/v1/?um=' . $m[1] . '&source=constructor';
		}
		// Обычная ссылка yandex.ru/maps/...?ll=...&z=... — те же параметры понимает виджет.
		if ( strpos( $path, '/maps' ) === 0 && preg_match( '/(?:^|&)(ll|pt|oid|text|um|whatshere)/', $query ) ) {
			return 'https://yandex.ru/map-widget/v1/?' . $query;
		}
		return $by_addr; // короткие ссылки yandex.ru/maps/-/... в iframe не открываются.
	}
	if ( strpos( $host, 'google.' ) !== false && strpos( $path, '/maps/embed' ) !== false ) {
		return $map;
	}
	return $by_addr;
}

/** Короткий адрес для вкладки: «ул. Студенческая, 40»
 *
 * @param mixed $addr Addr.
 */
function memorial_plugin_short_addr( $addr ) {
	$s = preg_replace( '/^\d{6},\s*/u', '', $addr );
	$s = preg_replace( '/^(г\.|город)\s*[^,]+,\s*/u', '', $s );
	return trim( $s );
}

/*
=========================================================
 * Проверка данных при сохранении
 * =======================================================
 */

/**
 * Sanitize.
 *
 * @param mixed $schema Schema.
 * @param mixed $in In.
 */
function memorial_plugin_sanitize( $schema, $in ) {
	$in  = is_array( $in ) ? $in : array();
	$out = array();
	foreach ( $schema as $k => $f ) {
		$v = $in[ $k ] ?? null;
		switch ( $f['type'] ) {
			case 'group':
				$out[ $k ] = memorial_plugin_sanitize( $f['fields'], $v );
				break;
			case 'repeater':
				$rows = array();
				foreach ( is_array( $v ) ? $v : array() as $row ) {
					$r = memorial_plugin_sanitize( $f['fields'], $row );
					if ( ! memorial_plugin_row_empty( $r, $f['fields'] ) ) {
						// A stable id of the row: texts bound to it on pages (blocks/bindings.php) follow the row when the list is reordered.
						$id       = is_array( $row ) ? (string) ( $row['_id'] ?? '' ) : '';
						$r['_id'] = preg_match( '/^[a-z0-9]{6,32}$/', $id ) ? $id : memorial_plugin_row_id();
						$rows[]   = $r;
					}
				}
				$out[ $k ] = $rows;
				break;
			case 'checkbox':
				$out[ $k ] = empty( $v ) ? 0 : 1;
				break;
			case 'number':
				$v         = is_scalar( $v ) ? preg_replace( '/[^\d]/', '', (string) $v ) : '';
				$out[ $k ] = '' === $v ? '' : (int) $v;
				break;
			case 'page':
				$out[ $k ] = absint( $v ) ? absint( $v ) : '';
				break;
			case 'button': // an action of the screen (a test email): nothing to keep.
			case 'hidden': // a field of a switched-off feature: its saved value stays as it is.
				break;
			case 'emails': // addresses separated by commas.
				$out[ $k ] = implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', is_scalar( $v ) ? (string) $v : '' ) ) ), 'is_email' ) );
				break;
			case 'perms': // «Настройки → Разрешения» (core/permissions.php).
				$out[ $k ] = memorial_plugin_perm_clean( $v );
				break;
			case 'link': // a page of the site (its ID) or any address.
				$v         = is_scalar( $v ) ? trim( (string) $v ) : '';
				$out[ $k ] = ctype_digit( $v ) ? ( absint( $v ) ? (string) absint( $v ) : '' ) : esc_url_raw( $v );
				break;
			case 'order':
				$v         = array_values( array_intersect( is_array( $v ) ? array_map( 'strval', $v ) : array(), array_keys( $f['options'] ) ) );
				$out[ $k ] = array_values( array_unique( array_merge( $v, array_keys( $f['options'] ) ) ) );
				break;
			case 'color':
				$out[ $k ] = (string) sanitize_hex_color( (string) $v );
				break;
			case 'file':
				$out[ $k ] = esc_url_raw( (string) $v );
				break;
			case 'fontsel': // список шрифтов меняется при сохранении (можно добавить свой) — проверяем только формат.
				$out[ $k ] = sanitize_key( (string) $v );
				if ( '' === $out[ $k ] ) {
					$out[ $k ] = (string) array_key_first( $f['options'] );
				}
				break;
			case 'select':
				$out[ $k ] = is_scalar( $v ) && array_key_exists( (string) $v, $f['options'] ) ? (string) $v : (string) array_key_first( $f['options'] );
				break;
			case 'email':
				$out[ $k ] = sanitize_email( (string) $v );
				break;
			case 'image':
				$out[ $k ] = esc_url_raw( (string) $v );
				break;
			case 'map':
				$out[ $k ] = memorial_plugin_clean_map( $v );
				break;
			case 'code': // коды счётчиков: целиком сохраняет только администратор с правом на HTML.
				$out[ $k ] = current_user_can( 'unfiltered_html' ) ? trim( (string) $v ) : '';
				break;
			case 'textarea':
				$out[ $k ] = ! empty( $f['html'] ) ? wp_kses_post( (string) $v ) : sanitize_textarea_field( (string) $v );
				break;
			default:
				$out[ $k ] = sanitize_text_field( (string) $v );
		}
	}
	return $out;
}

/**
 * A new id for a row of a list.
 *
 * @return string
 */
function memorial_plugin_row_id() {
	return strtolower( wp_generate_password( 10, false, false ) );
}

/** Пустые строки списков не сохраняем (галочки не в счёт).
 *
 * @param mixed $r R.
 * @param array $fields Fields.
 */
function memorial_plugin_row_empty( $r, $fields ) {
	foreach ( $fields as $k => $f ) {
		if ( 'checkbox' === $f['type'] ) {
			continue;
		}
		$v = $r[ $k ] ?? '';
		if ( is_array( $v ) ? $v : trim( (string) $v ) !== '' ) {
			return false;
		}
	}
	return true;
}

/*
=========================================================
 * Страницы в админке
 * =======================================================
 */

/**
 * Home services schema.
 */
function memorial_plugin_home_services_schema() {
	$s = memorial_plugin_home_schema()['services'];
	return array(
		'services' => array(
			'type'   => 'group',
			'label'  => __( 'Services', 'memorial-plugin' ),
			'help'   => $s['help'] ?? '',
			'fields' => array( 'items' => $s['fields']['items'] ),
		),
	);
}

/**
 * Admin pages.
 */
function memorial_plugin_admin_pages() {
	// The texts of the home page are its content; this screen keeps only the service cards.
	$home = array(
		'slug'   => 'mem-home',
		'title'  => __( 'Services', 'memorial-plugin' ),
		'schema' => 'memorial_plugin_home_services_schema',
		'intro'  => __( 'Service cards with photos and prices — on the home page and in the “Our services” block on other pages. The texts of the home page are edited like any page: “Pages → Home”.', 'memorial-plugin' ),
	);
	return apply_filters(
		'memorial_plugin_admin_pages',
		array(
			'mem_contacts' => array(
				'slug'   => 'mem-contacts',
				'title'  => __( 'Contacts', 'memorial-plugin' ),
				'schema' => 'memorial_plugin_contacts_schema',
				'intro'  => __( 'Phones, email, addresses and opening hours. Changes appear at once in the header, the footer, on the home page and in the product cards.', 'memorial-plugin' ),
			),
			'mem_home'     => $home,
		)
	);
}

add_action(
	'admin_menu',
	function () {
		global $memorial_plugin_admin_hooks;
		$memorial_plugin_admin_hooks = array();
		$pages                       = memorial_plugin_admin_pages();
		add_menu_page( __( 'Site settings', 'memorial-plugin' ), __( 'Memorial', 'memorial-plugin' ), 'manage_options', 'mem-contacts', '__return_null', 'dashicons-admin-home', 3 );
		foreach ( $pages as $opt => $p ) {
			// 'hidden' — страница без своего пункта меню (открывается вкладкой другой страницы)
			// страница настроек — приложение на DataForm (admin/settings-form.php, src/settings)
			$hook                          = add_submenu_page(
				empty( $p['hidden'] ) ? 'mem-contacts' : '',
				$p['title'],
				$p['title'],
				memorial_plugin_screen_cap( $opt ), // «Настройки → Разрешения».
				$p['slug'],
				fn() => memorial_plugin_settings_container( $opt )
			);
			$memorial_plugin_admin_hooks[] = $hook;
			if ( $hook ) {
				add_action( 'load-' . $hook, fn() => add_action( 'admin_enqueue_scripts', 'memorial_plugin_settings_assets' ) );
			}
			// у страницы без пункта меню WordPress не знает заголовок окна — задаём сами.
			if ( ! empty( $p['hidden'] ) && $hook ) {
				add_action(
					'load-' . $hook,
					function () use ( $p ) {
						$GLOBALS['title'] = $p['h1'] ?? $p['title']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the admin page title of wp-admin.
					}
				);
			}
		}
	}
);

/* Порядок пунктов «Мемориал»: сначала то, чем пользуются каждый день, внизу — разовые настройки */
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( empty( $submenu['mem-contacts'] ) ) {
			return;
		}
		$order = array( 'memorial-plugin-leads', 'mem-contacts', 'mem-prices', 'mem-reviews', 'memorial-plugin-rewio', 'mem-home', 'mem-rest', 'mem-cem', 'mem-design', 'mem-metrika', 'mem-legal', 'mem-sq', 'mem-modules' );
		$items = $submenu['mem-contacts'];
		usort(
			$items,
			function ( $a, $b ) use ( $order ) {
				$ia = array_search( $a[2], $order, true );
				$ib = array_search( $b[2], $order, true );
				return ( false === $ia ? 99 : $ia ) <=> ( false === $ib ? 99 : $ib );
			}
		);
		$submenu['mem-contacts'] = array_values( $items ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reorders the plugin's own submenu.
	},
	9999
);

/** Вкладки над страницами одного раздела (например, «Отзывы»: сервисы · Rewio · из книги)
 *
 * @param mixed $tabs Tabs.
 * @param mixed $current Current.
 */
function memorial_plugin_admin_tabs( $tabs, $current ) {
	echo '<nav class="nav-tab-wrapper mem-tabs" style="margin:6px 0 14px">';
	foreach ( $tabs as $key => [$label, $url] ) {
		echo '<a class="nav-tab' . ( $key === $current ? ' nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
}

add_action(
	'admin_init',
	function () {
		foreach ( memorial_plugin_admin_pages() as $opt => $p ) {
			register_setting(
				$opt,
				$opt,
				array(
					'type'              => 'array',
					'sanitize_callback' => fn( $v ) => memorial_plugin_sanitize( call_user_func( $p['schema'] ), $v ),
					'show_in_rest'      => false,
				)
			);
		}
	}
);

/* Форма страниц настроек строится приложением на DataForm (admin/settings-form.php); классические формы удалены на этапе 4. */

/* Ссылки в верхней панели: быстрое редактирование с сайта */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'mem-settings',
				'title' => __( '✎ Texts and contacts', 'memorial-plugin' ),
				'href'  => admin_url( 'admin.php?page=' . ( is_front_page() ? 'mem-home' : 'mem-contacts' ) ),
			)
		);
	},
	80
);
