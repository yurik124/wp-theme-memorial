/**
 * Products and works on DataViews; their fields in the block editor sidebar.
 */
const { test, expect } = require( '@playwright/test' );
const { login, collectJsErrors } = require( './helpers' );

const api = ( page, options ) =>
	page.evaluate( ( o ) => window.wp.apiFetch( o ), options );

test( 'товары: новый товар, цена правится в списке', async ( { page } ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto(
		'/wp-admin/edit.php?post_type=mem_product&page=mem-products'
	);
	const name = 'E2E тип ' + Date.now();
	const type = await api( page, {
		path: '/wp/v2/mem_cat',
		method: 'POST',
		data: { name },
	} );
	await page.goto(
		'/wp-admin/edit.php?post_type=mem_product&page=mem-products&type=' +
			type.id
	);
	await page
		.getByRole( 'button', { name: 'Add product', exact: true } )
		.click();
	const modal = page.getByRole( 'dialog' );
	await expect( modal ).toBeVisible();
	await modal.getByLabel( 'Title', { exact: true } ).fill( 'Венок e2e' );
	await modal.getByLabel( 'Price, ₽' ).fill( '1500' );
	await modal.getByLabel( 'Show “from”' ).check();
	await modal.getByRole( 'button', { name: 'Save' } ).click();
	await expect( modal ).toBeHidden();
	const row = page.getByRole( 'row' ).filter( { hasText: 'Венок e2e' } );
	await expect( row ).toContainText( '1 500' );

	// the same value in the block editor fields (REST meta)
	const items = await api( page, {
		path: '/memorial-plugin/v1/catalog/products',
	} );
	const product = items.items.find( ( i ) => i.title === 'Венок e2e' );
	const post = await api( page, {
		path: '/wp/v2/mem_product/' + product.id + '?context=edit',
	} );
	expect( post.meta._mem_price ).toBe( 1500 );
	expect( post.meta._mem_price_from ).toBe( '1' );
	expect( errors ).toEqual( [] );
} );

test( 'товар и работа открываются в блочном редакторе с панелями полей', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=mem_work&page=mem-works' );
	const res = await api( page, {
		path: '/memorial-plugin/v1/catalog/works',
		method: 'POST',
		data: {},
	} );
	const work = res.items[ 0 ];
	await page.goto( work.edit );
	// no welcome guide over the editor
	await page.waitForFunction( () =>
		window.wp?.data?.select( 'core/preferences' )
	);
	await page.evaluate( () =>
		window.wp.data
			.dispatch( 'core/preferences' )
			.set( 'core/edit-post', 'welcomeGuide', false )
	);
	await page.reload();
	await expect(
		page.getByRole( 'button', { name: 'Photos, size and prices' } )
	).toBeVisible( { timeout: 30000 } );
	// the classic meta box is gone
	await expect( page.locator( '#mem_work' ) ).toHaveCount( 0 );
} );

test( 'работы: год и «в примерах» правятся в списке', async ( { page } ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto( '/wp-admin/edit.php?post_type=mem_work' );
	await expect( page ).toHaveURL( /page=mem-works/ );
	const res = await api( page, {
		path: '/memorial-plugin/v1/catalog/works',
		method: 'POST',
		data: {},
	} );
	const id = res.items[ 0 ].id;
	await api( page, {
		path: '/memorial-plugin/v1/catalog/works/' + id,
		method: 'PUT',
		data: { values: { title: 'Работа e2e ' + id } },
	} );
	await page.reload();
	const row = page
		.getByRole( 'row' )
		.filter( { hasText: 'Работа e2e ' + id } );
	await row.getByRole( 'button', { name: 'Edit' } ).click();
	const modal = page.getByRole( 'dialog' );
	await modal.getByLabel( 'Year' ).fill( '2024' );
	await modal.getByLabel( 'In examples' ).check();
	await modal.getByRole( 'button', { name: 'Save' } ).click();
	await expect( modal ).toBeHidden();
	await expect( row ).toContainText( '2024' );
	const post = await api( page, {
		path: '/wp/v2/mem_work/' + id + '?context=edit',
	} );
	expect( post.meta._mem_year ).toBe( 2024 );
	expect( post.meta._mem_example ).toBe( '1' );
	expect( errors ).toEqual( [] );
} );

test( 'типы и категории: новый тип, характеристика, категория', async ( {
	page,
} ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto(
		'/wp-admin/edit-tags.php?taxonomy=mem_cat&post_type=mem_product'
	);
	await expect( page ).toHaveURL( /page=mem-types/ );
	const name = 'Тип e2e ' + Date.now();
	await page.getByRole( 'button', { name: 'New type' } ).click();
	let modal = page.getByRole( 'dialog' );
	await modal.getByLabel( 'Name' ).fill( name );
	await modal.getByRole( 'button', { name: 'Add' } ).click();
	await expect(
		page.getByRole( 'tab', { name: new RegExp( name ) } )
	).toHaveAttribute( 'aria-selected', 'true' );

	await page.getByRole( 'button', { name: 'Add specification' } ).click();
	await page
		.locator( '.memorial-plugin-catalog__spec' )
		.last()
		.getByLabel( 'Name' )
		.fill( 'Высота' );
	await page.getByRole( 'button', { name: 'Save specifications' } ).click();
	await expect(
		page.locator( '.components-notice.is-success' )
	).toBeVisible();

	await page.getByRole( 'button', { name: 'Add category' } ).click();
	modal = page.getByRole( 'dialog' );
	await modal.getByLabel( 'Name' ).fill( 'Категория e2e' );
	await modal.getByRole( 'button', { name: 'Add' } ).click();
	await expect(
		page.getByRole( 'row' ).filter( { hasText: 'Категория e2e' } )
	).toBeVisible();

	// the new specification is a column of the products of the type
	await page.getByRole( 'link', { name: /^Products “/ } ).click();
	await expect(
		page.getByRole( 'tab', { name: new RegExp( name ) } )
	).toHaveAttribute( 'aria-selected', 'true' );
	expect( errors ).toEqual( [] );
} );

test( 'что ищут на сайте: запрос из поиска виден в списке', async ( {
	page,
	request,
} ) => {
	const q = 'проверка поиска e2e';
	const res = await request.post( '/wp-admin/admin-ajax.php', {
		form: { action: 'mem_sq', q, n: '0', w: 'faq' },
	} );
	expect( res.status() ).toBeLessThan( 400 );
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=mem-sq' );
	await expect(
		page.getByRole( 'row' ).filter( { hasText: q } )
	).toBeVisible();
	expect( errors ).toEqual( [] );
} );
