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
			// то же с развёрнутыми списками: видно, как стоят поля
			const unfold = tab.locator( '.memorial-plugin-settings__fold-all button:last-child' );
			const cnt = await unfold.count().catch( () => 0 );
			if ( cnt ) {
				for ( let k = 0; k < cnt; k++ ) { await unfold.nth( k ).click().catch( () => {} ); }
				for ( let pass = 0; pass < 2; pass++ ) {
					const heads = tab.locator( '.memorial-plugin-settings__row-head[aria-expanded="false"]' );
					const c2 = await heads.count().catch( () => 0 );
					for ( let k = 0; k < c2; k++ ) { await heads.first().click().catch( () => {} ); }
				}
				await tab.waitForTimeout( 500 );
				await tab.screenshot( { path: `${ DIR }/${ name }-open.png`, fullPage: true } ).catch( () => {} );
			}
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
			st.notFound = [];
			tab.on( 'response', ( r ) => {
				if ( r.status() >= 400 ) {
					st.notFound.push( r.status() + ' ' + r.url().slice( 0, 200 ) );
				}
			} );
			await tab.goto( '/wp-admin/site-editor.php?p=%2Fwp_template_part%2Fmemorial-theme%2F%2Fheader&canvas=edit' );
			await tab.waitForTimeout( 12000 );
			st.logo = await tab.evaluate( async () => {
				const s = await window.wp.apiFetch( { path: '/wp/v2/settings' } ).catch( ( e ) => ( { error: String( e && e.message ) } ) );
				return { site_logo: s.site_logo, site_icon: s.site_icon, error: s.error };
			} );
			for ( const fr of tab.frames() ) {
				const html = await fr.evaluate( () => document.querySelector( '[data-type="core/site-logo"]' )?.innerHTML.slice( 0, 300 ) ).catch( () => null );
				if ( html ) {
					st.logoBlock = html;
				}
			}
			await tab.screenshot( { path: `${ DIR }/audit-site-editor-header.png` } );
			editor[ 'site-editor header' ] = st;
			await tab.close();
		}
		report.editor = editor;
		report.options = { show_on_front: null };
		fs.writeFileSync( `${ DIR }/audit.json`, JSON.stringify( report, null, 1 ) );
	} );
} );

/*
 * Аудит блоков: все блоки плагина в черновике (снимок каждого), категории вставки, паттерны без рубрики,
 * подвал в редакторе сайта и внизу обычной страницы, главная в редакторе.
 */
test.describe( 'аудит блоков', () => {
	test.use( { viewport: { width: 1440, height: 1000 } } );
	test( 'аудит блоков', async ( { page } ) => {
		test.setTimeout( 1_500_000 );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const report = {};
		const errs = errorsOf( page );
		// черновик со всеми блоками плагина
		await page.goto( '/wp-admin/post-new.php?post_type=page' );
		await page.waitForFunction( () => window.wp?.blocks?.getBlockTypes?.().length > 50, null, { timeout: 60000 } );
		await page.evaluate( () => {
			const pr = window.wp.data.dispatch( 'core/preferences' );
			pr.set( 'core/edit-post', 'welcomeGuide', false );
			pr.set( 'core', 'welcomeGuide', false );
		} ).catch( () => {} );
		report.types = await page.evaluate( () =>
			window.wp.blocks.getBlockTypes().filter( ( b ) => /^memorial/.test( b.name ) ).map( ( b ) => ( {
				name: b.name, title: b.title, category: b.category, description: b.description,
				attrs: Object.fromEntries( Object.entries( b.attributes || {} ).filter( ( [ , a ] ) => a.autoGenerateControl || a.label ).map( ( [ k, a ] ) => [ k, a.type + ( a.enum ? ':' + a.enum.join( '|' ) : '' ) + ( a.autoGenerateControl ? ' auto' : '' ) ] ) ),
			} ) )
		);
		report.categories = await page.evaluate( () => window.wp.blocks.getCategories().map( ( c ) => c.slug + ' — ' + c.title ) );
		const id = await page.evaluate( async () => {
			const { createBlock, serialize, getBlockTypes } = window.wp.blocks;
			const blocks = [];
			getBlockTypes().filter( ( b ) => /^memorial-plugin\//.test( b.name ) ).forEach( ( b ) => {
				blocks.push( createBlock( 'core/heading', { level: 3, content: b.name + ' — ' + b.title } ) );
				blocks.push( createBlock( b.name ) );
			} );
			const r = await window.wp.apiFetch( { path: '/wp/v2/pages', method: 'POST', data: { title: 'Аудит блоков', status: 'draft', content: serialize( blocks ) } } );
			return r.id;
		} );
		const tab = await page.context().newPage();
		await tab.setViewportSize( { width: 1440, height: 1600 } );
		const ed = errorsOf( tab );
		await tab.goto( '/wp-admin/post.php?action=edit&post=' + id );
		await tab.waitForTimeout( 30000 );
		const canvas = tab.frameLocator( 'iframe[name="editor-canvas"]' );
		const names = report.types.filter( ( t ) => /^memorial-plugin\//.test( t.name ) ).map( ( t ) => t.name );
		report.blocks = {};
		for ( const name of names ) {
			const loc = canvas.locator( `[data-type="${ name }"]` ).first();
			if ( ! ( await loc.count().catch( () => 0 ) ) ) {
				report.blocks[ name ] = { missing: true };
				continue;
			}
			const info = await loc.evaluate( ( el ) => {
				const r = el.getBoundingClientRect();
				const text = el.innerText.trim();
				const hidden = [ ...el.querySelectorAll( '*' ) ].filter( ( x ) => {
					const s = getComputedStyle( x );
					return s.opacity === '0' || s.visibility === 'hidden';
				} ).length;
				return { w: Math.round( r.width ), h: Math.round( r.height ), text: text.slice( 0, 300 ), hiddenEls: hidden, html: el.innerHTML.slice( 0, 600 ) };
			}, null, { timeout: 5000 } ).catch( ( e ) => ( { error: e.message.slice( 0, 200 ) } ) );
			await loc.scrollIntoViewIfNeeded( { timeout: 5000 } ).catch( () => {} );
			await tab.waitForTimeout( 400 );
			await loc.screenshot( { path: `${ DIR }/blk-${ name.replace( /\W+/g, '-' ) }.png`, timeout: 10000 } ).catch( () => {} );
			// промежуточный отчёт: если тест прервётся, собранное останется
			fs.writeFileSync( `${ DIR }/blocks-audit.json`, JSON.stringify( report, null, 1 ) );
			report.blocks[ name ] = info;
		}
		// настройки «Что показывать» у выбранного блока
		for ( const name of [ 'memorial-plugin/topbar', 'memorial-plugin/cards', 'memorial-plugin/catalog', 'memorial-plugin/product' ] ) {
			await tab.evaluate( ( n ) => {
				const b = window.wp.data.select( 'core/block-editor' ).getBlocks().find( ( x ) => x.name === n );
				if ( b ) {
					window.wp.data.dispatch( 'core/block-editor' ).selectBlock( b.clientId );
				}
			}, name ).catch( () => {} );
			await tab.waitForTimeout( 2500 );
			await canvas.locator( `[data-type="${ name }"]` ).first().scrollIntoViewIfNeeded( { timeout: 5000 } ).catch( () => {} );
			await tab.screenshot( { path: `${ DIR }/blk-selected-${ name.split( '/' )[ 1 ] }.png` } ).catch( () => {} );
		}
		// вставка: категории плагина
		await tab.keyboard.press( 'Escape' );
		await tab.locator( 'button.editor-document-tools__inserter-toggle' ).click( { timeout: 5000 } ).catch( () => {} );
		await tab.waitForTimeout( 2500 );
		await tab.screenshot( { path: `${ DIR }/blk-inserter.png` } ).catch( () => {} );
		report.patterns = await tab.evaluate( async () => {
			const all = await window.wp.apiFetch( { path: '/wp/v2/block-patterns/patterns' } );
			const cats = await window.wp.apiFetch( { path: '/wp/v2/block-patterns/categories' } );
			const user = await window.wp.apiFetch( { path: '/wp/v2/blocks?per_page=100&context=edit' } ).catch( () => [] );
			return {
				noCategory: all.filter( ( p ) => ! ( p.categories || [] ).length ).map( ( p ) => p.name + ' — ' + p.title + ( p.inserter === false ? ' (inserter:no)' : '' ) ),
				categories: cats.map( ( c ) => c.name + ' — ' + c.label ),
				user: user.map( ( p ) => ( { id: p.id, title: p.title?.raw, sync: p.wp_pattern_sync_status, cats: p.wp_pattern_category } ) ),
			};
		} ).catch( ( e ) => ( { error: e.message } ) );
		report.editorErrors = ed;
		await tab.close();
		// подвал: редактор сайта и низ обычной страницы
		for ( const [ name, url ] of [
			[ 'footer-part', '/wp-admin/site-editor.php?p=%2Fwp_template_part%2Fmemorial-theme%2F%2Ffooter&canvas=edit' ],
			[ 'header-part', '/wp-admin/site-editor.php?p=%2Fwp_template_part%2Fmemorial-theme%2F%2Fheader&canvas=edit' ],
		] ) {
			const t = await page.context().newPage();
			await t.goto( url );
			await t.waitForTimeout( 15000 );
			await t.screenshot( { path: `${ DIR }/blk-${ name }.png` } );
			await t.close();
		}
		const prices = PAGES.find( ( x ) => /^цены/i.test( x.post_title ) ) || PAGES[ 1 ];
		const frontId = await page.evaluate( () => window.wp?.apiFetch?.( { path: '/wp/v2/settings' } ).then( ( r ) => r.page_on_front ) ).catch( () => 0 );
		for ( const p of [ prices, { ID: frontId, post_name: 'front' } ] ) {
			const t = await page.context().newPage();
			await t.setViewportSize( { width: 1440, height: 1000 } );
			await t.goto( '/wp-admin/post.php?action=edit&post=' + p.ID );
			await t.waitForTimeout( 20000 );
			await t.screenshot( { path: `${ DIR }/blk-page-${ p.post_name }-top.png` } );
			const fr = t.frameLocator( 'iframe[name="editor-canvas"]' );
			await fr.locator( 'footer, .footer' ).last().scrollIntoViewIfNeeded( { timeout: 5000 } ).catch( () => {} );
			await t.waitForTimeout( 2000 );
			await t.screenshot( { path: `${ DIR }/blk-page-${ p.post_name }-bottom.png` } );
			report[ 'mode-' + p.post_name ] = await t.evaluate( () => window.wp.data.select( 'core/editor' ).getRenderingMode?.() ).catch( () => null );
			await t.close();
		}
		for ( const scr of [ 'mem-prices', 'mem-contacts' ] ) {
			const t = await page.context().newPage();
			await t.goto( '/wp-admin/admin.php?page=' + scr );
			await t.waitForTimeout( 6000 );
			await t.screenshot( { path: `${ DIR }/blk-admin-${ scr }.png`, fullPage: true } ).catch( () => {} );
			await t.close();
		}
		report.errors = errs;
		fs.writeFileSync( `${ DIR }/blocks-audit.json`, JSON.stringify( report, null, 1 ) );
	} );
} );

