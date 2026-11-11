/**
 * Settings screens on DataForm: every page opens with its fields, a change is saved through REST
 * and stays after reload; JS errors are not allowed.
 */
const { test, expect } = require( '@playwright/test' );
const { login, collectJsErrors } = require( './helpers' );

const PAGES = [
	'mem-contacts',
	'mem-home',
	'mem-prices',
	'mem-reviews',
	'mem-rest',
	'mem-cem',
	'mem-metrika',
	'mem-legal',
	'mem-modules',
];

for ( const slug of PAGES ) {
	test( `настройки ${ slug } — форма DataForm`, async ( { page } ) => {
		const errors = collectJsErrors( page );
		await login( page );
		await page.goto( '/wp-admin/admin.php?page=' + slug );
		await expect(
			page.locator( '.memorial-plugin-settings' )
		).toBeVisible();
		await expect(
			page
				.locator( '.memorial-plugin-settings .components-panel__body' )
				.first()
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Save' } ).first()
		).toBeAttached();
		expect( errors ).toEqual( [] );
	} );
}

test( 'Контакты: изменение сохраняется', async ( { page } ) => {
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=mem-contacts' );
	const field = page.getByLabel( 'Звонки в нерабочее время' );
	await field.fill( 'Звоните круглосуточно — проверка e2e' );
	await page.getByRole( 'button', { name: 'Save' } ).first().click();
	await expect(
		page.locator( '.components-notice.is-success' )
	).toBeVisible();
	await page.reload();
	await expect( page.getByLabel( 'Звонки в нерабочее время' ) ).toHaveValue(
		'Звоните круглосуточно — проверка e2e'
	);
} );
