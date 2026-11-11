/**
 * Экраны плагина открываются без ошибок PHP.
 * Каждый экран — отдельный тест: при разбиении на части они распределяются поровну,
 * а при падении видно, какой именно экран сломан.
 */
const { test, expect } = require( '@playwright/test' );
const { PHP_ERROR, login } = require( './helpers' );

const ADMIN_PAGES = [
	'admin.php?page=mem-contacts',
	'admin.php?page=mem-home',
	'admin.php?page=mem-prices',
	'admin.php?page=mem-reviews',
	'admin.php?page=mem-metrika',
	'admin.php?page=mem-legal',
	'admin.php?page=mem-modules',
	'admin.php?page=mem-sq',
	'admin.php?page=mem-help-m',
	'edit.php?post_type=mem_product&page=mem-products',
	'edit.php?post_type=mem_product&page=mem-types',
	'edit.php?post_type=mem_product&page=mem-product-pages',
	'edit.php?post_type=mem_product',
	'edit.php?post_type=mem_work',
	'edit.php?post_type=mem_work&page=mem-works',
	'edit.php?post_type=mem_work&page=mem-work-types',
];

for ( const path of ADMIN_PAGES ) {
	test( `экран ${ path }`, async ( { page } ) => {
		await login( page );
		const res = await page.goto( '/wp-admin/' + path );
		expect( res.status(), path ).toBeLessThan( 400 );
		const html = await page.content();
		expect( html, path ).not.toMatch( PHP_ERROR );
		expect( html, path ).not.toContain(
			'Sorry, you are not allowed to access this page'
		);
	} );
}