// Ширина шапки и подвала в редакторе сайта: цепочка предков с классами и шириной.
test.describe( 'аудит подвала', () => {
	test.use( { viewport: { width: 1440, height: 1000 } } );
	test( 'аудит подвала', async ( { page } ) => {
		test.setTimeout( 300_000 );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const out = {};
		for ( const part of [ 'footer', 'header' ] ) {
			await page.goto( `/wp-admin/site-editor.php?p=%2Fwp_template_part%2Fmemorial-theme%2F%2F${ part }&canvas=edit` );
			await page.waitForTimeout( 12000 );
			await page.evaluate( () => {
				const pr = window.wp?.data?.dispatch( 'core/preferences' );
				pr?.set( 'core/edit-site', 'welcomeGuide', false );
				pr?.set( 'core/edit-site', 'welcomeGuideTemplate', false );
			} ).catch( () => {} );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 1500 );
			const fr = page.frameLocator( 'iframe[name="editor-canvas"]' );
			out[ part ] = await fr.locator( `.${ part }` ).first().evaluate( ( el ) => {
				const chain = [];
				for ( let e = el; e && e !== document.documentElement; e = e.parentElement ) {
					const s = getComputedStyle( e );
					chain.push( `${ e.tagName.toLowerCase() }.${ [ ...e.classList ].join( '.' ) } w=${ Math.round( e.getBoundingClientRect().width ) } max=${ s.maxWidth } ml=${ s.marginLeft }` );
				}
				return { chain, root: getComputedStyle( document.body ).getPropertyValue( '--wp--style--global--content-size' ) };
			}, null, { timeout: 10000 } ).catch( ( e ) => e.message.slice( 0, 300 ) );
			await page.screenshot( { path: `${ DIR }/part-${ part }.png` } );
		}
		const frontId = await page.evaluate( () => window.wp?.apiFetch?.( { path: '/wp/v2/settings' } ).then( ( r ) => r.page_on_front ) ).catch( () => 0 );
		const contacts = PAGES.find( ( x ) => /контакт/i.test( x.post_title ) );
		for ( const [ name, id ] of [ [ 'front', frontId ], [ 'contacts', contacts?.ID ] ] ) {
			if ( ! id ) {
				continue;
			}
			await page.goto( '/wp-admin/post.php?action=edit&post=' + id );
			await page.waitForTimeout( 20000 );
			await page.screenshot( { path: `${ DIR }/part-page-${ name }.png` } );
			out[ name ] = await page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( '.is-root-container' ).first().evaluate( ( el ) => ( {
				parts: [ ...el.querySelectorAll( ':scope > .wp-block-template-part' ) ].map( ( x ) => getComputedStyle( x ).display ),
				width: Math.round( el.getBoundingClientRect().width ),
			} ), null, { timeout: 10000 } ).catch( ( e ) => e.message.slice( 0, 200 ) );
		}
		fs.writeFileSync( `${ DIR }/parts.json`, JSON.stringify( out, null, 1 ) );
	} );
} );

// Экран «Цены»: отдельная цена раскрывается, смена «Откуда берётся цена» не сворачивает строку; «Отзывы»: показ ключа.
test.describe( 'аудит цен', () => {
	test.use( { viewport: { width: 1440, height: 1000 } } );
	test( 'аудит цен', async ( { page } ) => {
		test.setTimeout( 300_000 );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const out = {};
		await page.goto( '/wp-admin/admin.php?page=mem-prices' );
		await page.waitForTimeout( 6000 );
		const heads = page.locator( '.memorial-plugin-settings__row-head' );
		out.heads = await heads.allInnerTexts().catch( () => [] );
		const last = heads.last();
		await last.scrollIntoViewIfNeeded().catch( () => {} );
		await last.click().catch( ( e ) => ( out.clickError = e.message ) );
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `${ DIR }/prices-open.png` } );
		const sel = page.locator( '.memorial-plugin-settings__row:not(.is-folded) select' ).first();
		await sel.selectOption( 'cat-min' ).catch( ( e ) => ( out.selectError = e.message ) );
		await page.waitForTimeout( 1200 );
		out.stillOpen = await page.locator( '.memorial-plugin-settings__row:not(.is-folded) .memorial-plugin-settings__fields' ).count();
		out.labels = await page.locator( '.memorial-plugin-settings__row:not(.is-folded) label' ).allInnerTexts().catch( () => [] );
		await page.screenshot( { path: `${ DIR }/prices-cat-min.png` } );
		await page.getByRole( 'button', { name: /Добавить цену/ } ).click().catch( ( e ) => ( out.addError = e.message ) );
		await page.waitForTimeout( 1000 );
		out.openAfterAdd = await page.locator( '.memorial-plugin-settings__row:not(.is-folded) .memorial-plugin-settings__fields' ).count();
		await page.screenshot( { path: `${ DIR }/prices-added.png`, fullPage: true } );
		await page.goto( '/wp-admin/admin.php?page=mem-rewio' );
		await page.waitForTimeout( 6000 );
		out.keyButtons = await page.locator( '.memorial-plugin-settings__secret button' ).count();
		await page.screenshot( { path: `${ DIR }/reviews.png`, fullPage: true } );
		fs.writeFileSync( `${ DIR }/prices.json`, JSON.stringify( out, null, 1 ) );
	} );
} );

// Общий текст в редакторе: панель «Общий текст» у привязанного абзаца и у обычного, готовые абзацы во вставке.
test.describe( 'аудит привязок', () => {
	test.use( { viewport: { width: 1440, height: 1000 } } );
	test( 'аудит привязок', async ( { page } ) => {
		test.setTimeout( 300_000 );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/post-new.php?post_type=page' );
		await page.waitForFunction( () => window.wp?.blocks?.getBlockTypes?.().length > 50, null, { timeout: 60000 } );
		const id = await page.evaluate( async () => {
			const bound = ( key ) => `<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"memorial-plugin/settings","args":{"key":"${ key }"}}}}} -->\n<p></p>\n<!-- /wp:paragraph -->`;
			const content = bound( 'mem_contacts:phones.@first.num' ) + '\n\n' + bound( 'mem_contacts:addrs.@first.addr' ) + '\n\n' + bound( 'mem_contacts:night' ) + '\n\n<!-- wp:paragraph -->\n<p>Обычный абзац со своим текстом.</p>\n<!-- /wp:paragraph -->';
			const r = await window.wp.apiFetch( { path: '/wp/v2/pages', method: 'POST', data: { title: 'Аудит привязок', status: 'draft', content } } );
			return r.id;
		} );
		await page.goto( '/wp-admin/post.php?action=edit&post=' + id );
		await page.waitForTimeout( 12000 );
		await page.evaluate( () => {
			const pr = window.wp.data.dispatch( 'core/preferences' );
			pr.set( 'core/edit-post', 'welcomeGuide', false );
			pr.set( 'core', 'welcomeGuide', false );
		} ).catch( () => {} );
		await page.keyboard.press( 'Escape' );
		const out = {};
		for ( const [ n, name ] of [ [ 0, 'bound' ], [ 3, 'plain' ] ] ) {
			await page.evaluate( ( i ) => {
				const b = window.wp.data.select( 'core/block-editor' ).getBlocks()[ i ];
				window.wp.data.dispatch( 'core/block-editor' ).selectBlock( b.clientId );
				window.wp.data.dispatch( 'core/edit-post' )?.openGeneralSidebar?.( 'edit-post/block' );
			}, n );
			await page.waitForTimeout( 2500 );
			await page.screenshot( { path: `${ DIR }/bind-${ name }.png` } );
		}
		out.texts = await page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( '[data-type="core/paragraph"]' ).allInnerTexts().catch( () => [] );
		out.panel = await page.locator( '.components-panel__body' ).allInnerTexts().catch( () => [] );
		fs.writeFileSync( `${ DIR }/bindings.json`, JSON.stringify( out, null, 1 ) );
	} );
} );

