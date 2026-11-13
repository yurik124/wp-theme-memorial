/**
 * The product template is built from core blocks (title, text) and the small product blocks of the plugin.
 */
const { test, expect } = require( '@playwright/test' );
const { login } = require( './helpers' );

test( 'шаблон товара: заголовок ядра, части товара, текст и похожие товары', async ( {
	page,
} ) => {
	await login( page );
	await page.goto(
		'/wp-admin/edit.php?post_type=mem_product&page=mem-products'
	);
	const api = ( options ) =>
		page.evaluate( ( o ) => window.wp.apiFetch( o ), options );
	const type = await api( {
		path: '/wp/v2/mem_cat',
		method: 'POST',
		data: { name: 'Product template ' + Date.now() },
	} );
	const made = [];
	for ( const title of [ 'Template product A', 'Template product B' ] ) {
		made.push(
			await api( {
				path: '/wp/v2/mem_product',
				method: 'POST',
				data: {
					title,
					status: 'publish',
					content:
						'<!-- wp:paragraph --><p>Product description text.</p><!-- /wp:paragraph -->',
					excerpt: 'Short text of the product.',
					mem_cat: [ type.id ],
				},
			} )
		);
	}
	await page.goto( made[ 0 ].link );
	const main = page.locator( 'main' );
	await expect( main.locator( 'h1' ) ).toHaveCount( 1 );
	await expect( main.locator( 'h1.wp-block-post-title' ) ).toHaveText(
		'Template product A'
	);
	await expect( main.locator( '.product > .product__media' ) ).toBeVisible();
	await expect(
		main.locator( '.product > .product__info .product__price' )
	).toBeVisible();
	await expect( main.locator( '.product__info .about__text' ) ).toContainText(
		'Short text of the product.'
	);
	await expect(
		main.locator( '.product__cta [data-callback]' )
	).toBeVisible();
	await expect( main.locator( '.content.product__desc' ) ).toContainText(
		'Product description text.'
	);
	await expect( main.locator( '.related .products' ) ).toContainText(
		'Template product B'
	);
} );
