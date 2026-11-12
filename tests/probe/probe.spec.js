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

for ( const width of [ 1920, 1366 ] ) {
	test.describe( 'компьютер ' + width, () => {
		test.use( { viewport: { width, height: 900 } } );
		test( 'меню: Кладбища ' + width, async ( { page } ) => {
			const errs = errorsOf( page );
			const W = String( width );
			await page.goto( '/', { waitUntil: 'networkidle' } );
			await page.addStyleTag( { content: NO_FLOAT } );
			const compact = await page.evaluate( () => document.getElementById( 'header' )?.classList.contains( 'is-compact' ) );
			await shot( page, `pc${ W }-home`, { clip: { x: 0, y: 0, width, height: 300 } } );
			const link = page.locator( '#header a' ).filter( { hasText: /Кладбищ/ } ).first();
			const parent = page.locator( '#header li.has-child' ).filter( { has: link } ).first();
			if ( compact ) {
				await page.locator( '#burger' ).click( { timeout: 5000 } ).catch( ( e ) => errs.push( 'burger: ' + e.message ) );
				await parent.locator( ':scope > .sub-toggle' ).click( { timeout: 5000 } ).catch( ( e ) => errs.push( 'toggle: ' + e.message ) );
			} else {
				await parent.locator( ':scope > a' ).hover( { timeout: 5000 } ).catch( ( e ) => errs.push( 'hover: ' + e.message ) );
			}
			await page.waitForTimeout( 600 );
			await shot( page, `pc${ W }-menu-open`, { clip: { x: 0, y: 0, width, height: 700 } } );
			await link.click( { timeout: 5000 } ).catch( ( e ) => errs.push( 'click: ' + e.message ) );
			await page.waitForLoadState( 'networkidle' ).catch( () => {} );
			await page.waitForTimeout( 1000 );
			await page.addStyleTag( { content: NO_FLOAT } ).catch( () => {} );
			await shot( page, `pc${ W }-after-click`, { clip: { x: 0, y: 0, width, height: 700 } } );
			const after = await page.evaluate( () => ( {
				url: location.href,
				header: document.getElementById( 'header' )?.className,
				open: [ ...document.querySelectorAll( '#header li.is-open, #header [aria-expanded="true"]' ) ].map( ( e ) => e.outerHTML.slice( 0, 200 ) ),
				focus: document.activeElement?.outerHTML.slice( 0, 200 ),
			} ) );
			await page.mouse.move( width / 2, 850 );
			await page.waitForTimeout( 600 );
			await shot( page, `pc${ W }-after-click-away`, { clip: { x: 0, y: 0, width, height: 700 } } );
			// снова открыть меню на новой странице
			if ( compact ) {
				await page.locator( '#burger' ).click( { timeout: 5000 } ).catch( ( e ) => errs.push( 'burger2: ' + e.message ) );
				await page.waitForTimeout( 600 );
				await shot( page, `pc${ W }-after-click-menu`, { clip: { x: 0, y: 0, width, height: 900 } } );
			}
			log( 'menu-' + W, { compact, after, errs } );
		} );
	} );
}

