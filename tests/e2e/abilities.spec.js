/**
 * Abilities API and Command Palette (stage 5).
 */
const { test, expect } = require( '@playwright/test' );
const { login, collectJsErrors } = require( './helpers' );

// errors of apiFetch come back as their message (otherwise Playwright shows only “Object”)
const api = ( page, options ) =>
	page.evaluate(
		( o ) =>
			window.wp.apiFetch( o ).catch( ( e ) => {
				throw new Error( JSON.stringify( e ) );
			} ),
		options
	);

test( 'способности плагина зарегистрированы и выполняются', async ( {
	page,
} ) => {
	await login( page );
	await page.goto(
		'/wp-admin/edit.php?post_type=mem_product&page=mem-products'
	);
	const list = await api( page, {
		path: '/wp-abilities/v1/abilities?category=memorial&per_page=100',
	} );
	const names = list.map( ( a ) => a.name );
	for ( const n of [
		'memorial-plugin/list-leads',
		'memorial-plugin/set-lead-done',
		'memorial-plugin/list-products',
		'memorial-plugin/update-product',
		'memorial-plugin/change-prices',
		'memorial-plugin/set-visibility',
		'memorial-plugin/create-products',
		'memorial-plugin/export-catalog',
		'memorial-plugin/convert-content',
		'memorial-plugin/rollback-conversion',
		'memorial-plugin/search-stats',
		'memorial-plugin/clear-search-stats',
	] ) {
		expect( names ).toContain( n );
	}
	const csv = await api( page, {
		path: '/wp-abilities/v1/abilities/memorial-plugin/export-catalog/run',
	} );
	expect( csv.filename ).toMatch( /^catalog-\d{4}-\d\d-\d\d\.csv$/ );
	expect( csv.csv ).toContain( 'Title' );

	// a product type and a product through abilities, then the price through change-prices
	const type = await api( page, {
		path: '/wp/v2/mem_cat',
		method: 'POST',
		data: { name: 'Abilities e2e ' + Date.now() },
	} );
	const [ product ] = await api( page, {
		path: '/wp-abilities/v1/abilities/memorial-plugin/create-products/run',
		method: 'POST',
		data: { input: { type: type.id } },
	} );
	await api( page, {
		path: '/wp-abilities/v1/abilities/memorial-plugin/update-product/run',
		method: 'POST',
		data: { input: { id: product.id, price: 1000 } },
	} );
	const [ changed ] = await api( page, {
		path: '/wp-abilities/v1/abilities/memorial-plugin/change-prices/run',
		method: 'POST',
		data: {
			input: { ids: [ product.id ], mode: 'pct', value: 10, round: 10 },
		},
	} );
	expect( changed.price ).toBe( 1100 );
} );

test( 'способности проверяют права: без входа — отказ', async ( {
	request,
} ) => {
	const res = await request.post(
		'/wp-json/wp-abilities/v1/abilities/memorial-plugin/update-product/run',
		{ data: { input: { id: 1, price: 1 } } }
	);
	expect( [ 401, 403 ] ).toContain( res.status() );
} );

test( 'палитра команд: экран плагина открывается по Ctrl+K', async ( {
	page,
} ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto( '/wp-admin/index.php' );
	await page.waitForFunction( () =>
		window.wp?.data
			?.select( 'core/commands' )
			?.getCommands()
			.some( ( c ) => c.name === 'memorial-plugin/open-products' )
	);
	await page.keyboard.press( 'ControlOrMeta+k' );
	const box = page
		.getByRole( 'combobox' )
		.or( page.getByPlaceholder( /search|поиск/i ) )
		.first();
	await box.fill( 'Types and categories' );
	await page
		.getByRole( 'option', { name: /Types and categories/ } )
		.first()
		.click();
	await expect( page ).toHaveURL( /page=mem-types/ );
	expect( errors ).toEqual( [] );
} );
