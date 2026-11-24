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
			page.locator( '.memorial-plugin-settings .components-card' ).first()
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
	const field = page.getByLabel( 'Calls outside working hours' );
	await field.fill( 'Звоните круглосуточно — проверка e2e' );
	await page.getByRole( 'button', { name: 'Save' } ).first().click();
	await expect(
		page.locator( '.components-notice.is-success' )
	).toBeVisible();
	await page.reload();
	await expect(
		page.getByLabel( 'Calls outside working hours' )
	).toHaveValue( 'Звоните круглосуточно — проверка e2e' );
} );

// Ввод по буквам: раньше после первой буквы форма собиралась заново — курсор уходил из поля,
// страница прыгала (fill() этого не ловит, он вставляет текст сразу).
test( 'Отдельные цены: новая строка — ввод по буквам без потери фокуса и прыжка', async ( {
	page,
} ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=mem-prices' );
	const card = page
		.locator( '.memorial-plugin-settings__card' )
		.filter( { hasText: 'Separate prices' } );
	await card.getByRole( 'button', { name: '+ Add a price' } ).click();
	const row = card.locator( '.memorial-plugin-settings__row' ).last();
	const name = row.getByRole( 'textbox', { name: /^Name/ } );
	await name.scrollIntoViewIfNeeded();
	await name.click();
	const before = await page.evaluate( () => window.scrollY );
	await name.pressSequentially( 'Доставка венков', { delay: 30 } );
	await expect( name ).toBeFocused();
	await expect( name ).toHaveValue( 'Доставка венков' );
	const price = row.getByRole( 'textbox', { name: /^Price/ } );
	await price.click();
	await price.pressSequentially( '500', { delay: 30 } );
	await expect( price ).toBeFocused();
	await expect( price ).toHaveValue( '500' );
	const after = await page.evaluate( () => window.scrollY );
	expect( Math.abs( after - before ) ).toBeLessThan( 5 );
	expect( errors ).toEqual( [] );
} );

test( 'Контакты: ввод по буквам в поле группы — фокус остаётся', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=mem-contacts' );
	const field = page.getByLabel( 'Calls outside working hours' );
	await field.fill( '' );
	await field.pressSequentially( 'Круглосуточно', { delay: 30 } );
	await expect( field ).toBeFocused();
	await expect( field ).toHaveValue( 'Круглосуточно' );
} );
