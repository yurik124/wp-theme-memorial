/**
 * Сборка DataViews + admin-ui работает на WordPress 7.1.x,
 * главная открывается на новой теме без ошибок PHP и JS.
 */
const { test, expect } = require( '@playwright/test' );
const { PHP_ERROR, login, collectJsErrors } = require( './helpers' );

test( 'DataViews и admin-ui собираются и работают', async ( { page } ) => {
	await login( page );
	const errors = collectJsErrors( page );
	await page.goto(
		'/wp-admin/tools.php?page=memorial-plugin-dataviews-check'
	);
	const table = page.locator( '#memorial-plugin-dataviews-check table' );
	await expect( table ).toBeVisible();
	await expect( table ).toContainText( 'Wreath' );
	await expect( table.locator( 'tbody tr' ) ).toHaveCount( 3 );
	expect( errors, 'ошибки JS на странице проверки' ).toEqual( [] );
} );

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

test( 'главная — разделы блоками, шаблон сохранён один раз', async ( {
	page,
} ) => {
	await login( page );
	// Перенос — при входе в админку; на этом экране есть wp.apiFetch.
	await page.goto( '/wp-admin/admin.php?page=memorial-plugin-convert' );
	const template = await page.evaluate( () =>
		window.wp.apiFetch( {
			path: '/wp/v2/templates/memorial-theme//front-page?context=edit',
		} )
	);
	expect( template.source ).toBe( 'custom' );
	expect( template.content.raw ).toContain( 'wp:memorial-plugin/phone-button' );
	expect( template.content.raw ).not.toContain( 'memorial-theme/home' );

	await page.goto( '/' );
	await expect( page.locator( 'section.hero h1' ) ).toBeVisible();
	await expect( page.locator( '.hero__cta .btn--tel' ) ).toBeAttached();
	await expect( page.locator( 'section#uslugi .svc' ) ).toBeAttached();
	expect( await page.content() ).not.toMatch( PHP_ERROR );
} );
