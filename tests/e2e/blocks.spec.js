/**
 * Блоки плагина выводятся без ошибок PHP и JS: страница /blocks-check/ со всеми блоками
 * создаётся в CI (memorial-plugin/tools/create-blocks-check-page.php). Данные — значения по умолчанию.
 */
const { test, expect } = require( '@playwright/test' );
const { PHP_ERROR, collectJsErrors } = require( './helpers' );

test( 'все блоки плагина выводятся', async ( { page } ) => {
	const errors = collectJsErrors( page );
	const res = await page.goto( '/blocks-check/' );
	expect( res.status() ).toBeLessThan( 400 );
	const html = await page.content();
	expect( html ).not.toMatch( PHP_ERROR );
	for ( const selector of [
		'.topbar',
		'.header__phone',
		'.footer__col',
		'.footer__copy',
		'.mbar',
		'section.hero',
		'.kgrid',
		'.product',
		'.nf__in',
	] ) {
		await expect(
			page.locator( selector ).first(),
			selector
		).toBeAttached();
	}
	expect(
		errors.filter( ( e ) => ! /mc\.yandex|yandex/i.test( e ) )
	).toEqual( [] );
} );