/*
 * Слепки страниц для проверки правок CSS без сайта: готовый HTML каждой страницы (после скриптов) со всеми
 * таблицами стилей внутри, для ПК и телефона. По ним tools/css-check сравнивает вычисленные стили
 * каждого элемента до и после правки. Картинки и шрифты в слепок не входят.
 */
test.describe( 'слепки страниц', () => {
	test( 'слепки страниц', async ( { browser } ) => {
		test.setTimeout( 1_500_000 );
		fs.mkdirSync( `${ DIR }/snap`, { recursive: true } );
		const list = [ { name: 'home', path: '/' } ];
		PAGES.forEach( ( p ) => list.push( { name: 'page-' + ( p.post_name || p.ID ), path: p.url.replace( /^https?:\/\/[^/]+/, '' ) } ) );
		list.push( { name: 'catalog', path: '/catalog/' }, { name: '404', path: '/no-such-page-404/' }, { name: 'search', path: '/?s=памятник' } );
		for ( const [ screen, viewport ] of [ [ 'pc', { width: 1280, height: 900 } ], [ 'phone', { width: 390, height: 800 } ] ] ) {
			const context = await browser.newContext( { viewport } );
			const page = await context.newPage();
			if ( screen === 'pc' ) {
				// разделы каталога и товары — из REST (самые наполненные разделы, товары с фото и без)
				const rest = async ( path ) => {
					const res = await page.request.get( '/wp-json/wp/v2/' + path ).catch( () => null );
					return res && res.ok() ? await res.json() : [];
				};
				const cats = await rest( 'mem_cat?per_page=3&orderby=count&order=desc&_fields=link' );
				const products = await rest( 'mem_product?per_page=4&orderby=id&order=asc&_fields=link' );
				cats.forEach( ( x, i ) => list.push( { name: 'cat-' + i, path: new URL( x.link ).pathname } ) );
				products.forEach( ( x, i ) => list.push( { name: 'product-' + i, path: new URL( x.link ).pathname } ) );
			}
			for ( const item of list ) {
				await page.goto( item.path, { waitUntil: 'load' } ).catch( () => {} );
				await page.waitForTimeout( 1200 );
				const html = await page.evaluate( async () => {
					for ( const link of [ ...document.querySelectorAll( 'link[rel="stylesheet"]' ) ] ) {
						let text = '';
						try {
							text = await ( await fetch( link.href ) ).text();
						} catch ( e ) {}
						text = text.replace( /url\((['"]?)(?!data:|https?:|\/\/|#)([^'")]+)\1\)/g, ( m, q, u ) => `url(${ q }${ new URL( u, link.href ).href }${ q })` );
						const style = document.createElement( 'style' );
						style.setAttribute( 'data-href', link.href );
						if ( link.media && link.media !== 'all' ) {
							style.media = link.media;
						}
						style.textContent = text;
						link.replaceWith( style );
					}
					document.querySelectorAll( 'script, noscript, iframe' ).forEach( ( el ) => el.remove() );
					return '<!doctype html>' + document.documentElement.outerHTML;
				} ).catch( ( e ) => '<!-- ' + e.message + ' -->' );
				fs.writeFileSync( `${ DIR }/snap/${ item.name }.${ screen }.html`, html );
			}
			await context.close();
		}
		fs.writeFileSync( `${ DIR }/snap/list.json`, JSON.stringify( list, null, 1 ) );
	} );
} );

test.describe( 'аудит товаров', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'аудит товаров', async ( { page } ) => {
		test.setTimeout( 300_000 );
		page.setDefaultTimeout( 15000 );
		const errs = errorsOf( page );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const out = {};
		const step = async ( name, fn ) => {
			try { await fn(); } catch ( e ) { out[ name + ':error' ] = String( e.message ).slice( 0, 300 ); }
		};
		await page.goto( '/wp-admin/admin.php?page=mem-products', { waitUntil: 'load' } );
		await page.waitForSelector( '.dataviews-view-table tbody tr', { timeout: 60000 } ).catch( () => {} );
		await shot( page, 'prod-01-table' );
		out.styles = await page.evaluate( () =>
			[ ...document.querySelectorAll( 'link[rel=stylesheet]' ) ].map( ( l ) => l.href.replace( /^https?:\/\/[^/]+/, '' ) ).filter( ( h ) => /dataviews|memorial|components|admin-ui/.test( h ) )
		);
		const tick = ( n ) => page.evaluate( ( k ) => {
			const boxes = [ ...document.querySelectorAll( '.dataviews-view-table tbody tr' ) ].map( ( tr ) => tr.querySelector( '[role=checkbox], input[type=checkbox]' ) );
			boxes.slice( 0, k ).forEach( ( b ) => b && b.getAttribute( 'aria-checked' ) !== 'true' && ! b.checked && b.click() );
		}, n );
		await step( 'select1', async () => {
			await tick( 1 );
			await page.waitForTimeout( 600 );
			await shot( page, 'prod-02-selected-1' );
			out.wrapperHtml = await page.evaluate( () => {
				const w = document.querySelector( '.dataviews-wrapper' );
				const c = w.cloneNode( true );
				c.querySelectorAll( 'tbody tr:nth-child(n+3)' ).forEach( ( x ) => x.remove() );
				c.querySelectorAll( 'svg' ).forEach( ( x ) => x.remove() );
				return c.outerHTML.slice( 0, 14000 );
			} );
		} );
		await step( 'select7', async () => {
			await tick( 7 );
			await page.waitForTimeout( 600 );
			await shot( page, 'prod-03-selected-7' );
			out.overlay = await page.evaluate( () => {
				const strip = ( e ) => e ? e.outerHTML.replace( /<svg[\s\S]*?<\/svg>/g, '' ).replace( /\sstyle="[^"]*"/g, '' ).slice( 0, 3500 ) : '';
				const t = document.querySelector( '.dataviews-view-table' );
				const box = t?.parentElement?.cloneNode( true );
				box?.querySelectorAll( 'tbody, thead, svg' ).forEach( ( x ) => x.remove() );
				const ov = [ ...document.querySelectorAll( '[class*="bulk"]' ) ].map( ( e ) => {
					const cs = getComputedStyle( e );
					return [ String( e.className ).slice( 0, 90 ), e.tagName, cs.position, cs.top, Math.round( e.getBoundingClientRect().top ), Math.round( e.getBoundingClientRect().width ) ];
				} );
				const th = document.querySelector( '.dataviews-view-table thead' );
				return { box: box ? box.outerHTML.replace( /\sstyle="[^"]*"/g, '' ).slice( 0, 3000 ) : '', ov, theadPos: th ? [ getComputedStyle( th ).position, getComputedStyle( th ).top ] : null, headH: document.querySelector( '.memorial-plugin-admin [class*="__stack"][class*="__header"]' )?.getBoundingClientRect().height };
			} );
			await page.evaluate( () => window.scrollTo( 0, 700 ) );
			await page.waitForTimeout( 500 );
			await shot( page, 'prod-04-scrolled' );
			out.sticky = await page.evaluate( () => [ ...document.querySelectorAll( '#wpbody-content *' ) ].filter( ( e ) => [ 'sticky', 'fixed' ].includes( getComputedStyle( e ).position ) ).slice( 0, 12 ).map( ( e ) => ( { cls: String( e.className ).slice( 0, 120 ), pos: getComputedStyle( e ).position, top: getComputedStyle( e ).top, z: getComputedStyle( e ).zIndex, rect: e.getBoundingClientRect().toJSON() } ) ) );
			await page.evaluate( () => window.scrollTo( 0, 0 ) );
		} );
		await step( 'viewconfig', async () => {
			await page.locator( '.memorial-plugin-catalog__tools button' ).last().click( { timeout: 5000 } );
			await page.waitForTimeout( 800 );
			await shot( page, 'prod-05-view-config' );
			out.configHtml = await page.evaluate( () => {
				const p = [ ...document.querySelectorAll( '.components-popover, [role=dialog]' ) ].pop();
				if ( ! p ) { return ''; }
				const c = p.cloneNode( true );
				c.querySelectorAll( 'svg' ).forEach( ( x ) => x.remove() );
				const html = c.outerHTML;
				const at = html.indexOf( 'Свойства' );
				return html.slice( Math.max( 0, at - 300 ), at + 5000 );
			} );
			await page.keyboard.press( 'Escape' );
		} );
		await step( 'filter', async () => {
			await page.goto( '/wp-admin/admin.php?page=mem-products', { waitUntil: 'load' } );
			await page.waitForSelector( '.dataviews-view-table tbody tr', { timeout: 60000 } );
			out.footer = await page.evaluate( () => document.querySelector( '.dataviews-footer, .dataviews-pagination' )?.innerText || '' );
			// какие строки таблицы остались без перевода: все тексты пакета из собранного файла
			out.bundleStrings = await page.evaluate( async () => {
				const src = [ ...document.scripts ].map( ( x ) => x.src ).find( ( x ) => /build\/catalog\/index\.js/.test( x ) );
				const txt = src ? await ( await fetch( src ) ).text() : '';
				const found = new Set();
				for ( const m of txt.matchAll( /\(0,[a-zA-Z_$.]+\.(?:__|_n|_x|sprintf)\)\("((?:[^"\\]|\\.){2,80})"/g ) ) { found.add( m[ 1 ] ); }
				return [ ...found ].filter( ( x ) => /item|of %|Select|Deselect|filter|Filter|Density|Compact|Comfortable|Balanced|Reset|Search|selected/.test( x ) ).map( ( x ) => [ x, window.wp.i18n.__( x ) ] );
			} );
			await page.locator( '.dataviews-filters__visibility-toggle, button:has-text("Фильтр"), button[aria-label*="фильтр" i]' ).first().click( { timeout: 5000 } ).catch( () => {} );
			await page.waitForTimeout( 500 );
			await page.locator( '.dataviews-filters__button, button:has-text("Добавить фильтр"), button:has-text("Add filter")' ).first().click( { timeout: 5000 } ).catch( () => {} );
			await page.waitForTimeout( 500 );
			await page.getByRole( 'menuitem', { name: /Категории/ } ).first().click( { timeout: 5000 } ).catch( () => {} );
			await page.waitForTimeout( 800 );
			await page.locator( '[role=dialog] [role=checkbox], [role=dialog] input[type=checkbox], .dataviews-filters__search-widget-listitem' ).first().click( { timeout: 5000 } ).catch( () => {} );
			await page.waitForTimeout( 800 );
			await shot( page, 'prod-11-filter' );
			out.filterCss = await page.evaluate( () => {
				const has = ( sel ) => [ ...document.styleSheets ].some( ( sh ) => { try { return [ ...sh.cssRules ].some( ( r ) => ( r.selectorText || '' ).includes( sel ) ); } catch { return false; } } );
				const chip = document.querySelector( '[class*="summary-chip"]' );
				return {
					chipRemoveRule: has( 'summary-chip-remove' ),
					listItemRule: has( 'search-widget-listitem' ),
					chipClasses: chip ? [ ...chip.parentElement.querySelectorAll( '*' ) ].slice( 0, 12 ).map( ( e ) => e.className && String( e.className ) ) : [],
					popover: ( document.querySelector( '.components-popover, [role=dialog]' )?.outerHTML || '' ).replace( /<svg[\s\S]*?<\/svg>/g, '' ).slice( 0, 2500 ),
					sheets: [ ...document.styleSheets ].map( ( sh ) => ( sh.href || 'inline:' + ( sh.ownerNode?.id || '' ) ).replace( /^https?:\/\/[^/]+/, '' ) ).filter( ( h ) => /dataviews|components|memorial|admin-ui|inline/.test( h ) ),
				};
			} );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 400 );
			await shot( page, 'prod-12-filter-chip' );
			out.api = await page.evaluate( async () => {
				const src = [ ...document.scripts ].map( ( x ) => x.src ).find( ( x ) => /build\/catalog\/index\.js/.test( x ) );
				const txt = src ? await ( await fetch( src ) ).text() : '';
				return [ 'BulkActionToolbar', 'FiltersToggle', 'FiltersToggled', 'ViewConfig', 'LayoutSwitcher', 'Pagination', 'BulkActions', 'Footer', 'Search', 'header', 'children' ].map( ( k ) => [ k, ( txt.match( new RegExp( '\\b' + k + '\\s*[:=]', 'g' ) ) || [] ).length ] );
			} );
			await page.evaluate( () => document.querySelector( '.dataviews-view-table tbody tr input[type=checkbox], .dataviews-view-table tbody tr [role=checkbox]' )?.click() );
			await page.waitForTimeout( 500 );
			out.footerCss = await page.evaluate( () => {
				const f = document.querySelector( '.dataviews-footer' );
				if ( ! f ) { return null; }
				const cs = getComputedStyle( f );
				const chain = [];
				for ( let e = f.parentElement; e && chain.length < 8; e = e.parentElement ) { const c = getComputedStyle( e ); chain.push( [ String( e.className ).slice( 0, 60 ), c.overflow, c.overflowY ] ); }
				return { pos: cs.position, bottom: cs.bottom, html: f.outerHTML.replace( /<svg[\s\S]*?<\/svg>/g, '' ).slice( 0, 2500 ), chain };
			} );
		} );
		await step( 'photo-click', async () => {
			await page.goto( '/wp-admin/admin.php?page=mem-products', { waitUntil: 'load' } );
			await page.waitForSelector( '.dataviews-view-table tbody tr', { timeout: 60000 } );
			const ph = page.locator( '.dataviews-view-table tbody tr .memorial-plugin-catalog__photo' ).first();
			const box = await ph.boundingBox();
			out.photoHit = await page.evaluate( ( b ) => {
				if ( ! b ) { return null; }
				const el = document.elementFromPoint( b.x + b.width / 2, b.y + b.height / 2 );
				const chain = [];
				for ( let e = el; e && chain.length < 6; e = e.parentElement ) { chain.push( [ e.tagName, String( e.className ).slice( 0, 70 ), getComputedStyle( e ).pointerEvents, e.inert ] ); }
				const media = document.querySelector( '.dataviews-view-table tbody tr .memorial-plugin-catalog__photo' )?.parentElement;
				const btn = document.querySelector( '.dataviews-view-table tbody tr .memorial-plugin-catalog__photo' );
				const r = ( e ) => e && e.getBoundingClientRect().toJSON();
				return { chain, media: media ? [ String( media.className ), r( media ), getComputedStyle( media ).overflow, media.outerHTML.replace( /<svg[\s\S]*?<\/svg>/g, '' ).slice( 0, 900 ) ] : null, btn: [ btn?.tagName, r( btn ), getComputedStyle( btn ).display, r( btn?.querySelector( 'img' ) ) ], b };
			}, box );
			await ph.click( { timeout: 5000 } ).catch( ( e ) => ( out.photoClickErr = String( e ).slice( -600 ) ) );
			await page.waitForTimeout( 600 );
			out.viewerOpen = await page.locator( '.memorial-plugin-viewer' ).count();
			await shot( page, 'prod-13-photo-click' );
			await page.keyboard.press( 'Escape' );
		} );
		await step( 'edit', async () => {
			await page.goto( '/wp-admin/admin.php?page=mem-products', { waitUntil: 'load' } );
			await page.waitForSelector( '.dataviews-view-table tbody tr', { timeout: 60000 } );
			await page.locator( '.dataviews-view-table tbody tr' ).first().getByRole( 'button', { name: /Изменить|Edit/ } ).first().click( { timeout: 8000 } );
			await page.waitForTimeout( 800 );
			await shot( page, 'prod-08-edit-modal', { fullPage: true } );
		} );
		for ( const [ n, u ] of [ [ 'types', 'edit.php?post_type=mem_product&page=mem-types' ], [ 'pages', 'edit.php?post_type=mem_product&page=mem-product-pages' ] ] ) {
			await step( n, async () => {
				await page.goto( '/wp-admin/' + u, { waitUntil: 'load' } );
				await page.waitForTimeout( 4000 );
				await shot( page, 'prod-09-' + n, { fullPage: true } );
			} );
		}
		await step( 'site-editor', async () => {
			await page.goto( '/wp-admin/site-editor.php?p=%2Fwp_template_part%2Fmemorial-theme%2F%2Fheader&canvas=edit', { waitUntil: 'load' } );
			await page.waitForTimeout( 6000 );
			await shot( page, 'prod-10-site-editor' );
		} );
		out.built = await page.evaluate( async () => {
			const res = {};
			for ( const f of [ 'index.css', 'style-index.css' ] ) {
				const r = await fetch( '/wp-content/plugins/memorial-plugin/build/catalog/' + f );
				const t = r.ok ? await r.text() : '';
				res[ f ] = { status: r.status, size: t.length, chip: t.includes( 'summary-chip-remove' ), layer: ( t.match( /@layer[^{;]*/g ) || [] ).slice( 0, 5 ) };
			}
			return res;
		} );
		// какие файлы стилей есть в собранном пакете DataViews (на раннере есть node_modules)
		try {
			const fs = require( 'fs' );
			const path = require( 'path' );
			const root = path.resolve( 'node_modules/@wordpress/dataviews' );
			const pkg = JSON.parse( fs.readFileSync( path.join( root, 'package.json' ), 'utf8' ) );
			const css = [];
			const walk = ( d, depth ) => { for ( const f of fs.readdirSync( d ) ) { const p = path.join( d, f ); if ( fs.statSync( p ).isDirectory() ) { if ( depth < 3 && f !== 'node_modules' ) { walk( p, depth + 1 ); } } else if ( /\.css$/.test( f ) ) { css.push( path.relative( root, p ) + ' ' + fs.statSync( p ).size ); } } };
			walk( root, 0 );
			out.pkg = { version: pkg.version, exports: pkg.exports, style: pkg.style, sideEffects: pkg.sideEffects, css: css.slice( 0, 40 ) };
		} catch ( e ) {
			out.pkg = String( e );
		}
		out.errors = errs.slice( 0, 20 );
		log( 'products', out );
	} );
} );

// Пакеты одним списком, сортировка каталога, «Показать ещё», отзывы Rewio, сохранение «Цен».
test.describe( 'пакеты', () => {
	test.use( { viewport: { width: 1366, height: 900 } } );
	test( 'аудит пакетов', async ( { page } ) => {
		test.setTimeout( 600_000 );
		const report = {};
		const errs = errorsOf( page );
		const front = async ( name, url, width ) => {
			const t = await page.context().newPage();
			await t.setViewportSize( { width, height: width < 600 ? 844 : 900 } );
			await t.goto( url, { waitUntil: 'networkidle' } ).catch( () => {} );
			await t.addStyleTag( { content: NO_FLOAT + '.reveal{opacity:1!important;transform:none!important}' } );
			const pk = t.locator( '.mem-pkgs' ).first();
			if ( await pk.count() ) {
				await pk.evaluate( ( el ) => el.querySelectorAll( 'details' ).forEach( ( d ) => ( d.open = true ) ) );
				await pk.screenshot( { path: `${ DIR }/pk-${ name }.png` } ).catch( () => {} );
				report[ name ] = await pk.evaluate( ( el ) => ( {
					cards: [ ...el.querySelectorAll( '.pkg' ) ].map( ( c ) => {
						const r = c.getBoundingClientRect();
						return { h: c.querySelector( 'h3' )?.textContent, x: Math.round( r.x ), y: Math.round( r.y ), w: Math.round( r.width ), link: c.querySelector( '.pkg__more' )?.href || '', note: c.querySelector( '.pkg__note' )?.textContent || '' };
					} ),
					leaf: getComputedStyle( el.querySelector( '.pkg__rows li' ) || el, '::before' ).content,
				} ) );
			}
			const tbl = t.locator( '.mem-cremation' ).first();
			if ( await tbl.count() ) {
				await tbl.screenshot( { path: `${ DIR }/pk-${ name }-table.png` } ).catch( () => {} );
			}
			const cat = t.locator( '.mcat, .works' ).first();
			if ( await cat.count() ) {
				await t.evaluate( () => window.scrollBy( 0, 1400 ) );
				await t.waitForTimeout( 500 );
				await t.screenshot( { path: `${ DIR }/pk-${ name }-scrolled.png` } );
				report[ name + '-cat' ] = await t.evaluate( () => {
					const s = document.querySelector( '.mcat__sort' );
					const m = document.querySelector( '.mcat__more:not([hidden])' );
					const r = m?.getBoundingClientRect();
					const pr = m?.parentElement.getBoundingClientRect();
					return { sortTop: s ? Math.round( s.getBoundingClientRect().top ) : null, more: r ? { left: Math.round( r.left - pr.left ), right: Math.round( pr.right - r.right ) } : null };
				} );
			}
			const rw = t.locator( '.rwo__src' );
			if ( await rw.count() ) {
				report[ name + '-rewio' ] = await rw.evaluateAll( ( a ) => a.slice( 0, 6 ).map( ( f ) => f.querySelector( 'a' )?.href || 'NO LINK: ' + f.textContent ) );
			}
			await t.close();
		};
		const byTitle = ( re ) => PAGES.find( ( x ) => re.test( x.post_title ) );
		const list = [
			[ 'home', '/' ],
			[ 'ceny', byTitle( /^цены/i )?.url ],
			[ 'krem', byTitle( /кремац/i )?.url ],
			[ 'otzyvy', byTitle( /отзыв/i )?.url ],
			[ 'groby', byTitle( /гроб/i )?.url ],
			[ 'raboty', byTitle( /наши работы|работ/i )?.url ],
		].filter( ( x ) => x[ 1 ] );
		for ( const [ n, u ] of list ) {
			await front( n, u, 1366 );
			await front( n + '-phone', u, 390 );
		}
		// админка «Цены»: добавить пакет, сохранить — он должен остаться без обновления страницы
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/admin.php?page=mem-prices' );
		await page.waitForSelector( '.memorial-plugin-settings__repeater', { timeout: 60000 } );
		await page.waitForTimeout( 1500 );
		await page.screenshot( { path: `${ DIR }/pk-admin.png`, fullPage: true } );
		report.tokens = await page.locator( '.memorial-plugin-settings__row-sum' ).allTextContents();
		const before = await page.locator( '.memorial-plugin-settings__row-title' ).count();
		await page.getByRole( 'button', { name: /Добавить пакет/ } ).first().click();
		await page.waitForTimeout( 500 );
		const row = page.locator( '.memorial-plugin-settings__row:not(.is-folded)' ).last();
		await row.locator( 'input[type=text]' ).first().fill( 'Проба' );
		await page.waitForTimeout( 300 );
		await page.screenshot( { path: `${ DIR }/pk-admin-new.png`, fullPage: true } );
		await page.getByRole( 'button', { name: /^Сохранить$/ } ).first().click();
		await page.waitForTimeout( 4000 );
		report.admin = {
			before,
			after: await page.locator( '.memorial-plugin-settings__row-title' ).count(),
			titles: await page.locator( '.memorial-plugin-settings__row-title' ).allTextContents(),
			hints: await page.evaluate( () => [ ...new Set( [ ...document.querySelectorAll( '.memorial-plugin-hint__btn .dashicons' ) ].map( ( d ) => getComputedStyle( d ).fontSize ) ) ] ),
		};
		await page.screenshot( { path: `${ DIR }/pk-admin-saved.png`, fullPage: true } );
		// первый ряд состава: галочка «На карточке» на одном уровне с полями
		await page.getByRole( 'button', { name: /Развернуть$/ } ).first().click().catch( () => {} );
		await page.waitForTimeout( 500 );
		report.firstRow = await page.evaluate( () => {
			const rep = document.querySelector( '.memorial-plugin-settings__repeater.is-inline' );
			const row = rep?.querySelector( '.memorial-plugin-settings__row' );
			const inp = row?.querySelector( 'input[type=text]' )?.getBoundingClientRect();
			const cb = row?.querySelector( 'input[type=checkbox]' )?.getBoundingClientRect();
			return inp && cb ? { input: Math.round( inp.top + inp.height / 2 ), checkbox: Math.round( cb.top + cb.height / 2 ) } : null;
		} );
		const inl = page.locator( '.memorial-plugin-settings__repeater.is-inline' ).first();
		if ( await inl.count() ) {
			await inl.screenshot( { path: `${ DIR }/pk-admin-rows.png` } ).catch( () => {} );
		}
		report.errors = errs;
		log( 'packages', report );
	} );
} );

// «Список отзывов», «Настройки» с вкладкой «Разрешения», подстановка цены.
test.describe( 'отзывы и разрешения', () => {
	test.use( { viewport: { width: 1366, height: 900 } } );
	test( 'аудит отзывов', async ( { page } ) => {
		test.setTimeout( 600_000 );
		const report = {};
		const errs = errorsOf( page );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/admin.php?page=memorial-plugin-rewio' );
		await page.waitForSelector( '.dataviews-view-table, .dataviews-no-results', { timeout: 60000 } ).catch( () => {} );
		await page.waitForTimeout( 1500 );
		await page.screenshot( { path: `${ DIR }/rv-list.png`, fullPage: true } );
		report.rows = await page.locator( '.dataviews-view-table tbody tr' ).count();
		report.menu = await page.locator( '#adminmenu a[href*="memorial-plugin-rewio"]' ).count();
		// скрыть первый отзыв через действие строки
		const first = page.locator( '.dataviews-view-table tbody tr' ).first();
		if ( await first.count() ) {
			await first.hover();
			await first.locator( 'button[aria-label="Actions"], button[aria-label="Действия"]' ).first().click( { timeout: 8000 } ).catch( () => {} );
			await page.waitForTimeout( 400 );
			await page.screenshot( { path: `${ DIR }/rv-actions.png` } );
			await page.getByRole( 'menuitem', { name: /Скрыть на сайте/ } ).click( { timeout: 8000 } ).catch( () => {} );
			await page.waitForTimeout( 2500 );
			report.afterHide = await page.locator( '.components-notice' ).allTextContents();
		}
		await page.locator( '.dataviews-view-table tbody tr' ).first().getByRole( 'button', { name: /Изменить|Edit/ } ).first().click( { timeout: 8000 } ).catch( () => {} );
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `${ DIR }/rv-edit.png` } );
		await page.keyboard.press( 'Escape' );
		await page.goto( '/wp-admin/admin.php?page=mem-modules' );
		await page.waitForSelector( '.memorial-plugin-settings__tabs', { timeout: 60000 } ).catch( () => {} );
		await page.waitForTimeout( 1000 );
		await page.screenshot( { path: `${ DIR }/rv-settings.png`, fullPage: true } );
		await page.getByRole( 'tab', { name: /Разрешения/ } ).click( { timeout: 8000 } ).catch( () => {} );
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `${ DIR }/rv-perms.png`, fullPage: true } );
		report.perms = await page.locator( '.memorial-plugin-settings__perms tbody tr' ).allTextContents();
		await page.getByRole( 'tab', { name: /Почта/ } ).click( { timeout: 8000 } ).catch( () => {} );
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `${ DIR }/rv-mail.png`, fullPage: true } );
		await page.getByRole( 'button', { name: /тестовое письмо/ } ).click( { timeout: 8000 } ).catch( () => {} );
		await page.waitForTimeout( 3000 );
		report.mailTest = await page.locator( '.memorial-plugin-settings__action .components-notice' ).allTextContents();
		report.mailRaw = await page.evaluate( async () => {
			const st = await window.wp.apiFetch( { path: '/memorial-plugin/v1/settings/mem_modules_page' } ).catch( () => ( {} ) );
			const r = await window.wp.apiFetch( { path: '/memorial-plugin/v1/lead-mail-test', method: 'POST', data: { values: st?.value?.mail || {} }, parse: false } ).catch( ( e ) => e );
			return r && r.text ? r.status + ' ' + ( await r.text() ).slice( 0, 1500 ) : String( r?.message || r );
		} );
		await page.goto( '/wp-admin/admin.php?page=memorial-plugin-leads' );
		await page.waitForTimeout( 4000 );
		await page.screenshot( { path: `${ DIR }/rv-leads.png`, fullPage: true } );
		await page.goto( '/wp-admin/edit.php?post_type=mem_work&page=mem-works' );
		await page.waitForSelector( '.dataviews-view-table tbody tr', { timeout: 60000 } ).catch( () => {} );
		await page.locator( '.dataviews-view-table tbody tr' ).first().getByRole( 'button', { name: /Изменить/ } ).first().click( { timeout: 8000 } ).catch( () => {} );
		const t0 = Date.now();
		await page.waitForSelector( '.memorial-plugin-photos img', { timeout: 20000 } ).catch( () => {} );
		report.workPhotosMs = Date.now() - t0;
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `${ DIR }/rv-work-edit.png`, fullPage: true } );
		report.errors = errs;
		log( 'reviews', report );
	} );
} );

