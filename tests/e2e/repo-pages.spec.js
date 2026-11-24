/**
 * Pages from the repository (pages/repo-pages.php): a page file becomes a page when an administrator opens
 * the admin area; a new version of the file is shown on the screen and written by “Apply”; the journal
 * keeps what was done. The plugin folder is mounted into wp-env, so the test writes the file there.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );
const { login, collectJsErrors, PHP_ERROR } = require( './helpers' );

const FILE = path.resolve(
	__dirname,
	'../../memorial-plugin/content/pages/e2e-repo-test.html'
);

const write = ( version, text ) =>
	fs.writeFileSync(
		FILE,
		'<!-- memorial-page ' +
			JSON.stringify( {
				slug: 'e2e-repo-test',
				parent: '',
				title: 'E2E repository page',
				seo_title: 'E2E repository page',
				seo_description: 'A page written by the e2e test.',
				status: 'publish',
				menu: null,
				version,
			} ) +
			' -->\n<!-- wp:paragraph -->\n<p>' +
			text +
			'</p>\n<!-- /wp:paragraph -->\n'
	);

test.describe.configure( { mode: 'serial' } );

test.afterAll( () => {
	if ( fs.existsSync( FILE ) ) {
		fs.unlinkSync( FILE );
	}
} );

test( 'Страницы из репозитория: файл → страница, новая версия → «Применить», журнал', async ( {
	page,
} ) => {
	const errors = collectJsErrors( page );
	write( 1, 'First text from the repository.' );
	await login( page );
	// Opening the admin area runs the import of new files.
	await page.goto( '/wp-admin/' );
	await page.goto( '/?pagename=e2e-repo-test' );
	await expect( page.locator( 'body' ) ).toContainText(
		'First text from the repository.'
	);

	write( 2, 'Second text from the repository.' );
	await page.goto( '/wp-admin/admin.php?page=mem-repo-pages' );
	// The fingerprint changed: the screen itself already applied it on admin_init — or shows it to apply.
	const row = page.getByRole( 'row' ).filter( { hasText: '/e2e-repo-test/' } );
	await expect( row ).toBeVisible();
	const apply = page.getByRole( 'button', { name: /^Apply/ } );
	if ( await apply.isEnabled() ) {
		await apply.click();
		await expect(
			page.locator( '.components-notice.is-success' )
		).toBeVisible();
	}
	await expect( row ).toContainText( 'Up to date' );
	await expect( row ).toContainText( '2 / 2' );

	await page.getByRole( 'tab', { name: 'Journal' } ).click();
	await expect(
		page.getByRole( 'row' ).filter( { hasText: 'e2e-repo-test' } ).first()
	).toBeVisible();

	await page.goto( '/?pagename=e2e-repo-test' );
	const body = await page.content();
	expect( body ).toContain( 'Second text from the repository.' );
	expect( body ).not.toMatch( PHP_ERROR );
	expect( errors ).toEqual( [] );
} );
