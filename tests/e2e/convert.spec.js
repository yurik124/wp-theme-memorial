/**
 * Memorial → Convert to blocks: preview, convert and roll back the CI page /convert-check/
 * (memorial-plugin/tools/create-blocks-check-page.php).
 */
const { test, expect } = require( '@playwright/test' );
const { PHP_ERROR, login, collectJsErrors } = require( './helpers' );

async function rawContent( page ) {
	return page.evaluate( async () => {
		const res = await window.wp.apiFetch( {
			path: '/wp/v2/pages?slug=convert-check&context=edit&_fields=content',
		} );
		return res[ 0 ].content.raw;
	} );
}

test( 'shortcodes are converted to blocks and rolled back', async ( {
	page,
} ) => {
	const errors = collectJsErrors( page );
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=memorial-plugin-convert' );
	expect( await page.content() ).not.toMatch( PHP_ERROR );

	const row = page.getByRole( 'row' ).filter( { hasText: 'Convert check' } );
	await expect( row ).toBeVisible();
	await row.getByRole( 'button', { name: 'Preview and convert' } ).click();
	const modal = page.getByRole( 'dialog' );
	await expect( modal.locator( 'table.diff' ) ).toBeVisible();
	// SEO title: the shortcode becomes a SEOPress variable from the registry.
	await expect( modal.locator( 'table.diff' ) ).toContainText(
		'%%mem_years%%'
	);
	await modal.getByRole( 'button', { name: 'Convert', exact: true } ).click();
	await expect(
		page.locator( '.components-notice' ).getByText( '1 post converted.' )
	).toBeVisible();

	const converted = await rawContent( page );
	expect( converted ).toContain( '<!-- wp:memorial-plugin/cta' );
	expect( converted ).toContain( 'data-value="years"' );
	expect( converted ).not.toContain( '[mem_' );

	const front = await page.goto( '/convert-check/' );
	expect( front.status() ).toBeLessThan( 400 );
	await expect(
		page.locator( '.wp-block-memorial-plugin-cta' )
	).toContainText( 'Convert check heading' );

	await page.goto( '/wp-admin/admin.php?page=memorial-plugin-convert' );
	const again = page
		.getByRole( 'row' )
		.filter( { hasText: 'Convert check' } );
	await again.getByRole( 'button', { name: 'Actions' } ).click();
	await page.getByRole( 'menuitem', { name: 'Roll back' } ).click();
	await expect(
		page.locator( '.components-notice' ).getByText( 'is back as it was' )
	).toBeVisible();
	expect( await rawContent( page ) ).toContain( '[mem_cta title=' );

	expect( errors ).toEqual( [] );
} );