test.describe( 'компьютер', () => {
	test.use( { viewport: { width: 1280, height: 900 } } );
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
			'admin.php?page=mem-cem',
			'admin.php?page=mem-rest',
			'admin.php?page=mem-legal',
			'admin.php?page=mem-metrika',
			'admin.php?page=mem-home',
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

test.describe( 'ветка меню', () => {
	test.use( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
	test( 'бургер-ветка: раскрытая ветка текущей страницы', async ( { page } ) => {
		const p = PAGES.find( ( x ) => /фотогалере/i.test( x.post_title ) ) || PAGES[ 0 ];
		await page.goto( p.url, { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: NO_FLOAT } );
		await page.locator( '#burger' ).tap();
		await page.waitForTimeout( 800 );
		await shot( page, 'phone-branch' );
		log( 'branch', await page.evaluate( () => {
			const li = document.querySelector( '#header li.is-open' );
			if ( ! li ) return 'нет раскрытой ветки';
			const sub = li.querySelector( '.wp-block-navigation__submenu-container' );
			const a = sub?.querySelector( 'a' );
			const st = ( el ) => {
				if ( ! el ) return null;
				const c = getComputedStyle( el );
				return { cls: el.className, rect: el.getBoundingClientRect().toJSON(), display: c.display, visibility: c.visibility, opacity: c.opacity, color: c.color, height: c.height, overflow: c.overflow, transform: c.transform };
			};
			return { li: st( li ), sub: st( sub ), li2: st( sub?.querySelector( 'li' ) ), a: st( a ), focus: document.activeElement?.outerHTML.slice( 0, 120 ) };
		} ) );
	} );
} );

test.describe( 'подменю ПК', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'подменю ПК: наведение и нажатие', async ( { page } ) => {
		const p = PAGES.find( ( x ) => /пособие/i.test( x.post_title ) ) || PAGES[ 0 ];
		await page.goto( p.url, { waitUntil: 'networkidle' } );
		await page.addStyleTag( { content: NO_FLOAT } );
		const info = async () => page.evaluate( () => [ ...document.querySelectorAll( '#header .has-child' ) ].map( ( li ) => {
			const sub = li.querySelector( '.wp-block-navigation__submenu-container' );
			const c = getComputedStyle( sub );
			return [ li.querySelector( 'a' ).textContent.trim(), sub.getBoundingClientRect().width, c.width, c.minWidth, c.visibility, li.matches( ':hover' ), li.matches( ':focus-within' ) ];
		} ) );
		const parent = page.locator( '#header li.has-child' ).last();
		await parent.locator( ':scope > a' ).hover();
		await page.waitForTimeout( 400 );
		await shot( page, 'pc-sub-hover', { clip: { x: 0, y: 0, width: 1440, height: 450 } } );
		const hover = await info();
		const link = parent.locator( '.wp-block-navigation__submenu-container a' ).nth( 1 );
		const box = await link.boundingBox();
		await page.mouse.move( box.x + 10, box.y + 5 );
		await page.waitForTimeout( 300 );
		await shot( page, 'pc-sub-hover-link', { clip: { x: 0, y: 0, width: 1440, height: 450 } } );
		await page.mouse.down();
		await page.waitForTimeout( 300 );
		await shot( page, 'pc-sub-mousedown', { clip: { x: 0, y: 0, width: 1440, height: 450 } } );
		const down = await info();
		log( 'pc-sub', { hover, down } );
	} );
} );

/**
 * Аудит админки: все экраны плагина из меню (и скрытые), редактор главной и «Контактов», редактор сайта.
 * Для каждого экрана — снимок, ошибки JS, ответы REST/AJAX с кодом ≥ 400, ошибки PHP в разметке.
 */
test.describe( 'аудит админки', () => {
	test.use( { viewport: { width: 1440, height: 1000 } } );
	test( 'аудит админки', async ( { page } ) => {
		test.setTimeout( 1_200_000 );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.evaluate( () => {
			const pr = window.wp?.data?.dispatch( 'core/preferences' );
			pr?.set( 'core/edit-post', 'welcomeGuide', false );
			pr?.set( 'core/edit-site', 'welcomeGuide', false );
			pr?.set( 'core', 'welcomeGuide', false );
		} ).catch( () => {} );
		// пункты меню плагина и его типов записей
		const menu = await page.evaluate( () =>
			[ ...document.querySelectorAll( '#adminmenu a' ) ]
				.map( ( a ) => ( { href: a.getAttribute( 'href' ), text: a.textContent.trim() } ) )
				.filter( ( x ) => /mem[-_]|memorial|post_type=mem_/.test( x.href || '' ) )
		);
		const hidden = [ 'admin.php?page=mem-rewio' ];
		const screens = [ ...new Set( [ ...menu.map( ( x ) => x.href ), ...hidden ] ) ];
		const report = { menu, screens: {} };
		const watch = ( p ) => {
			const st = { js: [], http: [] };
			p.on( 'pageerror', ( e ) => st.js.push( 'pageerror: ' + e.message ) );
			p.on( 'console', ( m ) => m.type() === 'error' && st.js.push( 'console: ' + m.text().slice( 0, 400 ) ) );
			p.on( 'response', async ( r ) => {
				if ( r.status() >= 400 && /wp-json|admin-ajax|rest_route/.test( r.url() ) ) {
					let body = '';
					try { body = ( await r.text() ).slice( 0, 400 ); } catch ( e ) {}
					st.http.push( r.status() + ' ' + r.url().replace( /^https?:\/\/[^/]+/, '' ) + ' ' + body );
				}
			} );
			return st;
		};
		let n = 0;
		for ( const s of screens ) {
			const tab = await page.context().newPage();
			const st = watch( tab );
			const url = '/wp-admin/' + s.replace( /^\/?wp-admin\//, '' );
			await tab.goto( url, { waitUntil: 'load' } ).catch( ( e ) => st.js.push( 'goto: ' + e.message ) );
			await tab.waitForTimeout( 5000 );
			const name = 'audit-' + String( ++n ).padStart( 2, '0' ) + '-' + s.replace( /[^a-z0-9]+/gi, '-' ).replace( /-+$/, '' ).slice( 0, 60 );
			await tab.screenshot( { path: `${ DIR }/${ name }.png`, fullPage: true } ).catch( () => {} );
			const info = await tab.evaluate( () => ( {
				title: document.querySelector( 'h1, .admin-ui-page__header h2, h2' )?.textContent.trim(),
				php: ( document.body.innerHTML.match( /(Fatal error|Warning|Notice|Deprecated)<\/b>:[^<]{0,300}/g ) || [] ).slice( 0, 5 ),
				notices: [ ...document.querySelectorAll( '.notice, .components-notice' ) ].map( ( x ) => x.textContent.trim().slice( 0, 300 ) ).slice( 0, 8 ),
				empty: !! document.querySelector( '[id^="memorial-plugin-"]:empty' ),
				text: ( document.querySelector( '#wpbody-content' )?.innerText || '' ).slice( 0, 1500 ),
			} ) ).catch( ( e ) => ( { error: e.message } ) );
			report.screens[ name ] = { url, ...st, ...info };
			await tab.close();
		}
		// редакторы
		const frontId = await page.evaluate( () => window.wp?.apiFetch?.( { path: '/wp/v2/settings' } ).then( ( r ) => r.page_on_front ) ).catch( () => 0 );
		const front = PAGES.find( ( x ) => +x.ID === +frontId ) || PAGES.find( ( x ) => /главн/i.test( x.post_title ) );
		const contacts = PAGES.find( ( x ) => /контакт/i.test( x.post_title ) );
		const editor = {};
		for ( const p of [ front, contacts ].filter( Boolean ) ) {
			const tab = await page.context().newPage();
			await tab.setViewportSize( { width: 1440, height: 6000 } );
			const st = watch( tab );
			st.ssr = [];
			st.ssrRequests = { single: 0, batch: 0, batchItems: 0 };
			tab.on( 'response', async ( r ) => {
				if ( /render-blocks/.test( decodeURIComponent( r.url() ) ) ) {
					st.ssrRequests.batch++;
					let answers = [];
					try { answers = await r.json(); } catch ( e ) {}
					st.ssrRequests.batchItems += Array.isArray( answers ) ? answers.length : 0;
					if ( r.status() >= 300 || ! Array.isArray( answers ) ) {
						st.ssr.push( r.status() + ' render-blocks ' + JSON.stringify( answers ).slice( 0, 600 ) );
					} else {
						answers.filter( ( a ) => a.status >= 300 ).forEach( ( a ) => st.ssr.push( a.status + ' ' + JSON.stringify( a.body ).slice( 0, 400 ) ) );
					}
				} else if ( /block-renderer/.test( decodeURIComponent( r.url() ) ) ) {
					st.ssrRequests.single++;
					let body = '';
					try { body = await r.text(); } catch ( e ) {}
					const name = ( decodeURIComponent( r.url() ).match( /block-renderer\/([\w-]+\/[\w-]+)/ ) || [] )[ 1 ];
					if ( r.status() >= 300 || ! /"rendered"/.test( body ) ) {
						st.ssr.push( r.status() + ' ' + name + ' ' + body.slice( 0, 600 ) + ' ' + ( r.request().postData() || '' ).slice( 0, 400 ) );
					}
				}
			} );
			await tab.goto( '/wp-admin/post.php?action=edit&post=' + p.ID );
			await tab.waitForTimeout( 20000 );
			await tab.screenshot( { path: `${ DIR }/audit-editor-${ p.post_name || p.ID }.png` } );
			st.errorsInCanvas = [];
			for ( const fr of tab.frames() ) {
				const t = await fr.evaluate( () => [ ...document.querySelectorAll( '[data-type]' ) ].filter( ( el ) => /Ошибка загрузки блока|Error loading block/.test( el.textContent ) && ! el.querySelector( '[data-type]' ) ).map( ( el ) => el.getAttribute( 'data-type' ) + ': ' + el.textContent.trim().slice( 0, 200 ) ) ).catch( () => [] );
				st.errorsInCanvas.push( ...t );
			}
			editor[ p.post_title ] = { ...st, ...( await tab.evaluate( () => {
				const d = window.wp.data;
				const ed = d.select( 'core/editor' );
				return {
					renderingMode: ed.getRenderingMode?.(),
					template: ed.getCurrentTemplateId?.(),
					content: ( ed.getEditedPostContent() || '' ).slice( 0, 2000 ),
					blocks: d.select( 'core/block-editor' ).getBlocks().map( ( b ) => b.name ),
				};
			} ).catch( ( e ) => ( { error: e.message } ) ) ) };
			await tab.close();
		}
		// редактор сайта: шапка
		{
			const tab = await page.context().newPage();
			const st = watch( tab );
			await tab.goto( '/wp-admin/site-editor.php?p=%2Fwp_template_part%2Fmemorial-theme%2F%2Fheader&canvas=edit' );
			await tab.waitForTimeout( 12000 );
			await tab.screenshot( { path: `${ DIR }/audit-site-editor-header.png` } );
			editor[ 'site-editor header' ] = st;
			await tab.close();
		}
		report.editor = editor;
		report.options = { show_on_front: null };
		fs.writeFileSync( `${ DIR }/audit.json`, JSON.stringify( report, null, 1 ) );
	} );
} );
