/**
 * Сценарии для разбора жалоб: бургер на телефоне, пункт меню на ПК, страница «Вопрос–ответ»,
 * страницы в редакторе, экраны админки. PHASE — classic (старая тема и плагин) или block.
 * Снимки — в PROBE_DIR/<phase>/, состояние — в PROBE_DIR/<phase>/log.json.
 */
const fs = require( 'fs' );
const { test } = require( '@playwright/test' );

const PHASE = process.env.PHASE || 'block';
const DIR = ( process.env.PROBE_DIR || '../../probe' ) + '/' + PHASE;
fs.mkdirSync( DIR, { recursive: true } );
const PAGES = JSON.parse( fs.readFileSync( process.env.PAGES_FILE, 'utf8' ) );
const LOG = {};
const log = ( k, v ) => {
	LOG[ k ] = v;
	fs.writeFileSync( DIR + '/log.json', JSON.stringify( LOG, null, 2 ) );
};
const shot = ( page, name, opts = {} ) =>
	page.screenshot( { path: `${ DIR }/${ name }.png`, ...opts } );
const errorsOf = ( page ) => {
	const errs = [];
	page.on( 'pageerror', ( e ) => errs.push( 'pageerror: ' + e.message ) );
	page.on( 'console', ( m ) => m.type() === 'error' && errs.push( 'console: ' + m.text() ) );
	return errs;
};
const NO_FLOAT = '.consent,.cookie,#totop{display:none !important}';

