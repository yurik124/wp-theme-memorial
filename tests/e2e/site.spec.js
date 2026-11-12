/**
 * Главная открывается на новой теме без ошибок PHP и JS.
 */
const { test, expect } = require( '@playwright/test' );
const { PHP_ERROR, login, collectJsErrors } = require( './helpers' );

test( 'сайт открывается на новой теме', async ( { page } ) => {
	const errors = collectJsErrors( page );
	const res = await page.goto( '/' );
	expect( res.status() ).toBeLessThan( 400 );
	expect( await page.content() ).not.toMatch( PHP_ERROR );
	await expect( page.locator( 'link#memorial-theme-css' ) ).toHaveCount( 1 );
	// блоковая тема: шапка с «Навигацией», подвал, нижняя панель — из частей шаблона
	const nav = page.locator( '#header nav.wp-block-navigation' );
	await expect( nav ).toHaveCount( 1 );
	await expect( page.locator( '.footer .footer__copy' ) ).toHaveCount( 1 );
	await expect( page.locator( 'main#main' ) ).toHaveCount( 1 );
	expect(
		errors.filter( ( e ) => ! /mc\.yandex|yandex/i.test( e ) )
	).toEqual( [] );
} );

test( '«Оформление» перенесено в стили сайта', async ( { page } ) => {
	// В CI до включения темы задана схема «камень» и текст 110 % (см. ci.yml).
	await login( page );
	await page.goto( '/wp-admin/' ); // перенос выполняется в админке
	await page.goto( '/' );
	const vars = await page.evaluate( () => {
		const css = window.getComputedStyle( document.documentElement );
		const get = ( name ) => css.getPropertyValue( name ).trim();
		return {
			base: get( '--wp--preset--color--base' ),
			primary: get( '--wp--preset--color--primary' ),
			fs: get( '--fs' ),
		};
	} );
	expect( vars.base.toLowerCase() ).toBe( '#fafaf8' );
	expect( vars.primary.toLowerCase() ).toBe( '#56687a' );
	expect( vars.fs ).toBe( '1.1' );
} );

test( 'плавающие блоки встают в подвал сами (Block Hooks)', async ( {
	page,
} ) => {
	await page.goto( '/' );
	// Телефонная панель и окно «Перезвоните мне» — один раз на странице, после подвала.
	await expect( page.locator( '.mbar' ) ).toHaveCount( 1 );
	await expect( page.locator( 'dialog#callback' ) ).toHaveCount( 1 );
	await expect( page.locator( 'footer.footer ~ .mbar' ) ).toHaveCount( 1 );
} );

test( 'иконки — из реестра иконок WordPress (SVG Icon API)', async ( {
	page,
} ) => {
	await page.goto( '/' );
	const icon = page.locator( 'svg.ico--fill' ).first();
	await expect( icon ).toBeAttached();
	// Только залитые контуры: ни обводки, ни <use> на общий набор.
	await expect( icon.locator( 'path[d]' ).first() ).toBeAttached();
	await expect( page.locator( 'svg.ico use' ) ).toHaveCount( 0 );
} );

test( 'главная и «Контакты» — разделы в содержимом страниц', async ( {
	page,
} ) => {
	await login( page );
	// Перенос — при входе в админку; на этом экране есть wp.apiFetch.
	await page.goto( '/wp-admin/admin.php?page=memorial-plugin-convert' );
	const data = await page.evaluate( async () => {
		const settings = await window.wp.apiFetch( {
			path: '/wp/v2/settings',
		} );
		const front = await window.wp.apiFetch( {
			path: '/wp/v2/pages/' + settings.page_on_front + '?context=edit',
		} );
		const template = await window.wp.apiFetch( {
			path: '/wp/v2/templates/memorial-theme//front-page?context=edit',
		} );
		return {
			showOnFront: settings.show_on_front,
			content: front.content.raw,
			template: template.content.raw,
		};
	} );
	expect( data.showOnFront ).toBe( 'page' );
	// Разделы — в содержимом главной, шаблон только обрамляет его.
	expect( data.content ).toContain( 'wp:memorial-plugin/phone-button' );
	expect( data.content ).not.toContain( 'wp:post-content' );
	expect( data.template ).toContain( 'wp:post-content' );
	expect( data.template ).not.toContain( 'memorial-plugin/phone-button' );

	await page.goto( '/' );
	await expect( page.locator( 'section.hero h1' ) ).toBeVisible();
	await expect( page.locator( '.hero__cta .btn--tel' ) ).toBeAttached();
	await expect( page.locator( 'section#uslugi .svc' ) ).toBeAttached();
	expect( await page.content() ).not.toMatch( PHP_ERROR );

	await page.goto( '/kontakty/' );
	await expect( page.locator( '.kontakty__head h1' ) ).toBeVisible();
	await expect( page.locator( '.kgrid .kcard' ).first() ).toBeAttached();
	await expect( page.locator( '.kontakty__text' ) ).toContainText(
		'Contacts check text.'
	);
	expect( await page.content() ).not.toMatch( PHP_ERROR );
} );