test.describe( 'редактор сайта', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'аудит редактора сайта', async ( { page, browser } ) => {
		test.setTimeout( 1_500_000 );
		page.setDefaultTimeout( 20000 );
		const report = { screens: {}, canvas: {}, menu: {} };
		// Картинка темы: главная рабочего сайта 1200×900 (screenshot.png)
		const ctx = await browser.newContext( { viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1 } );
		const p2 = await ctx.newPage();
		await p2.goto( 'https://memorialbel.ru/', { waitUntil: 'networkidle', timeout: 90000 } ).catch( () => {} );
		await p2.addStyleTag( { content: NO_FLOAT + '.consent-banner,.memorial-plugin-cookie,[class*=cookie],[class*=mobile-bar]{display:none !important}' } ).catch( () => {} );
		await p2.waitForTimeout( 3000 );
		await p2.screenshot( { path: `${ DIR }/theme-screenshot.png` } );
		await p2.screenshot( { path: `${ DIR }/theme-home-full.png`, fullPage: true } );
		await ctx.close();
		const errs = errorsOf( page );
		const bad = [];
		page.on( 'response', ( r ) => {
			if ( r.status() >= 400 && /wp-json|rest_route/.test( r.url() ) ) {
				bad.push( r.status() + ' ' + decodeURIComponent( r.url() ).replace( /^https?:\/\/[^/]+/, '' ).slice( 0, 200 ) );
			}
		} );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );

		// Меню «Внешний вид»: что в нём есть (Customizer, Меню, Виджеты быть не должно)
		await page.goto( '/wp-admin/themes.php' );
		report.menu.appearance = await page.locator( '#menu-appearance .wp-submenu a' ).evaluateAll( ( a ) => a.map( ( x ) => x.textContent.trim() + ' → ' + x.getAttribute( 'href' ) ) );
		await shot( page, 'se-themes' ).catch( () => {} );
		report.menu.themeCard = await page.locator( '.theme.active' ).first().innerText( { timeout: 5000 } ).catch( () => '' );
		report.menu.themeShot = await page.locator( '.theme.active .theme-screenshot img' ).first().getAttribute( 'src', { timeout: 5000 } ).catch( () => null );
		log( 'site-editor', report );

		const editorReady = async () => {
			await page.waitForSelector( '.edit-site-layout, .edit-site', { timeout: 60000 } ).catch( () => {} );
			await page.waitForTimeout( 4000 );
		};
		const canvasCheck = async () => {
			const fr = page.frameLocator( 'iframe[name="editor-canvas"]' );
			await page.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 30000 } ).catch( () => {} );
			await page.waitForTimeout( 5000 );
			const frame = page.frame( { name: 'editor-canvas' } );
			if ( ! frame ) {
				return { canvas: false };
			}
			return frame.evaluate( () => {
				const txt = ( el ) => el.textContent.trim().replace( /\s+/g, ' ' ).slice( 0, 220 );
				return {
					canvas: true,
					warnings: [ ...document.querySelectorAll( '.block-editor-warning' ) ].map( ( el ) => ( el.closest( '[data-type]' )?.getAttribute( 'data-type' ) || '?' ) + ': ' + txt( el ) ),
					loadErrors: [ ...document.querySelectorAll( '[data-type]' ) ].filter( ( el ) => /Ошибка загрузки блока|Error loading block|encountered an error|возникла ошибка/i.test( el.textContent ) && ! el.querySelector( '[data-type]' ) ).map( ( el ) => el.getAttribute( 'data-type' ) + ': ' + txt( el ) ),
					missing: [ ...document.querySelectorAll( '.wp-block-missing, [data-type="core/missing"]' ) ].map( txt ),
					placeholders: [ ...document.querySelectorAll( '.components-placeholder' ) ].map( ( el ) => ( el.closest( '[data-type]' )?.getAttribute( 'data-type' ) || '?' ) + ': ' + txt( el ) ),
					blocks: document.querySelectorAll( '[data-type]' ).length,
					types: [ ...new Set( [ ...document.querySelectorAll( '[data-type]' ) ].map( ( el ) => el.getAttribute( 'data-type' ) ) ) ],
					landmarks: [ 'header', 'main', 'footer', 'nav' ].map( ( t ) => t + ':' + document.querySelectorAll( t ).length ).join( ' ' ),
				};
			} ).catch( ( e ) => ( { error: String( e ) } ) );
		};

		// Корень «Дизайн» — тот самый экран с подписью «Настройте внешний вид вашего сайта с помощью редактора блоков»
		const routes = {
			root: '/wp-admin/site-editor.php',
			navigation: '/wp-admin/site-editor.php?p=%2Fnavigation',
			styles: '/wp-admin/site-editor.php?p=%2Fstyles',
			pages: '/wp-admin/site-editor.php?p=%2Fpage',
			templates: '/wp-admin/site-editor.php?p=%2Ftemplate',
			patterns: '/wp-admin/site-editor.php?p=%2Fpattern',
			parts: '/wp-admin/site-editor.php?p=%2Fpattern&postType=wp_template_part',
		};
		for ( const [ k, url ] of Object.entries( routes ) ) {
			await page.goto( url );
			await editorReady();
			report.screens[ k ] = {
				url: page.url().replace( /^https?:\/\/[^/]+/, '' ),
				sidebar: await page.locator( '.edit-site-sidebar__content, .edit-site-layout__sidebar' ).first().innerText().catch( () => '' ),
				notices: await page.locator( '.components-notice, .components-snackbar' ).allTextContents(),
			};
			await shot( page, 'se-' + k ).catch( () => {} );
			log( 'site-editor', report );
		}
		// Список шаблонов и частей (данные REST)
		report.templates = await page.evaluate( async () => {
			const t = await window.wp.apiFetch( { path: '/wp/v2/templates?context=edit&per_page=100' } ).catch( ( e ) => [ { error: e.message } ] );
			const p = await window.wp.apiFetch( { path: '/wp/v2/template-parts?context=edit&per_page=100' } ).catch( ( e ) => [ { error: e.message } ] );
			const n = await window.wp.apiFetch( { path: '/wp/v2/navigation?context=edit&per_page=100&status=publish,draft' } ).catch( ( e ) => [ { error: e.message } ] );
			const gs = await window.wp.apiFetch( { path: '/wp/v2/global-styles/themes/memorial-theme/variations' } ).catch( ( e ) => [ { error: e.message } ] );
			const pat = await window.wp.apiFetch( { path: '/wp/v2/block-patterns/patterns' } ).catch( ( e ) => [ { error: e.message } ] );
			const cats = await window.wp.apiFetch( { path: '/wp/v2/block-patterns/categories' } ).catch( ( e ) => [ { error: e.message } ] );
			const one = ( x ) => ( { id: x.id, title: x.title?.rendered ?? x.title, source: x.source, origin: x.origin, modified: x.modified, has_theme_file: x.has_theme_file, area: x.area, error: x.error } );
			return {
				templates: t.map( one ),
				parts: p.map( one ),
				navigation: n.map( ( x ) => ( { id: x.id, title: x.title?.raw, status: x.status, blocks: ( x.content?.raw || '' ).match( /<!-- wp:[a-z/-]+/g )?.length || 0 } ) ),
				variations: gs.map( ( x ) => x.title || x.error ),
				themePatterns: pat.filter( ( x ) => /^memorial/.test( x.name ) ).map( ( x ) => x.name + ' [' + ( x.categories || [] ).join( ',' ) + ']' + ( x.inserter === false ? ' (скрыт)' : '' ) ),
				patternCats: cats.map( ( x ) => x.name + ': ' + x.label ).filter( ( x ) => /memorial/.test( x ) ),
			};
		} );

		// Каждый шаблон и часть — в режиме правки: ошибки блоков на холсте
		const ids = [ ...( report.templates.templates || [] ), ...( report.templates.parts || [] ) ].filter( ( x ) => x.id ).map( ( x ) => [ x.area ? 'wp_template_part' : 'wp_template', x.id ] );
		for ( const [ type, id ] of ids ) {
			await page.goto( '/wp-admin/site-editor.php?p=' + encodeURIComponent( '/' + type + '/' + id ) + '&canvas=edit' );
			await editorReady();
			report.canvas[ id ] = await canvasCheck();
			report.canvas[ id ].notices = await page.locator( '.components-notice' ).allTextContents();
			await shot( page, 'se-edit-' + id.replace( /[^a-z0-9-]+/gi, '_' ) ).catch( () => {} );
			log( 'site-editor', report );
		}

		// Стили: варианты, книга стилей
		await page.goto( '/wp-admin/site-editor.php?p=%2Fstyles&canvas=edit' );
		await editorReady();
		await shot( page, 'se-styles-edit' );
		await page.getByRole( 'button', { name: /Обзор стилей|Browse styles/ } ).first().click( { timeout: 8000 } ).catch( () => {} );
		await page.waitForTimeout( 2500 );
		await shot( page, 'se-styles-variations' );
		report.styleVariationsUI = await page.locator( '.edit-site-global-styles-variations_item, .global-styles-ui-variations_item, [class*="variations_item"]' ).count();
		await page.getByRole( 'button', { name: /Книга стилей|Style Book/ } ).first().click( { timeout: 8000 } ).catch( () => {} );
		await page.waitForTimeout( 4000 );
		await shot( page, 'se-stylebook' );

		// Меню «Навигация»: открыть первое меню
		await page.goto( '/wp-admin/site-editor.php?p=%2Fnavigation' );
		await editorReady();
		await page.locator( '.edit-site-sidebar-navigation-screen a, .edit-site-sidebar-navigation-item' ).first().click( { timeout: 8000 } ).catch( () => {} );
		await page.waitForTimeout( 4000 );
		report.screens.navigationOpen = { url: page.url().replace( /^https?:\/\/[^/]+/, '' ), sidebar: await page.locator( '.edit-site-layout__sidebar' ).first().innerText().catch( () => '' ) };
		await shot( page, 'se-navigation-open' );

		report.restErrors = bad;
		report.errors = errs;
		log( 'site-editor', report );

	} );
} );