test.describe( 'телефон', () => {
	test.use( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
	test( 'бургер', async ( { page } ) => {
		const errs = errorsOf( page );
		await page.goto( '/', { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: NO_FLOAT } );
		await shot( page, 'phone-home' );
		const burger = page.locator( '#burger, .burger' ).first();
		const before = await burger.evaluate( ( b ) => ( {
			html: b.outerHTML,
			rect: b.getBoundingClientRect().toJSON(),
			top: document.elementFromPoint(
				b.getBoundingClientRect().x + b.getBoundingClientRect().width / 2,
				b.getBoundingClientRect().y + b.getBoundingClientRect().height / 2
			)?.outerHTML.slice( 0, 300 ),
			header: document.getElementById( 'header' )?.className,
		} ) ).catch( ( e ) => ( { error: e.message } ) );
		await burger.tap().catch( ( e ) => errs.push( 'tap: ' + e.message ) );
		await page.waitForTimeout( 800 );
		const after = await page.evaluate( () => {
			const nav = document.querySelector( '#header .wp-block-navigation, #nav' );
			const r = nav?.getBoundingClientRect();
			return {
				expanded: document.querySelector( '#burger' )?.getAttribute( 'aria-expanded' ),
				navClass: nav?.className,
				navRect: r && r.toJSON(),
				navDisplay: nav && getComputedStyle( nav ).display,
				navVisibility: nav && getComputedStyle( nav ).visibility,
				bodyClass: document.body.className,
				scripts: [ ...document.scripts ].map( ( s ) => s.src ).filter( Boolean ),
			};
		} );
		await shot( page, 'phone-burger-open' );
		log( 'burger', { before, after, errs } );
	} );
	test( 'вопрос-ответ телефон', async ( { page } ) => {
		const p = PAGES.find( ( x ) => /вопрос/i.test( x.post_title ) );
		if ( ! p ) return log( 'faq-phone', 'нет страницы' );
		await page.goto( p.url, { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: NO_FLOAT } );
		await shot( page, 'phone-faq', { fullPage: true } );
	} );
} );

test.describe( 'компьютер', () => {
	test.use( { viewport: { width: 1280, height: 900 } } );
	test( 'меню: Кладбища', async ( { page } ) => {
		const errs = errorsOf( page );
		await page.goto( '/', { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: NO_FLOAT } );
		await shot( page, 'pc-home', { clip: { x: 0, y: 0, width: 1280, height: 400 } } );
		const item = page.locator( '#header a, header a' ).filter( { hasText: /^\s*Кладбища/ } ).first();
		const info = await item.evaluate( ( a ) => ( {
			href: a.getAttribute( 'href' ),
			li: a.closest( 'li' )?.outerHTML.slice( 0, 1500 ),
		} ) ).catch( ( e ) => ( { error: e.message } ) );
		await item.hover().catch( () => {} );
		await page.waitForTimeout( 500 );
		await shot( page, 'pc-menu-hover', { clip: { x: 0, y: 0, width: 1280, height: 600 } } );
		await item.click().catch( ( e ) => errs.push( 'click: ' + e.message ) );
		await page.waitForTimeout( 1500 );
		await page.waitForLoadState( 'networkidle' ).catch( () => {} );
		await shot( page, 'pc-menu-after-click' );
		const after = await page.evaluate( () => ( {
			url: location.href,
			header: document.getElementById( 'header' )?.className,
			open: [ ...document.querySelectorAll( '#header li.is-open, #header .is-menu-open, #header [aria-expanded="true"]' ) ].map( ( e ) => e.outerHTML.slice( 0, 200 ) ),
			focus: document.activeElement?.outerHTML.slice( 0, 200 ),
		} ) );
		await page.mouse.move( 640, 800 );
		await page.waitForTimeout( 500 );
		await shot( page, 'pc-menu-after-click-mouseaway' );
		log( 'menu', { info, after, errs } );
	} );
	test( 'вопрос-ответ компьютер', async ( { page } ) => {
		const p = PAGES.find( ( x ) => /вопрос/i.test( x.post_title ) );
		if ( ! p ) return;
		await page.goto( p.url, { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: NO_FLOAT } );
		await shot( page, 'pc-faq', { fullPage: true } );
	} );
} );

test.describe( 'админка', () => {
	test.use( { viewport: { width: 1440, height: 1000 } } );
	test( 'редактор и экраны', async ( { page } ) => {
		test.setTimeout( 600_000 );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const editorErrs = {};
		const pick = [
			PAGES.find( ( x ) => /вопрос/i.test( x.post_title ) ),
			PAGES.find( ( x ) => /благоустр/i.test( x.post_title ) ),
			PAGES.find( ( x ) => /контакт/i.test( x.post_title ) ),
			PAGES.find( ( x ) => /кладбищ/i.test( x.post_title ) ),
		].filter( Boolean );
		let first = true;
		for ( const p of pick ) {
			const errs = errorsOf( page );
			await page.goto( '/wp-admin/post.php?action=edit&post=' + p.ID );
			if ( first ) {
				first = false;
				await page.waitForFunction( () => window.wp?.data?.dispatch?.( 'core/preferences' ), null, { timeout: 30000 } ).catch( () => {} );
				await page.evaluate( () => {
					const pr = window.wp?.data?.dispatch( 'core/preferences' );
					pr?.set( 'core/edit-post', 'welcomeGuide', false );
					pr?.set( 'core', 'welcomeGuide', false );
				} ).catch( () => {} );
				await page.reload();
			}
			await page.waitForTimeout( 8000 );
			await shot( page, 'editor-' + p.post_name );
			editorErrs[ p.post_name ] = errs;
		}
		log( 'editor', editorErrs );
		const screens = [
			'admin.php?page=mem-contacts',
			'admin.php?page=mem-prices',
			'admin.php?page=mem-reviews',
			'admin.php?page=mem-modules',
			'admin.php?page=memorial-plugin-leads',
			'edit.php?post_type=mem_lead',
			'edit.php?post_type=mem_product&page=mem-products',
			'edit.php?post_type=mem_product&page=mem-types',
			'edit.php?post_type=mem_work&page=mem-works',
			'edit.php?post_type=mem_work',
			'admin.php?page=mem-sq',
			'index.php',
		];
		const DOM = {};
		for ( const s of screens ) {
			await page.goto( '/wp-admin/' + s ).catch( () => {} );
			await page.waitForTimeout( 3000 );
			const name = 'admin-' + s.replace( /[^a-z0-9]+/gi, '-' ).replace( /-+$/, '' );
			await shot( page, name, { fullPage: true } );
			// классы элементов приложения — для оформления
			DOM[ name ] = await page.evaluate( () => {
				const root = document.querySelector( '.memorial-plugin-admin, [id^="memorial-plugin-"]' );
				if ( ! root ) return null;
				const out = new Set();
				root.querySelectorAll( '*' ).forEach( ( el ) => {
					const c = typeof el.className === 'string' ? el.className.trim() : '';
					if ( c ) out.add( el.tagName.toLowerCase() + '.' + c.split( /\s+/ ).join( '.' ) );
				} );
				return [ ...out ].slice( 0, 400 );
			} ).catch( () => null );
		}
		fs.writeFileSync( DIR + '/dom.json', JSON.stringify( DOM, null, 1 ) );
	} );
} );
