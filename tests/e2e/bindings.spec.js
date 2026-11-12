/**
 * Shared texts (Block Bindings source memorial-plugin/settings, blocks/bindings.php):
 * a bound paragraph shows the setting on the site, a change goes to the setting and to every page,
 * a stale version is refused, a wrong phone is refused, list rows have stable ids.
 */
const { test, expect } = require( '@playwright/test' );
const { login } = require( './helpers' );

const api = ( page, options ) =>
	page.evaluate(
		( o ) => window.wp.apiFetch( o ).catch( ( e ) => ( { error: e } ) ),
		options
	);

const bound = ( key ) =>
	`<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"memorial-plugin/settings","args":{"key":"${ key }"}}}}} -->\n<p></p>\n<!-- /wp:paragraph -->`;

test( 'общий текст: вывод, правка с проверкой версии и телефона', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=mem-contacts' );
	const key = 'mem_contacts:night';

	// Two pages show the same text.
	const pages = [];
	for ( const title of [ 'Bindings A', 'Bindings B' ] ) {
		const p = await api( page, {
			path: '/wp/v2/pages',
			method: 'POST',
			data: { title, status: 'publish', content: bound( key ) },
		} );
		pages.push( p );
	}

	const read = await api( page, {
		path: '/memorial-plugin/v1/texts?keys[]=' + encodeURIComponent( key ),
	} );
	expect( read.error ).toBeUndefined();
	const version = read.versions.mem_contacts;

	// Saved with the current version — both pages show the new text.
	const text = 'Night line check ' + Date.now();
	const saved = await api( page, {
		path: '/memorial-plugin/v1/texts',
		method: 'POST',
		data: {
			values: { [ key ]: text },
			versions: { mem_contacts: version },
		},
	} );
	expect( saved.error ).toBeUndefined();
	expect( saved.values[ key ] ).toBe( text );
	for ( const p of pages ) {
		await page.goto( p.link );
		await expect( page.locator( 'main' ) ).toContainText( text );
	}

	// The old version again — refused, nothing overwritten.
	await page.goto( '/wp-admin/admin.php?page=mem-contacts' );
	const stale = await api( page, {
		path: '/memorial-plugin/v1/texts',
		method: 'POST',
		data: {
			values: { [ key ]: 'stale' },
			versions: { mem_contacts: version },
		},
	} );
	expect( stale.error?.code ).toBe( 'memorial_plugin_texts_conflict' );

	// The settings screen with an old version is refused too.
	const screen = await api( page, {
		path: '/memorial-plugin/v1/settings/mem_contacts',
	} );
	const staleScreen = await api( page, {
		path: '/memorial-plugin/v1/settings/mem_contacts',
		method: 'POST',
		data: { value: screen.value, version: screen.version - 1 },
	} );
	expect( staleScreen.error?.code ).toBe(
		'memorial_plugin_settings_conflict'
	);

	// Saved with the current version — accepted; rows of lists get ids. A phone is checked on the server.
	const good = await api( page, {
		path: '/memorial-plugin/v1/settings/mem_contacts',
		method: 'POST',
		data: { value: screen.value, version: screen.version },
	} );
	expect( good.error ).toBeUndefined();
	const phones = good.value.phones || [];
	expect( phones.length ).toBeGreaterThan( 0 );
	expect( phones[ 0 ]._id ).toMatch( /^[a-z0-9]{6,32}$/ );
	const phoneKey = `mem_contacts:phones.${ phones[ 0 ]._id }.num`;
	const now = await api( page, {
		path:
			'/memorial-plugin/v1/texts?keys[]=' +
			encodeURIComponent( phoneKey ),
	} );
	expect( now.values[ phoneKey ] ).toBe( phones[ 0 ].num );
	const badPhone = await api( page, {
		path: '/memorial-plugin/v1/texts',
		method: 'POST',
		data: { values: { [ phoneKey ]: 'call me' }, versions: now.versions },
	} );
	expect( badPhone.error?.code ).toBe( 'memorial_plugin_texts_invalid' );

	// A key that does not exist is shown as nothing and cannot be written.
	const missing = await api( page, {
		path: '/memorial-plugin/v1/texts',
		method: 'POST',
		data: { values: { 'mem_contacts:phones.nosuchrow0.num': '+7 900' } },
	} );
	expect( missing.error?.code ).toBe( 'memorial_plugin_texts_invalid' );
} );

test( 'общий текст в редакторе: источник зарегистрирован и даёт правку', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	await page.waitForFunction(
		() =>
			!! window.wp?.blocks?.getBlockBindingsSource?.(
				'memorial-plugin/settings'
			)
	);
	const source = await page.evaluate( () => {
		const s = window.wp.blocks.getBlockBindingsSource(
			'memorial-plugin/settings'
		);
		return {
			label: s.label,
			canEdit: s.canUserEditValue?.( {} ),
			fields: ( s.getFieldsList?.( {} ) || [] ).length,
		};
	} );
	expect( source.canEdit ).toBe( true );
	expect( source.fields ).toBeGreaterThan( 5 );
} );

test( 'общий текст: правка в редакторе сохраняется в настройку вместе со страницей', async ( {
	page,
} ) => {
	await login( page );
	await page.goto( '/wp-admin/admin.php?page=mem-contacts' );
	const key = 'mem_contacts:night';
	const before = await api( page, {
		path: '/memorial-plugin/v1/texts?keys[]=' + encodeURIComponent( key ),
	} );
	const p = await api( page, {
		path: '/wp/v2/pages',
		method: 'POST',
		data: {
			title: 'Bindings editor',
			status: 'publish',
			content: bound( key ),
		},
	} );
	await page.goto( `/wp-admin/post.php?post=${ p.id }&action=edit` );
	await page.evaluate( () => {
		const pr = window.wp?.data?.dispatch( 'core/preferences' );
		pr?.set( 'core/edit-post', 'welcomeGuide', false );
		pr?.set( 'core', 'welcomeGuide', false );
	} );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const para = canvas.locator( '[data-type="core/paragraph"]' ).first();
	await expect( para ).toContainText( before.values[ key ] || '' );
	const added = ' edited ' + Date.now();
	await para.click();
	await page.keyboard.press( 'End' );
	await page.keyboard.type( added );
	await page
		.locator( '.editor-post-publish-button, .editor-post-save-draft' )
		.first()
		.click();
	await expect
		.poll(
			async () =>
				(
					await api( page, {
						path:
							'/memorial-plugin/v1/texts?keys[]=' +
							encodeURIComponent( key ),
					} )
				).values[ key ],
			{ timeout: 15000 }
		)
		.toContain( added.trim() );
} );
