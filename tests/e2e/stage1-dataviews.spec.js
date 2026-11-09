/**
 * Этап 1, часть 1: сборка DataViews + admin-ui работает на WordPress 7.1.x,
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
	expect(
		errors.filter( ( e ) => ! /mc\.yandex|yandex/i.test( e ) )
	).toEqual( [] );
} );
