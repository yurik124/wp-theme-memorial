/**
 * Lists on DataViews + DataForm: call-back requests and guest book reviews.
 */
const { test, expect } = require( '@playwright/test' );
const { login, collectJsErrors } = require( './helpers' );

test( 'заявки: новая заявка видна в списке и отмечается', async ( {
	page,
	request,
} ) => {
	const errors = collectJsErrors( page );
	const phone = '+7 900 555-' + String( Date.now() ).slice( -2 ) + '-11';
	const res = await request.post( '/wp-json/mem/v1/callback', {
		data: {
			phone,
			name: 'Проверка e2e',
			consent: 1,
			elapsed: 5,
			page: '/',
		},
	} );
	expect( res.ok() ).toBeTruthy();

	await login( page );
	// старый адрес списка ведёт на новый экран
	await page.goto( '/wp-admin/edit.php?post_type=mem_lead' );
	await expect( page ).toHaveURL( /page=memorial-plugin-leads/ );
	const row = page.getByRole( 'row' ).filter( { hasText: phone } );
	await expect( row ).toBeVisible();
	await row.getByRole( 'button', { name: 'Edit' } ).click();
	const modal = page.getByRole( 'dialog' );
	await modal.getByLabel( 'Called back' ).check();
	await modal.getByRole( 'button', { name: 'Save' } ).click();
	await expect(
		page.locator( '.components-notice.is-success' )
	).toBeVisible();
	expect( errors ).toEqual( [] );
} );

test( 'книга отзывов: добавить отзыв', async ( { page } ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=memorial-plugin-bookrev' );
	await page.getByRole( 'button', { name: 'Add' } ).first().click();
	const modal = page.getByRole( 'dialog' );
	await modal.getByLabel( 'Author' ).fill( 'Елена В. (e2e)' );
	await modal
		.getByLabel( 'Review' )
		.fill( 'Спасибо за помощь. Проверка e2e.' );
	await modal.getByRole( 'button', { name: 'Save' } ).click();
	await expect(
		page.getByRole( 'row' ).filter( { hasText: 'Елена В. (e2e)' } )
	).toBeVisible();
	expect( errors ).toEqual( [] );
} );