test.describe( 'итог 2.1', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'итог 2.1: редактор сайта, меню, обновление', async ( { page, browser } ) => {
		test.setTimeout( 900_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const BASE = 'http://localhost:8888';
		// Картинка темы (screenshot.png 1200×900) — главная копии сайта с фото
		const ctx = await browser.newContext( { viewport: { width: 1200, height: 900 }, deviceScaleFactor: 1 } );
		const p2 = await ctx.newPage();
		await p2.goto( BASE + '/', { waitUntil: 'networkidle', timeout: 90000 } ).catch( () => {} );
		await p2.addStyleTag( { content: NO_FLOAT + '.mbar,dialog{display:none !important} *{animation:none !important;transition:none !important}' } ).catch( () => {} );
		report.bodyClass = await p2.evaluate( () => document.documentElement.className + ' | ' + document.body.className ).catch( () => '' );
		await p2.waitForTimeout( 3000 );
		await p2.screenshot( { path: `${ DIR }/theme-screenshot.png` } );
		await p2.screenshot( { path: `${ DIR }/front-full.png`, fullPage: true } );
		// Подвал и крошки — из «Навигации»
		report.footerCols = await p2.locator( '.fnav .fnav__title' ).allTextContents().catch( () => [] );
		report.footerLinks = await p2.locator( '.fnav a' ).count().catch( () => 0 );
		const faq = PAGES.find( ( x ) => /вопрос/i.test( x.post_title ) );
		const sub = PAGES.find( ( x ) => /оградк|венки|гроб/i.test( x.post_title ) );
		for ( const pg of [ faq, sub ].filter( Boolean ) ) {
			await p2.goto( BASE + new URL( pg.url ).pathname, { waitUntil: 'domcontentloaded' } ).catch( () => {} );
			report[ 'crumbs ' + pg.post_title ] = ( await p2.locator( '.crumbs, .breadcrumbs, nav[aria-label*="крошк" i], [class*=crumb]' ).first().innerText().catch( () => '' ) ).replace( /\s+/g, ' ' );
		}
		await ctx.close();

		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		report.appearance = await page.locator( '#menu-appearance .wp-submenu a' ).evaluateAll( ( a ) => a.map( ( x ) => x.textContent.trim() ) );
		// журналы обновления — со страницы всех настроек
		await page.goto( '/wp-admin/options.php' );
		report.options = await page.evaluate( () => {
			const out = {};
			for ( const k of [ 'memorial_plugin_upgrade_210', 'memorial_theme_upgrade_510', 'memorial_plugin_db', 'memorial_theme_db', 'memorial_theme_main_nav', 'memorial_plugin_webp', 'mem_design', 'mem_speed', 'theme_mods_memorial_speshu', 'memorial_theme_nav_migrated' ] ) {
				const el = document.querySelector( '#' + k );
				out[ k ] = el ? ( el.value || el.textContent || '' ).slice( 0, 400 ) : '(нет)';
			}
			return out;
		} );
		await page.goto( '/wp-admin/site-editor.php' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } ).catch( () => {} );
		report.navs = await page.evaluate( async () => ( await window.wp.apiFetch( { path: '/wp/v2/navigation?context=edit&per_page=50&status=publish,draft,trash' } ).catch( ( e ) => [ { id: e.message } ] ) ).map( ( x ) => x.id + ' ' + x.status + ' ' + ( x.title?.raw || '' ) ) );
		report.types = await page.evaluate( async () => {
			await new Promise( ( r ) => setTimeout( r, 500 ) );
			const t = await fetch( '/wp-json/wp/v2/types/page?context=edit', { headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce || '' } } ).then( ( r ) => r.json() ).catch( () => ( {} ) );
			const n = await fetch( '/wp-json/wp/v2/navigation?context=edit&per_page=50&status=publish,draft,trash', { headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce || '' } } ).then( ( r ) => r.json() ).catch( () => [] );
			return { mode: t.default_rendering_mode, nav: Array.isArray( n ) ? n.map( ( x ) => x.id + ' ' + x.status + ' ' + x.title?.raw ) : n };
		} );
		const probeOne = async ( name, url ) => {
			await page.goto( url );
			await page.waitForSelector( '.edit-site-layout, .edit-site, .editor-header', { timeout: 60000 } ).catch( () => {} );
			await page.evaluate( () => {
				try {
					for ( const k of [ 'welcomeGuide', 'welcomeGuideStyles', 'welcomeGuidePage', 'welcomeGuideTemplate' ] ) {
						window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-site', k, false );
						window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', k, false );
					}
				} catch ( e ) {}
			} );
			await page.waitForTimeout( 6000 );
			const st = await page.evaluate( () => ( { mode: window.wp.data.select( 'core/editor' ).getRenderingMode?.() } ) ).catch( ( e ) => ( { e: String( e ) } ) );
			const frame = page.frame( { name: 'editor-canvas' } );
			st.canvas = frame ? await frame.evaluate( () => ( {
				parts: [ ...document.querySelectorAll( '.wp-block-template-part' ) ].map( ( e ) => e.tagName + ':' + getComputedStyle( e ).display ),
				title: [ ...document.querySelectorAll( '.editor-visual-editor__post-title-wrapper' ) ].map( ( e ) => getComputedStyle( e ).display ),
				warnings: [ ...document.querySelectorAll( '.block-editor-warning' ) ].length,
			} ) ).catch( ( e ) => String( e ) ) : null;
			report[ name ] = st;
			await shot( page, 'fin-' + name ).catch( () => {} );
			log( 'final', report );
		};
		const front = PAGES.find( ( x ) => /главн/i.test( x.post_title ) );
		await probeOne( 'se-root', '/wp-admin/site-editor.php' );
		await probeOne( 'se-styles', '/wp-admin/site-editor.php?p=%2Fstyles' );
		if ( faq ) {
			await probeOne( 'se-page-faq', '/wp-admin/site-editor.php?p=' + encodeURIComponent( '/page/' + faq.ID ) );
			await probeOne( 'edit-faq', '/wp-admin/post.php?post=' + faq.ID + '&action=edit' );
		}
		if ( front ) {
			await probeOne( 'edit-front', '/wp-admin/post.php?post=' + front.ID + '&action=edit' );
		}
		await probeOne( 'se-templates', '/wp-admin/site-editor.php?p=%2Ftemplate' );
		await probeOne( 'se-footer', '/wp-admin/site-editor.php?p=' + encodeURIComponent( '/wp_template_part/memorial-theme//footer' ) + '&canvas=edit' );
		log( 'final', report );
	} );
} );

