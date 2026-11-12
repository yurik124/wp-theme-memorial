/**
 * Блоки плагина выводятся без ошибок PHP и JS: страница /blocks-check/ со всеми блоками
 * создаётся в CI (memorial-plugin/tools/create-blocks-check-page.php). Данные — значения по умолчанию.
 */
const { test, expect } = require( '@playwright/test' );
const { PHP_ERROR, login, collectJsErrors } = require( './helpers' );

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
		'.kgrid',
		'.product',
		'.nf__in',
		'.wp-block-memorial-plugin-cta',
	] ) {
		await expect(
			page.locator( selector ).first(),
			selector
		).toBeAttached();
	}
	// Inline value: the stored text is replaced with the current one.
	await expect(
		page.locator( '.memorial-plugin-value' ).first()
	).not.toHaveText( 'STALE' );
	expect(
		errors.filter( ( e ) => ! /mc\.yandex|yandex/i.test( e ) )
	).toEqual( [] );
} );

// The editor preview of every plugin block (REST block-renderer) accepts the attributes the editor adds:
// an extra CSS class and the style supports. Blocks registered after the block supports used to fail
// with “Error loading block” (className is not a valid property).
test( 'предпросмотр блоков в редакторе с классом и стилями', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/' );
	const failed = await page.evaluate( async () => {
		const types = await window.wp.apiFetch( {
			path: '/wp/v2/block-types/memorial-plugin?context=edit',
		} );
		const out = [];
		for ( const type of types ) {
			const attributes = { className: 'e2e-extra' };
			if ( type.supports?.color ) {
				attributes.backgroundColor = 'base';
				attributes.style = { spacing: { padding: { top: '1rem' } } };
			}
			try {
				await window.wp.apiFetch( {
					path:
						'/wp/v2/block-renderer/' + type.name + '?context=edit',
					method: 'POST',
					data: { attributes },
				} );
			} catch ( e ) {
				out.push( type.name + ': ' + e.message );
			}
		}
		return out;
	} );
	expect( failed ).toEqual( [] );
} );
