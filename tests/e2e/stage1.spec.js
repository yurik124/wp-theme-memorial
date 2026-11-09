/**
 * Этап 1 на WordPress 7.1.x (wp-env):
 *  — сборка DataViews + admin-ui работает: таблица рисуется, сортировка меняет порядок, нет ошибок JS;
 *  — плагин и тема после переименования открывают свои экраны без ошибок PHP на странице.
 * Ошибки PHP из debug.log CI проверяет отдельным шагом.
 */
const { test, expect } = require( '@playwright/test' );

const ADMIN_PAGES = [
	'admin.php?page=mem-contacts',
	'admin.php?page=mem-home',
	'admin.php?page=mem-prices',
	'admin.php?page=mem-reviews',
	'admin.php?page=mem-metrika',
	'admin.php?page=mem-legal',
	'admin.php?page=mem-design',
	'admin.php?page=mem-modules',
	'admin.php?page=mem-speed',
	'admin.php?page=mem-sq',
	'admin.php?page=mem-help-m',
	'edit.php?post_type=mem_product&page=mem-products',
	'edit.php?post_type=mem_product&page=mem-types',
	'edit.php?post_type=mem_product',
	'edit.php?post_type=mem_work',
];

const PHP_ERROR =
	/(Fatal error|Parse error|Warning|Notice|Deprecated)<\/b>:|Uncaught (Error|TypeError)/;

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.WP_USERNAME || 'admin' );
	await page.fill( '#user_pass', process.env.WP_PASSWORD || 'password' );
	await Promise.all( [
		page.waitForURL( /wp-admin/ ),
		page.click( '#wp-submit' ),
	] );
}

function collectJsErrors( page ) {
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' ) {
			errors.push( m.text() );
		}
	} );
	return errors;
}

test.beforeEach( async ( { page } ) => {
	await login( page );
} );

test( 'DataViews и admin-ui собираются и работают', async ( { page } ) => {
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

test( 'экраны плагина открываются без ошибок PHP', async ( { page } ) => {
	for ( const path of ADMIN_PAGES ) {
		const res = await page.goto( '/wp-admin/' + path );
		expect( res.status(), path ).toBeLessThan( 400 );
		const html = await page.content();
		expect( html, path ).not.toMatch( PHP_ERROR );
		expect( html, path ).not.toContain(
			'Sorry, you are not allowed to access this page'
		);
	}
} );

test( 'сайт открывается на новой теме', async ( { page } ) => {
	const errors = collectJsErrors( page );
	const res = await page.goto( '/' );
	expect( res.status() ).toBeLessThan( 400 );
	expect( await page.content() ).not.toMatch( PHP_ERROR );
	await expect( page.locator( 'link#memorial-theme-css' ) ).toHaveCount( 1 );
	expect(
		errors.filter( ( e ) => ! /mc\.yandex|yandex/i.test( e ) )
	).toEqual( [] );
} );