test.describe( 'тексты админки', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'тексты админки: меню, экраны, схемы', async ( { page } ) => {
		test.setTimeout( 1_200_000 );
		page.setDefaultTimeout( 20000 );
		const out = { menu: [], screens: {} };
		const json = [];
		page.on( 'response', async ( r ) => {
			const u = decodeURIComponent( r.url() );
			if ( /memorial-plugin\/v1|wp-abilities/.test( u ) && r.request().method() === 'GET' && r.status() < 300 ) {
				const body = await r.text().catch( () => '' );
				if ( body.length < 2_000_000 ) {
					json.push( { url: u.replace( /^https?:\/\/[^/]+/, '' ), body } );
				}
			}
		} );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		out.menu = await page.locator( '#adminmenu > li.menu-top' ).evaluateAll( ( lis ) => lis.map( ( li ) => ( {
			top: li.querySelector( '.wp-menu-name' )?.textContent.trim(),
			items: [ ...li.querySelectorAll( '.wp-submenu a' ) ].map( ( a ) => [ a.textContent.trim(), a.getAttribute( 'href' ) ] ),
		} ) ) );
		const links = [ ...new Set( out.menu.filter( ( m ) => /Мемориал|Каталог|работ|Товар|Отзыв|Заявк/i.test( m.top ) ).flatMap( ( m ) => m.items.map( ( i ) => i[ 1 ] ) ) ) ];
		for ( const href of links ) {
			const url = href.startsWith( 'http' ) || href.startsWith( '/' ) ? href : '/wp-admin/' + href;
			await page.goto( url ).catch( () => {} );
			await page.waitForTimeout( 5000 );
			// раскрыть свёрнутое: «Развернуть все», вступления
			for ( const b of await page.getByRole( 'button', { name: /Развернуть все|Подробнее|Показать/ } ).all() ) {
				await b.click( { timeout: 2000 } ).catch( () => {} );
			}
			await page.waitForTimeout( 800 );
			const tabs = await page.getByRole( 'tab' ).all();
			const texts = [ await page.locator( '#wpbody-content' ).innerText().catch( () => '' ) ];
			for ( const t of tabs ) {
				await t.click( { timeout: 3000 } ).catch( () => {} );
				await page.waitForTimeout( 1200 );
				texts.push( await page.locator( '#wpbody-content' ).innerText().catch( () => '' ) );
			}
			const attrs = await page.evaluate( () => [ ...new Set( [ ...document.querySelectorAll( '#wpbody-content [title], #wpbody-content [aria-label], #wpbody-content [data-hint], #wpbody-content [aria-description]' ) ].map( ( e ) => e.getAttribute( 'data-hint' ) || e.getAttribute( 'aria-description' ) || e.getAttribute( 'title' ) || e.getAttribute( 'aria-label' ) ) ) ] );
			out.screens[ url ] = { title: await page.title(), texts, attrs };
			fs.writeFileSync( `${ DIR }/admin-texts.json`, JSON.stringify( out, null, 1 ) );
		}
		fs.writeFileSync( `${ DIR }/admin-rest.json`, JSON.stringify( json ) );
	} );
} );

