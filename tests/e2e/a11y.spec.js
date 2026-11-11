/**
 * Accessibility (WCAG 2.2 AA): automatic check with axe on the main kinds of pages, keyboard use of the menu.
 * Serious and critical problems fail the check; the rest are printed to the log.
 */
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const PAGES = [
	'/',
	'/blocks-check/',
	'/?s=памятник',
	'/catalog/',
	'/no-such-page-404/',
];

for ( const path of PAGES ) {
	test( `axe: ${ path }`, async ( { page } ) => {
		await page.goto( path );
		const res = await new AxeBuilder( { page } )
			.withTags( [
				'wcag2a',
				'wcag2aa',
				'wcag21a',
				'wcag21aa',
				'wcag22aa',
			] )
			// maps and widgets of other sites are not ours
			.exclude( 'iframe' )
			.analyze();
		const report = res.violations.map(
			( v ) =>
				`${ v.impact }: ${ v.id } — ${ v.help } (${ v.nodes.length }): ${ v.nodes
					.slice( 0, 3 )
					.map( ( n ) => n.target.join( ' ' ) )
					.join( ', ' ) }`
		);
		if ( report.length ) {
			// eslint-disable-next-line no-console
			console.log( `${ path }\n  ${ report.join( '\n  ' ) }` );
		}
		expect(
			res.violations
				.filter( ( v ) =>
					[ 'serious', 'critical' ].includes( v.impact )
				)
				.map( ( v ) => v.id )
		).toEqual( [] );
	} );
}

test( 'клавиатура: ссылка «к содержимому» и меню с бургера', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 390, height: 844 } );
	await page.goto( '/' );
	await page.keyboard.press( 'Tab' );
	// the skip link is the first stop
	await expect( page.locator( ':focus' ) ).toHaveAttribute( 'href', /#/ );
	await page.locator( '#burger' ).focus();
	await page.keyboard.press( 'Enter' );
	await expect( page.locator( '#burger' ) ).toHaveAttribute(
		'aria-expanded',
		'true'
	);
	await expect(
		page.locator( '#header .wp-block-navigation-item__content' ).first()
	).toBeVisible();
	await page.keyboard.press( 'Escape' );
	await expect( page.locator( '#burger' ) ).toHaveAttribute(
		'aria-expanded',
		'false'
	);
} );
