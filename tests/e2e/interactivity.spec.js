/**
 * Stage 6: the cemeteries block on the Interactivity API; the plugin’s styles in another block theme.
 */
const { test, expect } = require( '@playwright/test' );
const { login, collectJsErrors } = require( './helpers' );

const api = ( page, options ) =>
	page.evaluate(
		( o ) =>
			window.wp.apiFetch( o ).catch( ( e ) => {
				throw new Error( JSON.stringify( e ) );
			} ),
		options
	);

test( 'кладбища: фильтр по статусу работает без перезагрузки', async ( {
	page,
} ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=mem-cem' );
	await api( page, {
		path: '/memorial-plugin/v1/settings/mem_cem',
		method: 'POST',
		data: {
			value: {
				items: [
					{
						name: 'Открытое e2e',
						status: 'open',
						addr: 'ул. Первая, 1',
					},
					{
						name: 'Закрытое e2e',
						status: 'closed',
						addr: 'ул. Вторая, 2',
					},
				],
			},
		},
	} );
	const pageObj = await api( page, {
		path: '/wp/v2/pages',
		method: 'POST',
		data: {
			title: 'Кладбища e2e',
			status: 'publish',
			content: '<!-- wp:memorial-plugin/cemeteries /-->',
		},
	} );
	await page.goto( pageObj.link );
	const open = page
		.locator( '.mem-cem__item' )
		.filter( { hasText: 'Открытое e2e' } );
	const closed = page
		.locator( '.mem-cem__item' )
		.filter( { hasText: 'Закрытое e2e' } );
	await expect( open ).toBeVisible();
	await expect( closed ).toBeVisible();
	const chips = page.locator( '.mem-cem .chip' );
	await chips.nth( 2 ).click();
	await expect( chips.nth( 2 ) ).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( chips.nth( 0 ) ).toHaveAttribute( 'aria-pressed', 'false' );
	await expect( page.locator( '.mem-cem__item:visible' ) ).toHaveCount( 1 );
	await chips.nth( 0 ).click();
	await expect( page.locator( '.mem-cem__item:visible' ) ).toHaveCount( 2 );
	expect(
		errors.filter( ( e ) => ! /404|Failed to load resource/.test( e ) )
	).toEqual( [] );
} );

test( 'стили плагина подключаются вместе с блоком', async ( { page } ) => {
	await login( page );
	await page.goto( '/' );
	// in the Memorial theme the header has blocks of the plugin, so its styles are on every page
	await expect( page.locator( 'link#memorial-plugin-css' ) ).toHaveCount( 1 );
	// the colours of the plugin are the presets of the theme
	const usesPreset = await page.evaluate( () =>
		[ ...document.styleSheets ].some( ( s ) => {
			try {
				return (
					/memorial-plugin\/assets\/css\/blocks/.test(
						s.href || ''
					) &&
					[ ...s.cssRules ].some( ( r ) =>
						r.cssText.includes( '--wp--preset--color--primary' )
					)
				);
			} catch ( e ) {
				return false;
			}
		} )
	);
	expect( usesPreset ).toBe( true );
} );