test.describe( 'описания и крошки', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'описания и крошки: значения сайта в описаниях, крошки в редакторе', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-types' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } ).catch( () => {} );
		report.types = await page.evaluate( async () => {
			const t = await window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types' } ).catch( ( e ) => ( { e: e.message } ) );
			return JSON.stringify( t ).match( /[^"]{0,80}(\{price:|\[mem_)[^"]{0,80}/g );
		} );
		report.prices = await page.evaluate( async () => {
			const s = await window.wp.apiFetch( { path: '/memorial-plugin/v1/settings/mem_prices' } ).catch( ( e ) => ( { e: e.message } ) );
			return ( s?.value?.other?.items || [] ).map( ( r ) => r.name + ' | ' + r.what + ' | ' + r.section + ' | ' + r.code );
		} );
		const fences = PAGES.find( ( x ) => /оградк/i.test( x.post_title ) );
		if ( fences ) {
			await page.goto( new URL( fences.url ).pathname );
			report.fencesDesc = await page.locator( '.mcat__desc, .cat-desc' ).allInnerTexts().catch( () => [] );
		}
		const faq = PAGES.find( ( x ) => /вопрос/i.test( x.post_title ) );
		if ( faq ) {
			await page.goto( '/wp-admin/post.php?post=' + faq.ID + '&action=edit' );
			await page.waitForTimeout( 9000 );
			const frame = page.frame( { name: 'editor-canvas' } );
			report.crumbsEditor = frame ? await frame.locator( '.crumbs' ).first().innerText().catch( () => '' ) : 'no canvas';
			await shot( page, 'crumbs-editor' ).catch( () => {} );
		}
		log( 'desc', report );
	} );
} );

