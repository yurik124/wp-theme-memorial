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
// отдельный файл на каждую запись: после упавшего теста Playwright перезапускает процесс
const log = ( k, v ) =>
	fs.writeFileSync( `${ DIR }/log-${ k }.json`, JSON.stringify( v, null, 2 ) );
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
				header: document.getElementById( 'header' )?.outerHTML.slice( 0, 12000 ),
				items: [ ...( nav?.querySelectorAll( 'li' ) || [] ) ].slice( 0, 12 ).map( ( li ) => {
					const cs = getComputedStyle( li );
					return [ li.className, li.getBoundingClientRect().height, cs.display, cs.visibility, cs.opacity ];
				} ),
				ul: ( () => {
					const ul = nav?.querySelector( 'ul' );
					if ( ! ul ) return null;
					const cs = getComputedStyle( ul );
					return [ ul.className, ul.getBoundingClientRect().toJSON(), cs.display, cs.visibility, ul.parentElement.className, getComputedStyle( ul.parentElement ).display ];
				} )(),
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
	test.use( { viewport: { width: 1600, height: 900 } } );
	test( 'меню: Кладбища', async ( { page } ) => {
		const errs = errorsOf( page );
		await page.goto( '/', { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: NO_FLOAT } );
		log( 'pc-header', await page.evaluate( () => ( {
			header: document.getElementById( 'header' )?.className,
			html: document.getElementById( 'header' )?.outerHTML.slice( 0, 4000 ),
		} ) ) );
		await shot( page, 'pc-home', { clip: { x: 0, y: 0, width: 1600, height: 300 } } );
		// пункт «Кладбища…» — в подменю; наводим на его родителя
		const link = page.locator( '#header a' ).filter( { hasText: /Кладбищ/ } ).first();
		const parent = page.locator( '#header li.has-child' ).filter( { has: link } ).first();
		await parent.locator( ':scope > a' ).hover().catch( ( e ) => errs.push( 'hover: ' + e.message ) );
		await page.waitForTimeout( 500 );
		await shot( page, 'pc-menu-hover', { clip: { x: 0, y: 0, width: 1600, height: 600 } } );
		await link.click().catch( ( e ) => errs.push( 'click: ' + e.message ) );
		await page.waitForLoadState( 'networkidle' ).catch( () => {} );
		await page.waitForTimeout( 1000 );
		await page.addStyleTag( { content: NO_FLOAT } );
		await shot( page, 'pc-menu-after-click', { clip: { x: 0, y: 0, width: 1600, height: 700 } } );
		const after = await page.evaluate( () => ( {
			url: location.href,
			header: document.getElementById( 'header' )?.className,
			open: [ ...document.querySelectorAll( '#header li.is-open, #header [aria-expanded="true"]' ) ].map( ( e ) => e.outerHTML.slice( 0, 300 ) ),
			focus: document.activeElement?.outerHTML.slice( 0, 300 ),
			subs: [ ...document.querySelectorAll( '#header .wp-block-navigation__submenu-container' ) ].map( ( u ) => {
				const cs = getComputedStyle( u );
				return [ u.parentElement.className, cs.visibility, cs.opacity, cs.display ];
			} ),
		} ) );
		await page.mouse.move( 800, 850 );
		await page.waitForTimeout( 600 );
		await shot( page, 'pc-menu-after-click-mouseaway', { clip: { x: 0, y: 0, width: 1600, height: 700 } } );
		log( 'menu', { after, errs } );
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
				const root = document.querySelector( 'div.memorial-plugin-admin, div[id^="memorial-plugin-"]' );
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