test.describe( 'отзывы и почта', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'отзывы и почта: окно отзыва, вкладка Rewio, письма', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const errs = errorsOf( page );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/admin.php?page=memorial-plugin-rewio' );
		await page.waitForSelector( '.dataviews-view-table tbody tr', { timeout: 60000 } ).catch( () => {} );
		report.toggleOnList = await page.locator( '.memorial-plugin-collection__toggle' ).count();
		// a review with a first version (changed on the review site), otherwise the first one
		const rows = page.locator( '.dataviews-view-table tbody tr' );
		let row = rows.filter( { hasText: /Изменён/ } ).first();
		if ( ! ( await row.count() ) ) {
			row = rows.first();
		}
		await row.getByRole( 'button', { name: /Изменить/ } ).first().click( { timeout: 8000 } ).catch( async () => {
			await row.hover();
			await row.locator( 'button[aria-label="Действия"]' ).first().click().catch( () => {} );
			await page.getByRole( 'menuitem', { name: /Изменить/ } ).click().catch( () => {} );
		} );
		await page.waitForTimeout( 1500 );
		report.modal = await page.evaluate( () => {
			const c = document.querySelector( '.components-modal__content' );
			const f = document.querySelector( '.components-modal__frame' );
			return c ? { width: f.getBoundingClientRect().width, scrollW: c.scrollWidth, clientW: c.clientWidth, state: [ ...c.querySelectorAll( 'dd' ) ].map( ( d ) => d.textContent.slice( 0, 40 ) ) } : null;
		} );
		await shot( page, 'rv2-modal' );
		await page.keyboard.press( 'Escape' );
		await page.goto( '/wp-admin/admin.php?page=mem-rewio' );
		await page.waitForTimeout( 5000 );
		report.rewioTab = ( await page.locator( '#wpbody-content' ).innerText() ).match( /(3\. Какие[\s\S]{0,900})/ )?.[ 1 ] || '';
		report.notify = await page.getByText( /Уведомлять о новых или удалённых/ ).count();
		report.gone = await page.getByText( /Показывать отзывы, удалённые на площадке/ ).count();
		await shot( page, 'rv2-rewio', { fullPage: true } );
		await page.goto( '/wp-admin/admin.php?page=mem-modules' );
		await page.waitForTimeout( 4000 );
		await page.getByRole( 'tab', { name: /Почта/ } ).click().catch( () => {} );
		await page.waitForTimeout( 1000 );
		report.tabs = await page.getByRole( 'tab' ).allTextContents();
		await page.locator( '.memorial-plugin-settings__note summary' ).click().catch( () => {} );
		await page.waitForTimeout( 500 );
		report.codes = await page.locator( '.memorial-plugin-settings__note' ).innerText().catch( () => '' );
		report.cards = await page.locator( '.memorial-plugin-settings__card-title' ).allTextContents();
		for ( const b of await page.getByRole( 'button', { name: /тестовое письмо/ } ).all() ) {
			await b.click().catch( () => {} );
			await page.waitForTimeout( 3000 );
		}
		report.testNotices = await page.locator( '.memorial-plugin-settings__action .components-notice' ).allTextContents();
		await shot( page, 'rv2-mail', { fullPage: true } );
		report.errors = errs;
		log( 'reviews-mail', report );
	} );
} );

test.describe( 'новая работа', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'новая работа: создаётся только по «Сохранить»', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const errs = errorsOf( page );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_work&page=mem-works' );
		await page.waitForSelector( '.dataviews-view-table tbody tr', { timeout: 60000 } ).catch( () => {} );
		const count = () => page.evaluate( async () => ( await window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/works' } ) ).items.length );
		const pick = async () => {
			await page.getByRole( 'button', { name: /Новая работа из фото/ } ).click();
			await page.locator( '.media-modal' ).waitFor();
			await page.locator( '.media-modal .media-menu-item, .media-modal [id^="menu-item-browse"]' ).filter( { hasText: /Библиотека/ } ).first().click().catch( () => {} );
			await page.locator( '.media-modal li.attachment' ).first().waitFor( { timeout: 30000 } );
			await page.locator( '.media-modal li.attachment' ).nth( 0 ).click();
			await page.locator( '.media-modal li.attachment' ).nth( 1 ).click( { modifiers: [ 'Control' ] } ).catch( () => {} );
			await page.locator( '.media-modal .media-button-select' ).click();
			await page.locator( '.components-modal__frame' ).waitFor();
			await page.waitForTimeout( 800 );
		};
		report.before = await count();
		await pick();
		report.modalTitle = await page.locator( '.components-modal__header h1' ).innerText().catch( () => '' );
		report.photosInModal = await page.locator( '.components-modal__frame img' ).count();
		report.afterOpen = await count();
		await shot( page, 'work-new-modal' );
		await page.getByRole( 'button', { name: 'Отмена' } ).click();
		report.afterCancel = await count();
		log( 'work-new', report );
		await pick();
		await page.locator( '.components-modal__frame input:not([type=checkbox]):not([type=number]):not([type=hidden])' ).first().fill( 'Проверка: новая работа' );
		await page.getByRole( 'button', { name: 'Сохранить' } ).click();
		await page.waitForTimeout( 2500 );
		report.afterSave = await count();
		report.saved = await page.evaluate( async () => ( await window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/works' } ) ).items.filter( ( i ) => /Проверка: новая работа/.test( i.title ) ).map( ( i ) => ( { title: i.title, photos: i.photos.length, year: i.year, status: i.status } ) ) );
		report.notice = await page.locator( '.components-notice' ).allTextContents();
		await shot( page, 'work-new-saved' );
		report.errors = errs;
		log( 'work-new', report );
	} );
} );
