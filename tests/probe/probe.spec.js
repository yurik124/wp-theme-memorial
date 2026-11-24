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

test.describe( 'просмотр', () => {
	test.use( { viewport: { width: 1440, height: 900 } } );
	test( 'просмотр каталога: окно товара и только фото', async ( { page, browser } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const errs = errorsOf( page );
		const login = async () => {
			await page.goto( '/wp-login.php' );
			await page.fill( '#user_login', 'probe' );
			await page.fill( '#user_pass', 'probe-pass-123' );
			await page.click( '#wp-submit' );
			await page.waitForURL( /wp-admin/ );
			await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-types' );
			await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		};
		await login();
		const types = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types' } ) );
		const list = types.types || types.items || types;
		const venki = ( Array.isArray( list ) ? list : [] ).find( ( t ) => /венк/i.test( t.name ) );
		const setView = ( view ) => page.evaluate( ( [ id, v ] ) => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types/' + id, method: 'PUT', data: { values: { orderBtn: true, orderText: 'Заказать', view: v } } } ), [ venki.id, view ] );
		await setView( 'card' );
		await page.context().clearCookies();
		const pg = PAGES.find( ( x ) => /венк/i.test( x.post_title ) );
		const path = new URL( pg.url ).pathname;
		// карточка с несколькими загруженными фото
		const pick = async ( p ) => {
			await p.evaluate( () => document.querySelectorAll( '.mcard img' ).forEach( ( i ) => ( i.loading = 'eager' ) ) );
			await p.waitForTimeout( 3000 );
			return p.evaluate( () => {
				const c = [ ...document.querySelectorAll( '.mcard' ) ];
				const ok = ( x ) => x.querySelector( 'a[data-pv]' ) && x.querySelector( 'img' )?.naturalWidth > 0;
				const k = c.findIndex( ( x ) => ok( x ) && x.querySelectorAll( '.mcard__ph' ).length > 1 );
				return k >= 0 ? k : c.findIndex( ok );
			} );
		};
		const geo = ( p ) => p.evaluate( () => [ '.pv__img', '.pv__title', '.pv__price', '.pv__order', '.pv__desc', '.pv__specs', '.pv__prev', '.pv__arr--r' ].map( ( q ) => {
			const el = document.querySelector( '#pv ' + q );
			const r = el?.getBoundingClientRect();
			return q + ': ' + ( r && r.width ? `x${ Math.round( r.left ) }–${ Math.round( r.right ) } y${ Math.round( r.top ) }–${ Math.round( r.bottom ) }` : 'нет' );
		} ) );
		await page.goto( path );
		await page.addStyleTag( { content: NO_FLOAT } ).catch( () => {} );
		report.cardSpecs = await page.locator( '.mcat__grid .mcard__specs' ).count();
		report.links = await page.locator( 'a[data-pv]' ).count();
		const k = await pick( page );
		report.card = k;
		await page.locator( '.mcard' ).nth( k ).scrollIntoViewIfNeeded();
		await shot( page, 'pv-list' );
		await page.locator( '.mcard' ).nth( k ).locator( 'a[data-pv]' ).click();
		await page.waitForTimeout( 2500 );
		report.cardPc = { cls: await page.locator( '#pv' ).getAttribute( 'class' ), geo: await geo( page ), dots: await page.locator( '#pv .pv__dots i' ).count(), bg: await page.locator( '#pv .pv__box' ).evaluate( ( e ) => getComputedStyle( e ).backgroundColor ) };
		await shot( page, 'pv-card' );
		const t1 = await page.locator( '#pv .pv__title' ).innerText();
		if ( report.cardPc.dots ) {
			await page.locator( '#pv .pv__arr--r' ).click();
			await page.waitForTimeout( 1200 );
			report.photoStepKeepsProduct = ( await page.locator( '#pv .pv__title' ).innerText() ) === t1;
			report.dotOn = await page.locator( '#pv .pv__dots i.is-on' ).evaluate( ( e ) => [ ...e.parentNode.children ].indexOf( e ) );
		}
		await page.locator( '#pv .pv__img' ).click();
		await page.waitForTimeout( 1500 );
		report.lbFromCard = { open: await page.locator( '#lb[open]' ).count(), count: await page.locator( '#lb .lb__count' ).isVisible(), cap: await page.locator( '#lb figcaption' ).innerText() };
		await shot( page, 'pv-card-photos' );
		await page.locator( '#lb .lb__x' ).click();
		await page.waitForTimeout( 600 );
		report.backToCard = await page.locator( '#pv[open]' ).count();
		await page.keyboard.press( 'ArrowRight' );
		await page.waitForTimeout( 1500 );
		report.nextProduct = ( await page.locator( '#pv .pv__title' ).innerText() ) + ' (был ' + t1 + ')';
		await page.locator( '#pv .pv__next' ).click();
		await page.waitForTimeout( 1500 );
		await shot( page, 'pv-card-next' );
		await page.locator( '#pv .pv__order' ).click().catch( () => {} );
		await page.waitForTimeout( 800 );
		report.order = { pvClosed: ! ( await page.locator( '#pv[open]' ).count() ), status: await page.locator( '#callback [data-cb-status]' ).innerText().catch( () => '' ) };
		// телефон: окно товара
		const phone = async ( name ) => {
			const ctx = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, baseURL: 'http://localhost:8888' } );
			const ph = await ctx.newPage();
			await ph.goto( path );
			await ph.addStyleTag( { content: NO_FLOAT } ).catch( () => {} );
			const n = await pick( ph );
			await ph.locator( '.mcard' ).nth( n ).locator( 'a[data-pv]' ).click();
			await ph.waitForTimeout( 2500 );
			report[ name ] = await geo( ph );
			await ph.screenshot( { path: `${ DIR }/${ name }.png` } );
			await ph.locator( '#pv' ).evaluate( ( dd ) => ( dd.scrollTop = 99999 ) );
			await ph.waitForTimeout( 400 );
			await ph.screenshot( { path: `${ DIR }/${ name }-below.png` } );
			await ctx.close();
		};
		await phone( 'pv-card-phone' );
		// «Только фото»
		await login();
		await setView( 'photo' );
		await page.context().clearCookies();
		await page.goto( path );
		await page.addStyleTag( { content: NO_FLOAT } ).catch( () => {} );
		const k2 = await pick( page );
		await page.locator( '.mcard' ).nth( k2 ).locator( 'a[data-pv]' ).click();
		await page.waitForTimeout( 2500 );
		report.photoPc = { cls: await page.locator( '#pv' ).getAttribute( 'class' ), geo: await geo( page ), lbCount: await page.locator( '.lb__count:visible' ).count() };
		await shot( page, 'pv-photo' );
		await page.locator( '#pv .pv__img' ).click();
		await page.waitForTimeout( 600 );
		report.photoClickOpensNothing = ! ( await page.locator( '#lb[open]' ).count() );
		await phone( 'pv-photo-phone' );
		// «Наши работы» и прочие фото — прежний просмотр
		report.errors = errs.filter( ( x ) => ! /404/.test( x ) );
		await login();
		await setView( 'card' );
		log( 'pv', report );
	} );
} );

test.describe( 'анимация', () => {
	test( 'телефон: одна колонка, окно товара при прокрутке, анимация', async ( { page, browser } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-types' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const types = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types' } ) );
		const list = types.types || types.items || types;
		const venki = ( Array.isArray( list ) ? list : [] ).find( ( t ) => /венк/i.test( t.name ) );
		await page.evaluate( ( id ) => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types/' + id, method: 'PUT', data: { values: { colsPhone: 1, orderBtn: true, orderText: 'Заказать', view: 'card' } } } ), venki.id );
		const pg = PAGES.find( ( x ) => /венк/i.test( x.post_title ) );
		const path = new URL( pg.url ).pathname;
		for ( const [ w, h ] of [ [ 390, 844 ], [ 360, 640 ] ] ) {
			const ctx = await browser.newContext( { viewport: { width: w, height: h }, isMobile: true, hasTouch: true, baseURL: 'http://localhost:8888' } );
			const ph = await ctx.newPage();
			await ph.goto( path );
			await ph.waitForTimeout( 2500 );
			await ph.locator( '.consent button' ).first().click().catch( () => {} );
			await ph.addStyleTag( { content: 'html{scroll-behavior:auto!important}' } );
			// карточка под шапкой и строкой разделов: видны ли название, цена, «Заказать»
			for ( let pass = 0; pass < 3; pass++ ) {
				await ph.evaluate( () => {
					document.documentElement.style.scrollBehavior = 'auto';
					const li = document.querySelectorAll( '.mcat__grid > .mcard' )[ 2 ];
					const bar = document.querySelector( '.mcat__bar' ).getBoundingClientRect().bottom;
					window.scrollTo( 0, window.scrollY + li.getBoundingClientRect().top - bar - 6 );
				} );
				await ph.waitForTimeout( 500 );
			}
			await ph.waitForTimeout( 800 );
			report[ `list ${ w }×${ h }` ] = await ph.evaluate( () => {
				const g = document.querySelector( '.mcat__grid' );
				const li = document.querySelectorAll( '.mcat__grid > .mcard' )[ 2 ];
				const r = ( q ) => Math.round( li.querySelector( q )?.getBoundingClientRect().bottom || -1 );
				const mbar = document.querySelector( '.mbar' )?.getBoundingClientRect().top;
				return { dbg: g.dataset.fitDebug, li: Math.round( li.getBoundingClientRect().height ), barB: Math.round( document.querySelector( '.mcat__bar' ).getBoundingClientRect().bottom ), var: g.style.getPropertyValue( '--mcard-ph' ), img: Math.round( li.querySelector( '.mcard__img' ).getBoundingClientRect().height ), name: r( '.mcard__name' ), price: r( '.mcard__price' ), order: r( '.mcard__order' ), mbarTop: Math.round( mbar ), screen: innerHeight };
			} );
			await ph.screenshot( { path: `${ DIR }/anim-list-${ w }.png` } );
			await ph.locator( '.mcat__grid > .mcard' ).nth( 2 ).locator( 'a[data-pv]' ).click();
			await ph.waitForTimeout( 90 );
			report[ `opening ${ w }` ] = await ph.locator( '#pv .pv__box' ).evaluate( ( e ) => getComputedStyle( e ).transform + ' ' + getComputedStyle( e ).animationName );
			await ph.waitForTimeout( 2000 );
			const g = () => ph.evaluate( () => {
				const q = ( x ) => document.querySelector( '#pv ' + x )?.getBoundingClientRect();
				return { img: Math.round( q( '.pv__img' ).height ), price: Math.round( q( '.pv__price' )?.left || 0 ) + '…' + Math.round( q( '.pv__price' )?.right || 0 ), order: Math.round( q( '.pv__order' )?.left || 0 ) + '…' + Math.round( q( '.pv__order' )?.right || 0 ) + ' bottom ' + Math.round( q( '.pv__order' )?.bottom || 0 ), screen: innerHeight };
			} );
			report[ `window ${ w }` ] = await g();
			await ph.screenshot( { path: `${ DIR }/anim-window-${ w }.png` } );
			await ph.locator( '#pv' ).evaluate( ( dd ) => ( dd.scrollTop = 300 ) );
			await ph.setViewportSize( { width: w, height: h + 56 } ); // браузер спрятал адресную строку
			await ph.waitForTimeout( 600 );
			report[ `window ${ w } scrolled` ] = await g();
			await ph.locator( '#pv .pv__x' ).click();
			await ph.waitForTimeout( 60 );
			report[ `closing ${ w }` ] = await ph.locator( '#pv' ).evaluate( ( e ) => e.open + ' ' + e.className );
			await ph.waitForTimeout( 400 );
			report[ `closed ${ w }` ] = await ph.locator( '#pv' ).evaluate( ( e ) => e.open );
			await ctx.close();
		}
		await page.evaluate( ( id ) => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types/' + id, method: 'PUT', data: { values: { colsPhone: 2 } } } ), venki.id );
		// PageSpeed: стили темы встроены, скрипты — сжатые копии, шрифты предзагружены
		await page.context().clearCookies();
		await page.goto( '/' );
		report.speed = await page.evaluate( () => ( {
			inlineCss: [ ...document.querySelectorAll( 'style[id^="memorial-theme"]' ) ].map( ( x ) => x.id + ' ' + x.textContent.length ),
			cssLinks: [ ...document.querySelectorAll( 'link[rel=stylesheet]' ) ].map( ( x ) => x.href.replace( /^.*\/wp-content\//, '' ).replace( /\?.*$/, '' ) ),
			modules: [ ...document.querySelectorAll( 'script[type=module][src]' ) ].map( ( x ) => x.src.replace( /^.*\/wp-content\//, '' ).replace( /\?.*$/, '' ) ),
			preload: [ ...document.querySelectorAll( 'link[rel=preload]' ) ].map( ( x ) => x.href.replace( /^.*\//, '' ) ),
			fontsOk: document.fonts.status,
			brokenImgs: [ ...document.images ].filter( ( i ) => i.complete && ! i.naturalWidth && i.src ).map( ( i ) => decodeURI( i.src ) ),
		} ) );
		await shot( page, 'speed-home' );
		log( 'anim', report );
	} );

	test( 'телефон-карточки: скругление фото, ширина карточки, крестик, запас снизу', async ( { page, browser } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-types' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const types = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types' } ) );
		const list = types.types || types.items || types;
		const venki = ( Array.isArray( list ) ? list : [] ).find( ( t ) => /венк/i.test( t.name ) );
		const setType = ( values ) => page.evaluate( ( [ id, v ] ) => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types/' + id, method: 'PUT', data: { values: v } } ), [ venki.id, values ] );
		const pg = PAGES.find( ( x ) => /венк/i.test( x.post_title ) );
		const path = new URL( pg.url ).pathname;
		// карточка: рамка фото держит пропорцию и скруглена, карточка и «Заказать» — по ширине фото
		const cardInfo = ( ph ) => ph.evaluate( () => {
			const li = document.querySelectorAll( '.mcat__grid > .mcard' )[ 2 ];
			const box = li.querySelector( '.mcard__img' ).getBoundingClientRect();
			const link = li.querySelector( '.mcard__link' ).getBoundingClientRect();
			const order = li.querySelector( '.mcard__order' )?.getBoundingClientRect();
			const cs = getComputedStyle( li.querySelector( '.mcard__img' ) );
			const img = li.querySelector( '.mcard__img img.is-on' ) || li.querySelector( '.mcard__img img' );
			return {
				ph: document.querySelector( '.mcat__grid' ).style.getPropertyValue( '--mcard-ph' ),
				box: Math.round( box.width ) + '×' + Math.round( box.height ) + ' ratio ' + ( box.width / box.height ).toFixed( 3 ),
				photo: img ? img.naturalWidth + '×' + img.naturalHeight : '-',
				radius: cs.borderRadius + ' ' + cs.overflow,
				gapL: Math.round( box.left - link.left ),
				gapR: Math.round( link.right - box.right ),
				card: Math.round( link.left ) + '…' + Math.round( link.right ),
				order: order ? Math.round( order.left ) + '…' + Math.round( order.right ) + ' h' + Math.round( order.height ) : '-',
				screen: innerWidth,
			};
		} );
		const scrollTo3 = async ( ph ) => {
			for ( let pass = 0; pass < 3; pass++ ) {
				await ph.evaluate( () => {
					document.documentElement.style.scrollBehavior = 'auto';
					const li = document.querySelectorAll( '.mcat__grid > .mcard' )[ 2 ];
					const bar = document.querySelector( '.mcat__bar' ).getBoundingClientRect().bottom;
					window.scrollTo( 0, window.scrollY + li.getBoundingClientRect().top - bar - 6 );
				} );
				await ph.waitForTimeout( 500 );
			}
		};
		for ( const cols of [ 1, 2 ] ) {
			await setType( { colsPhone: cols, orderBtn: true, orderText: 'Заказать', view: 'card' } );
			for ( const [ w, h ] of [ [ 390, 844 ], [ 360, 640 ] ] ) {
				const ctx = await browser.newContext( { viewport: { width: w, height: h }, isMobile: true, hasTouch: true, baseURL: 'http://localhost:8888' } );
				const ph = await ctx.newPage();
				const errs = errorsOf( ph );
				await ph.goto( path );
				await ph.waitForTimeout( 2500 );
				await ph.locator( '.consent button' ).first().click().catch( () => {} );
				await scrollTo3( ph );
				await ph.waitForTimeout( 800 );
				report[ `card ${ cols }col ${ w }×${ h }` ] = await cardInfo( ph );
				report[ `card ${ cols }col ${ w } bottoms` ] = await ph.evaluate( () => {
					const li = document.querySelectorAll( '.mcat__grid > .mcard' )[ 2 ];
					const b = ( q ) => Math.round( li.querySelector( q )?.getBoundingClientRect().bottom || -1 );
					return { name: b( '.mcard__name' ), price: b( '.mcard__price' ), order: b( '.mcard__order' ), mbarTop: Math.round( document.querySelector( '.mbar' )?.getBoundingClientRect().top || -1 ) };
				} );
				await ph.screenshot( { path: `${ DIR }/pp-list-${ cols }col-${ w }.png` } );
				if ( cols === 1 ) {
					await ph.locator( '.mcat__grid > .mcard' ).nth( 2 ).locator( 'a[data-pv]' ).click();
					await ph.waitForTimeout( 1500 );
					report[ `close ${ w }` ] = await ph.locator( '#pv .pv__x' ).evaluate( ( e ) => {
						const c = getComputedStyle( e );
						const r = e.getBoundingClientRect();
						return c.backgroundColor + ' | ' + c.color + ' | ' + c.borderTopColor + ' | ' + c.boxShadow.slice( 0, 60 ) + ' | ' + Math.round( r.width ) + '×' + Math.round( r.height );
					} );
					await ph.screenshot( { path: `${ DIR }/pp-window-top-${ w }.png` } );
					// в середину — текст под крестиком
					await ph.locator( '#pv' ).evaluate( ( dd ) => ( dd.scrollTop = Math.max( 0, ( dd.scrollHeight - dd.clientHeight ) / 2 ) ) );
					await ph.waitForTimeout( 400 );
					await ph.screenshot( { path: `${ DIR }/pp-window-mid-${ w }.png` } );
					await ph.locator( '#pv' ).evaluate( ( dd ) => ( dd.scrollTop = dd.scrollHeight ) );
					await ph.waitForTimeout( 400 );
					report[ `window bottom ${ w }` ] = await ph.evaluate( () => {
						const rows = document.querySelectorAll( '#pv .pv__specs > div' );
						const last = rows[ rows.length - 1 ] || document.querySelector( '#pv .pv__main' ).lastElementChild;
						const r = last.getBoundingClientRect();
						return { lastBottom: Math.round( r.bottom ), screen: innerHeight, free: Math.round( innerHeight - r.bottom ), pad: getComputedStyle( document.querySelector( '#pv .pv__box' ) ).paddingBottom };
					} );
					await ph.screenshot( { path: `${ DIR }/pp-window-bottom-${ w }.png` } );
					await ph.locator( '#pv .pv__x' ).click();
					await ph.waitForTimeout( 400 );
					// «Только фото»: тот же запас снизу
					await setType( { view: 'photo' } );
					await ph.reload();
					await ph.waitForTimeout( 2000 );
					await scrollTo3( ph );
					await ph.locator( '.mcat__grid > .mcard' ).nth( 2 ).locator( 'a[data-pv]' ).click();
					await ph.waitForTimeout( 1500 );
					await ph.locator( '#pv' ).evaluate( ( dd ) => ( dd.scrollTop = dd.scrollHeight ) );
					await ph.waitForTimeout( 400 );
					report[ `photo bottom ${ w }` ] = await ph.evaluate( () => {
						const rows = document.querySelectorAll( '#pv .pv__specs > div' );
						const last = rows[ rows.length - 1 ];
						return last ? Math.round( innerHeight - last.getBoundingClientRect().bottom ) : 'нет характеристик';
					} );
					await ph.screenshot( { path: `${ DIR }/pp-photo-bottom-${ w }.png` } );
					await setType( { view: 'card' } );
				}
				report[ `errors ${ cols }col ${ w }` ] = errs;
				await ctx.close();
			}
		}
		// ПК — как было
		const ctx = await browser.newContext( { viewport: { width: 1440, height: 900 }, baseURL: 'http://localhost:8888' } );
		const pc = await ctx.newPage();
		await pc.goto( path );
		await pc.waitForTimeout( 2500 );
		await pc.locator( '.consent button' ).first().click().catch( () => {} );
		report[ 'card pc' ] = await cardInfo( pc );
		await pc.screenshot( { path: `${ DIR }/pp-list-pc.png` } );
		await pc.locator( '.mcat__grid > .mcard' ).nth( 2 ).locator( 'a[data-pv]' ).click();
		await pc.waitForTimeout( 1500 );
		report[ 'close pc' ] = await pc.locator( '#pv .pv__x' ).evaluate( ( e ) => getComputedStyle( e ).backgroundColor + ' | ' + getComputedStyle( e ).color );
		await pc.screenshot( { path: `${ DIR }/pp-window-pc.png` } );
		await ctx.close();
		await setType( { colsPhone: 2 } );
		log( 'phone-polish', report );
	} );

	test( 'артикул и выгрузка: перенос, таблица товаров, Excel и CSV', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 30000 );
		const report = {};
		const errs = errorsOf( page );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-products' ).catch( () => {} );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const data = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/products' } ) );
		report.products = data.items.length;
		report.withSku = data.items.filter( ( i ) => i.sku ).length;
		report.skuIsId = data.items.filter( ( i ) => i.sku === 'M-' + i.id ).length;
		report.dupSku = data.items.length - new Set( data.items.map( ( i ) => i.sku ) ).size;
		// чужой артикул не сохраняется
		const [ a, b ] = data.items;
		report.taken = await page.evaluate( ( [ id, sku ] ) => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/products/' + id, method: 'POST', data: { values: { sku } } } ).then( () => 'saved', ( e ) => e.code + ' ' + e.message ), [ b.id, a.sku ] );
		for ( const format of [ 'xlsx', 'csv' ] ) {
			report[ format ] = await page.evaluate( ( f ) => window.wp.apiFetch( { path: '/wp-abilities/v1/abilities/memorial-plugin/export-catalog/run?input[format]=' + f } ).then( ( r ) => ( { filename: r.filename, count: r.count, mime: r.mime, size: r.file.length, head: f === 'csv' ? r.csv.split( '\r\n' )[ 0 ].slice( 0, 400 ) : atob( r.file ).slice( 0, 2 ) } ), ( e ) => e.message ), format );
		}
		await page.waitForTimeout( 2000 );
		await page.screenshot( { path: `${ DIR }/sku-list.png`, fullPage: false } );
		await page.getByRole( 'button', { name: /Выгрузить|Export/ } ).first().click().catch( ( e ) => ( report.dropdown = e.message ) );
		await page.waitForTimeout( 500 );
		await page.screenshot( { path: `${ DIR }/sku-export-menu.png` } );
		const dl = page.waitForEvent( 'download', { timeout: 60000 } ).catch( () => null );
		await page.getByRole( 'menuitem', { name: /Весь каталог — Excel|Whole catalog — Excel/ } ).click().catch( ( e ) => ( report.menuitem = e.message ) );
		const file = await dl;
		report.download = file ? file.suggestedFilename() : 'нет';
		if ( file ) {
			await file.saveAs( `${ DIR }/sku-catalog.xlsx` );
		}
		report.errors = errs;
		log( 'sku-export', report );
	} );

	test( 'импорт: весь каталог туда и обратно без изменений, пример ZIP через окно, откат', async ( { page } ) => {
		test.setTimeout( 900_000 );
		page.setDefaultTimeout( 60000 );
		const report = {};
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-products' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		// Выгрузка всего каталога → проверка импорта этим же файлом: ничего не должно меняться.
		for ( const format of [ 'xlsx', 'csv' ] ) {
			report[ 'roundtrip ' + format ] = await page.evaluate( async ( f ) => {
				const exp = await window.wp.apiFetch( { path: '/wp-abilities/v1/abilities/memorial-plugin/export-catalog/run?input[format]=' + f } );
				const bin = atob( exp.file );
				const bytes = new Uint8Array( bin.length );
				for ( let i = 0; i < bin.length; i++ ) {
					bytes[ i ] = bin.charCodeAt( i );
				}
				const body = new FormData();
				body.append( 'table', new Blob( [ bytes ] ), exp.filename );
				body.append( 'files', '[]' );
				const t0 = performance.now();
				const res = await window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/import/preview', method: 'POST', body } );
				return {
					exported: exp.count,
					ms: Math.round( performance.now() - t0 ),
					count: res.count,
					unknown: res.unknown,
					notSame: res.rows.filter( ( r ) => r.action !== 'same' ).slice( 0, 15 ).map( ( r ) => r.n + ' ' + r.sku + ' ' + r.title + ' | ' + r.action + ' | ' + r.changes.join( ',' ) + ' | ' + r.errors.join( ' ' ) ),
				};
			}, format );
		}
		// Пример ZIP — через окно, как сделает владелец.
		const zipB64 = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/import/sample?format=zip' } ).then( ( r ) => r.file ) );
		await page.getByRole( 'button', { name: /^(Импорт|Import)$/ } ).click();
		const modal = page.locator( '.memorial-plugin-import' );
		await modal.waitFor();
		await page.waitForTimeout( 500 );
		await page.screenshot( { path: `${ DIR }/import-1-files.png` } );
		await modal.locator( 'input[type=file]' ).first().setInputFiles( { name: 'catalog-import-example.zip', mimeType: 'application/zip', buffer: Buffer.from( zipB64, 'base64' ) } );
		await page.waitForTimeout( 1000 );
		report.files = await modal.locator( '.memorial-plugin-import__files' ).innerText();
		await page.screenshot( { path: `${ DIR }/import-2-chosen.png` } );
		await modal.getByRole( 'button', { name: /Проверить файл|Check the file/ } ).click();
		await page.waitForTimeout( 3000 );
		report.preview = await modal.locator( '.components-notice' ).first().innerText().catch( () => '' );
		await page.screenshot( { path: `${ DIR }/import-3-preview.png` } );
		await page.setViewportSize( { width: 782, height: 900 } );
		await page.waitForTimeout( 500 );
		await page.screenshot( { path: `${ DIR }/import-3-preview-782.png` } );
		log( 'import', report );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await modal.getByRole( 'button', { name: /Импортировать|Import \d/ } ).click();
		await modal.getByRole( 'button', { name: /Откатить этот импорт|Roll back this import/ } ).waitFor( { timeout: 180000 } );
		report.done = await modal.locator( '.components-notice' ).first().innerText().catch( () => '' );
		await page.screenshot( { path: `${ DIR }/import-4-done.png` } );
		report.created = await page.evaluate( async () => {
			const d = await window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/products' } );
			const items = d.items.filter( ( i ) => /^OBR-/.test( i.sku ) );
			const out = [];
			for ( const i of items ) {
				const alts = [];
				for ( const ph of i.photos ) {
					const m = await window.wp.apiFetch( { path: '/wp/v2/media/' + ph.id + '?context=edit' } );
					alts.push( m.alt_text + ' / ' + m.title.raw );
				}
				out.push( i.sku + ' ' + i.status + ' ' + i.price + ' ' + i.title + ' | ' + JSON.stringify( i.specs ) + ' | ' + alts.join( ' ; ' ) );
			}
			return out;
		} );
		// Повторная проверка того же архива: фото не загружаются заново.
		log( 'import', report );
		await modal.getByRole( 'button', { name: /Закрыть|Close/ } ).last().click();
		await page.getByRole( 'button', { name: /^(Импорт|Import)$/ } ).click();
		await modal.locator( 'input[type=file]' ).first().setInputFiles( { name: 'catalog-import-example.zip', mimeType: 'application/zip', buffer: Buffer.from( zipB64, 'base64' ) } );
		await page.waitForTimeout( 800 );
		await modal.getByRole( 'button', { name: /Проверить файл|Check the file/ } ).click();
		await page.waitForTimeout( 3000 );
		report.again = await modal.locator( '.components-notice' ).first().innerText().catch( () => '' );
		await page.screenshot( { path: `${ DIR }/import-5-again.png` } );
		// Откат из окна (последний импорт).
		await modal.getByRole( 'button', { name: /Назад|Back/ } ).click();
		await modal.getByRole( 'button', { name: /Откатить последний импорт|Roll back the last import/ } ).click();
		await page.waitForTimeout( 3000 );
		report.rollback = await modal.locator( '.components-notice' ).first().innerText().catch( () => '' );
		report.afterRollback = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/products' } ).then( ( d ) => d.items.filter( ( i ) => /^OBR-/.test( i.sku ) ).length ) );
		await page.screenshot( { path: `${ DIR }/import-6-rollback.png` } );
		report.errors = errs;
		log( 'import', report );
	} );
} );

test.describe( 'хаб', () => {
	test( 'хаб вопросов: разделы, свой раздел, FAQPage, редактор, llms.txt', async ( { page, browser } ) => {
		test.setTimeout( 900_000 );
		page.setDefaultTimeout( 60000 );
		const report = {};
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const hub = PAGES.find( ( x ) => /Вопрос/.test( x.post_title ) && /ответ/.test( x.post_title ) );
		report.hub = hub && hub.ID + ' ' + hub.post_title;
		const hubPath = new URL( hub.url ).pathname;
		// Свой раздел на странице «Вопрос–ответ»: H2 и два вопроса.
		const det = ( q, a ) => `<!-- wp:details -->\n<details class="wp-block-details"><summary>${ q }</summary><!-- wp:paragraph -->\n<p>${ a }</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n`;
		report.saved = await page.evaluate( async ( [ id, add ] ) => {
			const p = await window.wp.apiFetch( { path: '/wp/v2/pages/' + id + '?context=edit' } );
			const r = await window.wp.apiFetch( { path: '/wp/v2/pages/' + id, method: 'POST', data: { content: p.content.raw + add, meta: { _mem_faq_sections: JSON.stringify( { order: [ 'h:oplata' ], items: {} } ) } } } );
			return r.meta?._mem_faq_sections;
		}, [ hub.ID, '\n<!-- wp:heading {"anchor":"oplata"} -->\n<h2 class="wp-block-heading" id="oplata">Оплата</h2>\n<!-- /wp:heading -->\n' + det( 'Можно ли оплатить картой?', 'Да, картой, наличными и переводом.' ) + det( 'Нужна ли предоплата?', 'Предоплата не нужна, оплата после оказания услуг.' ) ] );
		for ( const [ w, h ] of [ [ 1440, 900 ], [ 390, 844 ], [ 360, 640 ] ] ) {
			const ctx = await browser.newContext( { viewport: { width: w, height: h }, isMobile: w < 500, hasTouch: w < 500, baseURL: 'http://localhost:8888' } );
			const ph = await ctx.newPage();
			const e2 = errorsOf( ph );
			await ph.goto( hubPath );
			await ph.waitForTimeout( 2000 );
			await ph.locator( '.consent button' ).first().click().catch( () => {} );
			report[ 'hub ' + w ] = await ph.evaluate( () => ( {
				chips: [ ...document.querySelectorAll( '.mfaq__toc .chip' ) ].map( ( c ) => c.textContent.trim() ),
				sections: [ ...document.querySelectorAll( '.mfaq__group' ) ].map( ( g ) => g.id + ' | ' + g.querySelector( 'h2' ).textContent ),
				oldIds: document.querySelectorAll( '[id^="faq-"]' ).length,
				ownTwice: document.body.innerHTML.split( 'Можно ли оплатить картой?' ).length - 1,
				readMore: document.querySelectorAll( '.mfaq__qmore a' ).length,
				ownCount: document.querySelectorAll( '#oplata details' ).length,
				// ответы длиннее первого абзаца (у них должна быть «Подробнее»)
				longer: [ ...document.querySelectorAll( '.mfaq details[data-t]' ) ].filter( ( d ) => d.dataset.t.length > [ ...d.children ].filter( ( c ) => c.tagName !== 'SUMMARY' && ! c.classList.contains( 'mfaq__qmore' ) ).map( ( c ) => c.textContent ).join( ' ' ).trim().length + 10 ).length,
				firstMore: document.querySelector( '.mfaq__qmore a' )?.getAttribute( 'href' ),
				faqpage: [ ...document.querySelectorAll( 'script[type="application/ld+json"]' ) ].map( ( x ) => x.textContent ).filter( ( t ) => t.includes( 'FAQPage' ) ).map( ( t ) => JSON.parse( t ).mainEntity.map( ( q ) => q.name ) ),
				sw: document.documentElement.scrollWidth,
				cw: document.documentElement.clientWidth,
			} ) );
			await ph.screenshot( { path: `${ DIR }/hub-${ w }.png` } );
			if ( w === 1440 ) {
				// Поиск находит свой вопрос и полный текст ответа раздела страницы.
				await ph.fill( '#mfaq-q', 'предоплата' );
				await ph.waitForTimeout( 800 );
				report.search = await ph.evaluate( () => ( {
					none: ! document.querySelector( '[data-msearch-none]' )?.hidden,
					shown: [ ...document.querySelectorAll( '.mfaq details' ) ].filter( ( d ) => d.getClientRects().length ).map( ( d ) => d.querySelector( 'summary' ).textContent ).slice( 0, 5 ),
				} ) );
				await ph.screenshot( { path: `${ DIR }/hub-search.png` } );
				// «Подробнее» ведёт к вопросу на его странице, и он раскрыт.
				const href = report[ 'hub 1440' ].firstMore;
				if ( href ) {
					await ph.goto( href );
					await ph.waitForTimeout( 1500 );
					report.readMoreTarget = await ph.evaluate( () => {
						const el = document.getElementById( decodeURIComponent( location.hash.slice( 1 ) ) );
						return el ? el.tagName + ' open=' + el.open : 'нет элемента ' + location.hash;
					} );
					await ph.screenshot( { path: `${ DIR }/hub-readmore.png` } );
					report.sourceFaqpage = await ph.evaluate( () => [ ...document.querySelectorAll( 'script[type="application/ld+json"]' ) ].filter( ( x ) => x.textContent.includes( 'FAQPage' ) ).length );
				}
			}
			report[ 'errors ' + w ] = e2;
			await ctx.close();
		}
		// Окно «Разделы вопросов» в редакторе страницы.
		await page.goto( '/wp-admin/post.php?post=' + hub.ID + '&action=edit' );
		await page.waitForTimeout( 8000 );
		report.panels = await page.locator( '.editor-sidebar .components-panel__body-title' ).allInnerTexts().catch( ( e ) => e.message );
		await page.locator( '.components-modal__header button[aria-label]' ).first().click().catch( () => {} );
		const panel = page.getByRole( 'button', { name: /Разделы вопросов|Question sections/ } ).first();
		await panel.click().catch( ( e ) => ( report.panel = e.message ) );
		await page.waitForTimeout( 500 );
		await page.getByRole( 'button', { name: /Настроить разделы|Set up the sections/ } ).click().catch( ( e ) => ( report.setup = e.message ) );
		await page.waitForTimeout( 3000 );
		report.modal = await page.locator( '.memorial-plugin-faq-sections' ).innerText().catch( ( e ) => e.message );
		report.modal = String( report.modal ).slice( 0, 1200 );
		await page.screenshot( { path: `${ DIR }/hub-editor.png` } );
		// llms.txt: сначала текст из WordPress, после WP-Cron — файл в корне.
		const llms1 = await page.request.get( '/llms.txt' );
		report.llms = { status: llms1.status(), type: llms1.headers()[ 'content-type' ], head: ( await llms1.text() ).split( '\n' ).slice( 0, 60 ).join( '\n' ) };
		await page.request.get( '/wp-cron.php?doing_wp_cron=' + Date.now() ).catch( () => {} );
		await page.waitForTimeout( 70000 );
		await page.request.get( '/wp-cron.php?doing_wp_cron=' + Date.now() ).catch( () => {} );
		await page.waitForTimeout( 5000 );
		const llms2 = await page.request.get( '/llms.txt' );
		report.llms2 = { status: llms2.status(), type: llms2.headers()[ 'content-type' ], len: ( await llms2.text() ).length };
		report.errors = errs;
		log( 'hub', report );
	} );
} );

test.describe( 'правка в таблице', () => {
	test( 'описание товара в HTML: окно товара, поисковики и диктор', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const save = () => fs.writeFileSync( `${ DIR }/log-desc.json`, JSON.stringify( report, null, 1 ) );
		const errs = errorsOf( page );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-types' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const r = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/types' } ) );
		const types = Array.isArray( r ) ? r : r.types || [];
		report.mode = await page.evaluate( () => window.wp.apiFetch( { path: '/wp/v2/settings' } ).then( ( x ) => x.mem_product_pages ).catch( () => null ) );
		report.types = types.map( ( t ) => [ t.name, t.view, t.page && t.page.url ] );
		const t = types.find( ( x ) => x.view === 'card' && x.page && ! x.page.draft ) || types.find( ( x ) => x.page && ! x.page.draft );
		report.type = t && [ t.name, t.view, t.page.url ];
		const items = ( await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/products' } ) ) ).items;
		report.withDesc = items.filter( ( i ) => i.type === t.id && String( i.desc || '' ).trim() ).map( ( i ) => ( { id: i.id, title: i.title, desc: String( i.desc ).slice( 0, 60 ), descLen: String( i.desc ).length } ) );
		save();
		await page.context().clearCookies();
		const url = t.page.url + ( t.page.url.includes( '?' ) ? '&' : '?' ) + 'nocache=' + Date.now();
		// Исходный HTML — как его получает поисковый робот, без JavaScript.
		const html = await ( await page.request.get( url ) ).text();
		const descs = [ ...html.matchAll( /<div class="pv__desc">([\s\S]*?)<\/div>/g ) ].map( ( m ) => m[ 1 ].replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim() );
		report.raw = {
			template: ( html.match( /<template class="mcard__info"/g ) || [] ).length,
			infoHidden: ( html.match( /<div class="mcard__info" hidden/g ) || [] ).length,
			descs: descs.length,
			firstDesc: descs[ 0 ] ? descs[ 0 ].slice( 0, 80 ) : null,
			pvTitle: ( html.match( /pv__title/g ) || [] ).length,
		};
		const sentence = descs[ 0 ] ? descs[ 0 ].split( '.' )[ 0 ] : '';
		report.raw.sentenceInHtml = sentence ? html.split( sentence ).length - 1 : null;
		report.raw.sentenceInSaved = sentence ? report.withDesc.length && items.filter( ( i ) => String( i.desc || '' ).includes( sentence ) ).map( ( i ) => String( i.desc ).split( sentence ).length - 1 ) : null;
		save();
		const snippet = descs[ 0 ] ? descs[ 0 ].slice( 0, 30 ) : null;
		for ( const w of [ 1440, 390, 360 ] ) {
			const o = {};
			await page.setViewportSize( { width: w, height: w > 500 ? 900 : 800 } );
			await page.goto( url );
			const cookie = page.getByRole( 'button', { name: /Принять/ } );
			if ( await cookie.count() ) {
				await cookie.first().click().catch( () => {} );
			}
			// товар с описанием (или первый в окне)
			const found = snippet
				? page.locator( '.mcard', { has: page.locator( '.mcard__info .pv__desc', { hasText: snippet } ) } ).first()
				: page.locator( '.mcard', { has: page.locator( 'a[data-pv]' ) } ).first();
			// карточка — по id: пока описание в окне, «карточка с описанием» не находится
			const li = page.locator( '#' + ( await found.getAttribute( 'id' ) ) );
			o.infoVisibleBefore = await li.locator( '.mcard__info' ).isVisible();
			o.descVisibleBefore = snippet ? await page.getByText( snippet ).first().isVisible() : null;
			// дерево доступности: скрытое описание диктор не видит
			o.cardTreeBefore = await li.ariaSnapshot();
			o.a11yCardBefore = o.cardTreeBefore.includes( 'голубых хризантем' );
			o.visibleWithText = snippet ? await page.getByText( snippet ).evaluateAll( ( els ) => els.filter( ( e ) => e.getClientRects().length ).map( ( e ) => e.className || e.tagName ) ) : null;
			await li.locator( 'a[data-pv]' ).first().scrollIntoViewIfNeeded();
			await li.locator( 'a[data-pv]' ).first().click();
			const pv = page.locator( 'dialog.pv[open]' );
			await pv.waitFor();
			await page.waitForTimeout( 700 );
			o.title = await pv.locator( '.pv__title' ).innerText().catch( () => null );
			o.price = await pv.locator( '.pv__price' ).innerText().catch( () => null );
			o.order = await pv.locator( '.pv__order' ).count();
			o.descInModal = await pv.locator( '.pv__desc' ).isVisible().catch( () => false );
			o.specsInModal = await pv.locator( '.pv__specs' ).count();
			o.infoLeftInCard = await li.locator( '.mcard__info > *' ).count();
			o.descCopiesOpen = snippet ? await page.getByText( snippet ).count() : null;
			o.a11yOpen = ( await pv.ariaSnapshot() ).includes( 'голубых хризантем' );
			o.a11yCardOpen = ( await li.ariaSnapshot() ).includes( 'голубых хризантем' );
			o.focusInDialog = await page.evaluate( () => !! document.activeElement?.closest( 'dialog.pv' ) );
			await page.screenshot( { path: `${ DIR }/desc-${ w }-open.png` } );
			if ( await pv.locator( '.pv__next' ).isVisible() ) {
				await pv.locator( '.pv__next' ).click();
				await page.waitForTimeout( 500 );
				o.afterNextBackInCard = await li.locator( '.mcard__info > *' ).count();
				await pv.locator( '.pv__prev' ).click();
				await page.waitForTimeout( 500 );
				o.afterPrevInModal = await pv.locator( '.pv__desc' ).count();
			}
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 600 );
			o.closed = ! ( await pv.count() );
			o.infoBackInCard = await li.locator( '.mcard__info > *' ).count();
			o.descVisibleAfter = snippet ? await page.getByText( snippet ).first().isVisible() : null;
			o.descCopiesAfter = snippet ? await page.getByText( snippet ).count() : null;
			o.a11yCardAfter = ( await li.ariaSnapshot() ).includes( 'голубых хризантем' );
			report[ 'w' + w ] = o;
			save();
		}
		report.errors = errs;
		save();
	} );

	test( 'коды характеристик: {spec:…} в названии товара', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const save = () => fs.writeFileSync( `${ DIR }/log-spec.json`, JSON.stringify( report, null, 1 ) );
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-types' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const fetchApi = ( o ) => page.evaluate( ( x ) => window.wp.apiFetch( x ), o );
		const r = await fetchApi( { path: '/memorial-plugin/v1/catalog/types' } );
		const types = Array.isArray( r ) ? r : r.types || [];
		report.codes = types.map( ( t ) => [ t.name, ( t.fields || [] ).map( ( f ) => f.label + ' → ' + f.code ) ] );
		const all = ( await fetchApi( { path: '/memorial-plugin/v1/catalog/products' } ) ).items;
		// товар на сайте с заполненной характеристикой у типа со страницей
		let pick = null;
		for ( const t of types ) {
			if ( ! t.page || t.page.draft ) {
				continue;
			}
			for ( const f of t.fields || [] ) {
				const p = all.find( ( i ) => i.type === t.id && i.status === 'publish' && i.specs && String( i.specs[ f.label ] || '' ).trim() );
				if ( p ) {
					pick = { t, f, p };
					break;
				}
			}
			if ( pick ) {
				break;
			}
		}
		report.pick = pick && { type: pick.t.name, spec: pick.f.label, code: pick.f.code, unit: pick.f.unit, value: pick.p.specs[ pick.f.label ], title: pick.p.title, url: pick.t.page.url };
		save();
		// страница типов: коды у характеристик
		await page.getByRole( 'tab', { name: new RegExp( pick.t.name.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) ) } ).click();
		const card = page.locator( '.memorial-plugin-catalog__spec' ).first();
		await card.scrollIntoViewIfNeeded();
		await page.mouse.wheel( 0, -150 );
		await page.screenshot( { path: `${ DIR }/spec-1-types.png` } );
		// название с кодом
		const newTitle = pick.p.title + ' {spec:' + pick.f.code + '}';
		const res = await fetchApi( { path: '/memorial-plugin/v1/catalog/products/' + pick.p.id, method: 'PUT', data: { values: { title: newTitle } } } );
		report.saved = { title: res.title, shown: res.shown };
		save();
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-products&type=' + pick.t.id );
		await page.waitForTimeout( 4000 );
		const row = page.getByRole( 'row' ).filter( { hasText: newTitle } ).first();
		report.tableShown = await row.locator( '.memorial-plugin-catalog__shown' ).innerText().catch( () => null );
		await row.scrollIntoViewIfNeeded();
		await page.screenshot( { path: `${ DIR }/spec-2-table.png` } );
		save();
		// сайт: карточка в каталоге и крупный просмотр
		for ( const w of [ 1440, 390, 360 ] ) {
			await page.setViewportSize( { width: w, height: w > 500 ? 900 : 800 } );
			await page.goto( pick.t.page.url + ( pick.t.page.url.includes( '?' ) ? '&' : '?' ) + 'nocache=' + Date.now() );
			const name = page.locator( '.mcard__name', { hasText: res.shown } ).first();
			report[ 'site' + w ] = {
				cardFound: await name.count(),
				codeLeft: await page.locator( 'body' ).evaluate( ( b ) => b.innerHTML.includes( '{spec:' ) ),
			};
			if ( await name.count() ) {
				await name.scrollIntoViewIfNeeded();
				await page.screenshot( { path: `${ DIR }/spec-3-site-${ w }.png` } );
			}
			save();
		}
		report.errors = errs;
		save();
	} );

	test( 'таблица товаров: галочка «На сайте», отмена щелчком мимо', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-products' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const all = () => page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/products' } ) );
		const data = await all();
		const venki = data.types.find( ( t ) => /венк/i.test( t.name ) );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-products&type=' + venki.id );
		await page.waitForTimeout( 4000 );
		report.checkboxes = await page.locator( '.memorial-plugin-inline__toggle input[type=checkbox]' ).count();
		await page.screenshot( { path: `${ DIR }/check-1-table.png` } );
		const row = page.getByRole( 'row' ).filter( { has: page.locator( '.memorial-plugin-inline__toggle' ) } ).first();
		const box = row.locator( '.memorial-plugin-inline__toggle input[type=checkbox]' );
		report.label = await box.getAttribute( 'aria-label' );
		report.sku = ( ( await row.innerText() ).match( /M-\d+/ ) || [] )[ 0 ];
		const st = async () => ( await all() ).items.find( ( i ) => i.sku === report.sku )?.status;
		const save = () => fs.writeFileSync( `${ DIR }/log-check.json`, JSON.stringify( report, null, 1 ) );
		report.before = await st();
		await box.click();
		await page.waitForTimeout( 2500 );
		report.afterClick = await st();
		await page.screenshot( { path: `${ DIR }/check-2-toggled.png` } );
		await box.click();
		await page.waitForTimeout( 2500 );
		report.afterBack = await st();
		save();
		// Щелчок мимо поля — отмена.
		await row.getByRole( 'button', { name: /Изменить: Заголовок|Change: Title/ } ).click();
		const input = page.locator( '.memorial-plugin-inline__form input' );
		await input.fill( 'ПРОБА — не сохранять' );
		report.formButtons = await page.locator( '.memorial-plugin-inline__form button' ).count();
		await page.screenshot( { path: `${ DIR }/check-3-editing.png` } );
		await page.locator( '#wpfooter' ).click( { position: { x: 4, y: 4 } } );
		await page.waitForTimeout( 800 );
		report.formAfterOutside = await input.count();
		report.titleAfter = ( await all() ).items.find( ( i ) => i.sku === report.sku )?.title;
		save();
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.waitForTimeout( 800 );
		await page.screenshot( { path: `${ DIR }/check-4-phone-390.png` } );
		report.errors = errs;
		save();
	} );

	test( 'таблица товаров щелчком и «Вопрос–ответ»: поиск по разделу, стрелка, свёрнутые разделы', async ( { page, browser } ) => {
		test.setTimeout( 900_000 );
		page.setDefaultTimeout( 20000 );
		const report = {};
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		// Таблица товаров: тип «Венки».
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-products' );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch, null, { timeout: 60000 } );
		const data = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/catalog/products' } ) );
		const venki = data.types.find( ( t ) => /венк/i.test( t.name ) );
		await page.goto( '/wp-admin/edit.php?post_type=mem_product&page=mem-products&type=' + venki.id );
		await page.waitForTimeout( 4000 );
		const row = page.getByRole( 'row' ).filter( { hasText: '₽' } ).nth( 1 );
		report.title = await row.innerText().catch( ( e ) => e.message );
		await page.screenshot( { path: `${ DIR }/inline-1-table.png` } );
		const price = row.locator( '.memorial-plugin-inline__value' ).filter( { hasText: /\d ₽/ } ).first();
		await price.hover().catch( () => {} );
		await page.screenshot( { path: `${ DIR }/inline-2-hover.png` } );
		await price.click().catch( ( e ) => ( report.priceClick = e.message ) );
		await page.waitForTimeout( 400 );
		await page.screenshot( { path: `${ DIR }/inline-3-price-edit.png` } );
		const input = row.locator( '.memorial-plugin-inline__form input' );
		const old = await input.inputValue().catch( () => '' );
		await input.fill( String( ( parseInt( old, 10 ) || 1000 ) + 100 ) ).catch( () => {} );
		await input.press( 'Enter' ).catch( () => {} );
		await page.waitForTimeout( 2500 );
		report.priceAfter = await row.innerText().catch( ( e ) => e.message );
		await page.screenshot( { path: `${ DIR }/inline-4-saved.png` } );
		// Категории — маленькое окно.
		const cats = row.locator( '.memorial-plugin-inline__value' ).nth( 3 );
		await cats.click().catch( () => {} );
		await page.waitForTimeout( 600 );
		report.pop = await page.locator( '.memorial-plugin-inline__pop' ).innerText().catch( ( e ) => e.message );
		await page.screenshot( { path: `${ DIR }/inline-5-popover.png` } );
		await page.keyboard.press( 'Escape' );
		report.cells = await page.locator( '.memorial-plugin-inline__value' ).count();
		// «Вопрос–ответ»: свой раздел, поиск по названию раздела, стрелка.
		const hub = PAGES.find( ( x ) => /Вопрос/.test( x.post_title ) && /ответ/.test( x.post_title ) );
		const det = ( q, a ) => `<!-- wp:details -->\n<details class="wp-block-details"><summary>${ q }</summary><!-- wp:paragraph -->\n<p>${ a }</p>\n<!-- /wp:paragraph --></details>\n<!-- /wp:details -->\n`;
		await page.evaluate( async ( [ id, add ] ) => {
			const p = await window.wp.apiFetch( { path: '/wp/v2/pages/' + id + '?context=edit' } );
			await window.wp.apiFetch( { path: '/wp/v2/pages/' + id, method: 'POST', data: { content: p.content.raw + add, meta: { _mem_faq_sections: JSON.stringify( { order: [], items: { p3302: { more: 'Об организации похорон →' } } } ) } } } );
		}, [ hub.ID, '\n<!-- wp:heading -->\n<h2 class="wp-block-heading">Оплата</h2>\n<!-- /wp:heading -->\n' + det( 'Можно ли наличными?', 'Да.' ) ] );
		for ( const [ w, h ] of [ [ 1440, 900 ], [ 390, 844 ], [ 360, 640 ] ] ) {
			const ctx = await browser.newContext( { viewport: { width: w, height: h }, isMobile: w < 500, hasTouch: w < 500, baseURL: 'http://localhost:8888' } );
			const ph = await ctx.newPage();
			ph.setDefaultTimeout( 15000 );
			await ph.goto( new URL( hub.url ).pathname );
			await ph.waitForTimeout( 2000 );
			await ph.locator( '.consent button' ).first().click().catch( () => {} );
			await ph.fill( '#mfaq-q', 'оплата' );
			await ph.waitForTimeout( 900 );
			report[ 'search ' + w ] = await ph.evaluate( () => [ ...document.querySelectorAll( '.mfaq details' ) ].filter( ( d ) => d.getClientRects().length ).map( ( d ) => d.querySelector( 'summary' ).textContent ) );
			await ph.screenshot( { path: `${ DIR }/inline-hub-search-${ w }.png` } );
			report[ 'more ' + w ] = await ph.evaluate( () => [ ...document.querySelectorAll( '.mfaq__more a' ) ].slice( 0, 3 ).map( ( a ) => a.textContent ) );
			await ctx.close();
		}
		// Окно «Настроить разделы»: свёрнуто.
		await page.goto( '/wp-admin/post.php?post=' + hub.ID + '&action=edit' );
		await page.waitForTimeout( 8000 );
		const panelBtn = page.locator( '.components-panel__body-toggle' ).filter( { hasText: /Разделы вопросов|Question sections/ } );
		if ( ( await panelBtn.getAttribute( 'aria-expanded' ).catch( () => 'true' ) ) === 'false' ) {
			await panelBtn.click().catch( () => {} );
		}
		await page.getByRole( 'button', { name: /Настроить разделы|Set up the sections/ } ).click().catch( ( e ) => ( report.setup = e.message ) );
		await page.waitForTimeout( 2500 );
		report.fieldsClosed = await page.locator( '.memorial-plugin-faq-sections__fields' ).count();
		await page.screenshot( { path: `${ DIR }/inline-sections-closed.png` } );
		await page.locator( '.memorial-plugin-faq-sections__toggle' ).nth( 1 ).click().catch( () => {} );
		await page.waitForTimeout( 400 );
		report.fieldsOpen = await page.locator( '.memorial-plugin-faq-sections__fields' ).count();
		await page.screenshot( { path: `${ DIR }/inline-sections-open.png` } );
		report.errors = errs;
		log( 'inline', report );
	} );
} );

test.describe( 'ширина', () => {
	test( 'стили: правка шапки меняет только подменю последних пунктов', async () => {
		test.setTimeout( 900_000 );
		const report = {};
		const browser = await require( '@playwright/test' ).chromium.launch( { ignoreDefaultArgs: [ '--hide-scrollbars' ] } );
		// старое поведение: подменю от левого края (right:0 оставалось от слабого правила), без страховки
		const OLD = '.header .nav.wp-block-navigation>ul>li.has-child:nth-last-child(-n+2)>.wp-block-navigation__submenu-container{left:0;right:0}.header .nav.wp-block-navigation>ul>li.has-child:nth-last-child(-n+2) .wp-block-navigation__submenu-container .wp-block-navigation__submenu-container{left:100%;right:auto}.header{overflow-x:visible}';
		const PROPS = [ 'display', 'position', 'left', 'right', 'top', 'width', 'height', 'margin', 'padding', 'overflow-x', 'overflow-y', 'visibility', 'opacity', 'font-size', 'color', 'background-color', 'flex-wrap', 'order', 'transform' ];
		const take = ( page ) =>
			page.evaluate( ( props ) => {
				const out = {};
				let i = 0;
				for ( const el of document.querySelectorAll( 'body *' ) ) {
					const cs = getComputedStyle( el );
					const key = i++ + ' ' + el.tagName.toLowerCase() + '.' + String( el.className ).trim().split( /\s+/ ).slice( 0, 3 ).join( '.' );
					out[ key ] = props.map( ( p ) => p + ':' + cs.getPropertyValue( p ) ).join( ';' );
				}
				return out;
			}, PROPS );
		const pages = [ '/', '/venki/', '/kontakty/', '/poleznoe/vopros-otvet/', '/ceny/' ];
		for ( const [ w, h, mob ] of [ [ 1280, 900 ], [ 1440, 900 ], [ 390, 844, true ], [ 360, 640, true ] ] ) {
			for ( const narrow of mob ? [ false ] : [ false, true ] ) {
				const ctx = await browser.newContext( { viewport: { width: w, height: h }, isMobile: !! mob, hasTouch: !! mob, baseURL: 'http://localhost:8888' } );
				const page = await ctx.newPage();
				page.setDefaultTimeout( 10000 );
				if ( narrow ) {
					await page.addInitScript( () => document.addEventListener( 'DOMContentLoaded', () => {
						const st = document.createElement( 'style' );
						st.textContent = '.header .nav>ul>li>a{letter-spacing:-.07em}';
						document.head.append( st );
					} ) );
				}
				for ( const path of pages ) {
					await page.goto( path, { waitUntil: 'domcontentloaded' } ).catch( () => {} );
					await page.waitForTimeout( 2000 );
					const after = await take( page );
					const swAfter = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
					await page.addStyleTag( { content: OLD } );
					await page.waitForTimeout( 200 );
					const before = await take( page );
					const swBefore = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
					const diff = Object.keys( after ).filter( ( k ) => after[ k ] !== before[ k ] ).map( ( k ) => {
						const a = after[ k ].split( ';' );
						const b = before[ k ].split( ';' );
						return k.replace( /^\d+ /, '' ) + ' → ' + a.filter( ( x, j ) => x !== b[ j ] ).join( '; ' );
					} );
					report[ `${ w }${ narrow ? ' две строки' : '' } ${ path }` ] = {
						mode: await page.evaluate( () => document.getElementById( 'header' )?.className ),
						overflowBefore: swBefore,
						overflowAfter: swAfter,
						changed: [ ...new Set( diff ) ].slice( 0, 12 ),
						changedCount: diff.length,
					};
					log( 'styles', report );
				}
				await ctx.close();
			}
		}
		await browser.close();
		log( 'styles', report );
	} );

	test( 'ширина ПК: шапка в две строки (как со шрифтом Windows)', async () => {
		test.setTimeout( 900_000 );
		const report = {};
		const browser = await require( '@playwright/test' ).chromium.launch( { ignoreDefaultArgs: [ '--hide-scrollbars' ] } );
		for ( const w of [ 1229, 1280, 1366, 1440, 1536, 1600 ] ) {
			const ctx = await browser.newContext( { viewport: { width: w, height: 800 }, baseURL: 'http://localhost:8888' } );
			const page = await ctx.newPage();
			page.setDefaultTimeout( 10000 );
			page.setDefaultNavigationTimeout( 45000 );
			// Segoe UI уже шрифта Linux: сужаем пункты меню, чтобы скрипт шапки выбрал две строки, как у владельца
			await page.addInitScript( () => {
				document.addEventListener( 'DOMContentLoaded', () => {
					const st = document.createElement( 'style' );
					st.textContent = '.header .nav>ul>li>a{letter-spacing:-.07em}';
					document.head.append( st );
				} );
			} );
			for ( const path of [ '/venki/', '/', '/kontakty/' ] ) {
				await page.goto( path, { waitUntil: 'domcontentloaded' } ).catch( () => {} );
				await page.waitForTimeout( 2000 );
				const r = await page.evaluate( () => {
					const de = document.documentElement;
					const h = document.getElementById( 'header' );
					const last = document.querySelector( '.header .nav>ul>li.has-child:last-child>.wp-block-navigation__submenu-container' );
					const lr = last?.getBoundingClientRect();
					return {
						mode: h.classList.contains( 'is-compact' ) ? 'compact' : h.classList.contains( 'is-tworow' ) ? 'two rows' : 'one row',
						sw: de.scrollWidth,
						cw: de.clientWidth,
						lastSub: lr ? Math.round( lr.left ) + '…' + Math.round( lr.right ) : '-',
					};
				} );
				report[ w + ' ' + path ] = r;
				log( 'header2', report );
				if ( path === '/venki/' ) {
					await page.locator( '.header .nav>ul>li.has-child' ).last().hover().catch( () => {} );
					await page.waitForTimeout( 500 );
					await page.screenshot( { path: `${ DIR }/hdr2-${ w }.png`, clip: { x: 0, y: 0, width: w, height: 520 } } );
				}
			}
			await ctx.close();
		}
		// Телефон: меню бургера открывается целиком
		const ctx = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, baseURL: 'http://localhost:8888' } );
		const ph = await ctx.newPage();
		ph.setDefaultTimeout( 10000 );
		await ph.goto( '/venki/', { waitUntil: 'domcontentloaded' } ).catch( () => {} );
		await ph.waitForTimeout( 2000 );
		await ph.locator( '.consent button' ).first().click().catch( () => {} );
		await ph.locator( '#burger' ).click().catch( ( e ) => ( report.burger = e.message ) );
		await ph.waitForTimeout( 600 );
		report.phoneMenu = await ph.evaluate( () => {
			const n = document.querySelector( '.header .nav' ).getBoundingClientRect();
			return Math.round( n.left ) + '…' + Math.round( n.right ) + ' h=' + Math.round( n.height ) + ' sw=' + document.documentElement.scrollWidth;
		} );
		await ph.screenshot( { path: `${ DIR }/hdr2-phone-menu.png` } );
		await ctx.close();
		await browser.close();
		log( 'header2', report );
	} );

	test( 'ширина ПК: горизонтальная прокрутка', async () => {
		test.setTimeout( 900_000 );
		const report = {};
		// Настоящие полосы прокрутки, как в Windows: по умолчанию headless Chromium их прячет, и 100vw не вылезает.
		const browser = await require( '@playwright/test' ).chromium.launch( { ignoreDefaultArgs: [ '--hide-scrollbars' ] } );
		const paths = [ '/venki/', '/pamyatniki/', '/', '/poleznoe/vopros-otvet/', '/kontakty/', '/ceny/', '/ritualnye-uslugi/' ];
		// 1229 и 1536 — ноутбуки Windows с масштабом 125 %
		for ( const w of [ 1024, 1100, 1229, 1280, 1366, 1440, 1536, 1600, 1920 ] ) {
			const ctx = await browser.newContext( { viewport: { width: w, height: 900 }, baseURL: 'http://localhost:8888' } );
			const page = await ctx.newPage();
			for ( const path of paths ) {
				await page.goto( path, { waitUntil: 'load' } ).catch( () => {} );
				await page.waitForTimeout( 400 );
				const r = await page.evaluate( () => {
					const de = document.documentElement;
					const cw = de.clientWidth;
					// Windows: полоса прокрутки ~17 px съедает ширину, а 100vw её не учитывает
					const win = cw === window.innerWidth ? cw - 17 : cw;
					const path = ( el ) => {
						const out = [];
						for ( let e = el; e && e !== document.body && out.length < 4; e = e.parentElement ) {
							out.unshift( e.tagName.toLowerCase() + ( e.id ? '#' + e.id : '' ) + ( e.className && typeof e.className === 'string' ? '.' + e.className.trim().split( /\s+/ ).slice( 0, 2 ).join( '.' ) : '' ) );
						}
						return out.join( ' > ' );
					};
					const over = [];
					const overWin = [];
					for ( const el of document.body.querySelectorAll( '*' ) ) {
						const cs = getComputedStyle( el );
						if ( cs.position === 'fixed' || cs.display === 'none' || el.closest( 'dialog:not([open])' ) ) {
							continue;
						}
						const rc = el.getBoundingClientRect();
						if ( ! rc.width ) {
							continue;
						}
						if ( rc.right > cw + 0.5 ) {
							over.push( path( el ) + ' right=' + Math.round( rc.right ) + ' w=' + Math.round( rc.width ) );
						} else if ( rc.right > win + 0.5 ) {
							overWin.push( path( el ) + ' right=' + Math.round( rc.right ) + ' w=' + Math.round( rc.width ) + ' css-w=' + cs.width );
						}
					}
					return { sw: de.scrollWidth, cw, iw: window.innerWidth, over: over.slice( 0, 6 ), overWin: overWin.slice( 0, 6 ) };
				} ).catch( ( e ) => ( { error: e.message } ) );
				if ( r.error || r.sw > r.cw || path === '/venki/' ) {
					report[ w + ' ' + path ] = r;
				}
			}
			await ctx.close();
		}
		await browser.close();
		log( 'width', report );
	} );
} );

test.describe( 'адреса и SEO', () => {
	test( 'адреса и SEO: старые адреса, title, RSS, листание', async ( { page, browser } ) => {
		test.setTimeout( 600_000 );
		const report = { codes: {}, titles: {}, errs: [] };
		const save = () => fs.writeFileSync( `${ DIR }/log-seo.json`, JSON.stringify( report, null, 1 ) );
		// Ответ без перехода по редиректу — как его видит поисковый робот.
		const head = async ( p ) => {
			const r = await page.request.get( p, { maxRedirects: 0 } ).catch( ( e ) => ( { error: e.message } ) );
			if ( r.error ) {
				return { error: r.error };
			}
			const html = r.status() === 200 && /html/.test( r.headers()[ 'content-type' ] || '' ) ? await r.text() : '';
			return {
				code: r.status(),
				to: ( r.headers().location || '' ).replace( /^https?:\/\/[^/]+/, '' ),
				title: ( html.match( /<title>([^<]*)<\/title>/ ) || [] )[ 1 ] || '',
				robots: ( html.match( /<meta[^>]+name=['"]robots['"][^>]+content=['"]([^'"]+)/ ) || [] )[ 1 ] || '',
				feedLinks: ( html.match( /application\/rss\+xml/g ) || [] ).length,
				ogradki2: ( html.match( /\/ogradki-2\//g ) || [] ).length,
				posobieTop: ( html.match( /["'(]\/posobie-na-pogrebenie\//g ) || [] ).length + ( html.match( /localhost:8888\/posobie-na-pogrebenie\//g ) || [] ).length,
				crumbs: ( html.match( /<nav class="crumbs"[\s\S]*?<\/nav>/ ) || [] )[ 0 ]?.replace( /<[^>]+>/g, '' ).replace( /\s+/g, ' ' ).trim() || '',
			};
		};
		const paths = [
			'/', '/index.html', '/indexdata/index.swf', '/politika-ispolzovaniya-cookie/', '/politika-cookie/', '/kresty/',
			'/ogradki/', '/ogradki-2/', '/posobie-na-pogrebenie/', '/venki/page/2/', '/pamyatniki/page/3/?sort=new',
			'/feed/', '/comments/feed/', '/kontakty/feed/', '/catalog/', '/?author=1',
			'/ritualnye-uslugi/', '/ritualnye-uslugi/kremacziya/', '/ritualnye-uslugi/gruz-200/', '/ritualnye-uslugi/traurnyj-zal/',
			'/venki/', '/groby/', '/pamyatniki/', '/metallicheskie-kresty/', '/blagoustrojstvo-zahoroneniya/',
			'/blagoustrojstvo-zahoroneniya/ukladka-plitki-na-kladbishhe-v-belgorode/', '/fotogalereia/', '/kontakty/', '/ceny/', '/otzyvy/',
			'/ritualnye-tovary/', '/pomoshh-v-organizaczii-pohoron/', '/chto-delat-esli-umer-chelovek/', '/poleznoe/', '/poleznoe/vopros-otvet/',
			'/poleznoe/posobie-na-pogrebenie/', '/poleznoe/kladbishha/', '/ritualnye-uslugi/pominalnyj-obed/',
			'/ritualnye-uslugi/pohorony-uchastnikov-svo/', '/ritualnye-uslugi/prizhiznennyj-dogovor/', '/soglasie-na-obrabotku-personalnyh-dannyh/',
		];
		for ( const p of paths ) {
			report.codes[ p ] = await head( p );
			save();
		}
		// Ссылки, оставшиеся на старые адреса, по всем страницам.
		report.leftovers = Object.entries( report.codes ).filter( ( [ , v ] ) => v.ogradki2 || v.posobieTop ).map( ( [ k, v ] ) => [ k, v.ogradki2, v.posobieTop ] );
		save();
		// Снимки «Оградок» по новому адресу: ПК и телефон.
		const errs = errorsOf( page );
		for ( const [ w, h, name ] of [ [ 1440, 900, 'pc' ], [ 390, 844, 'phone' ], [ 360, 780, 'phone360' ] ] ) {
			await page.setViewportSize( { width: w, height: h } );
			await page.goto( '/ogradki/', { waitUntil: 'networkidle' } );
			await page.addStyleTag( { content: NO_FLOAT } );
			await shot( page, `seo-ogradki-${ name }` );
		}
		// Редактор страницы: панели «Товары на странице» больше нет, ошибок нет.
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const fences = PAGES.find( ( x ) => /^ogradki/.test( x.post_name ) );
		if ( fences ) {
			await page.goto( '/wp-admin/post.php?action=edit&post=' + fences.ID );
			await page.waitForSelector( '.editor-sidebar, .interface-complementary-area', { timeout: 60000 } ).catch( () => {} );
			await page.waitForTimeout( 3000 );
			report.editor = await page.evaluate( () => ( {
				panels: [ ...document.querySelectorAll( '.components-panel__body-title' ) ].map( ( b ) => b.textContent.trim() ),
				oldPanel: /Товары на странице|Products on the page/.test( document.body.innerText ),
			} ) );
			await shot( page, 'seo-ogradki-editor' );
		}
		report.errs = errs;
		save();
	} );
} );

test.describe( 'условия и отзывы', () => {
	test( 'условия и отзывы: оплата, доставка, гарантии, отзывы страниц, шапка у окна товара', async ( { page, browser } ) => {
		test.setTimeout( 900_000 );
		page.setDefaultTimeout( 20000 );
		const report = { pages: {}, header: {}, errs: [] };
		const save = () => fs.writeFileSync( `${ DIR }/log-terms.json`, JSON.stringify( report, null, 1 ) );
		const errs = errorsOf( page );
		const paths = [ '/venki/', '/groby/', '/pamyatniki/', '/ogradki/', '/ceny/', '/kontakty/', '/ritualnye-uslugi/', '/ritualnye-uslugi/kremacziya/', '/ritualnye-uslugi/traurnyj-zal/', '/ritualnye-uslugi/gruz-200/', '/', '/poleznoe/vopros-otvet/' ];
		for ( const [ w, h, name ] of [ [ 1440, 900, 'pc' ], [ 390, 844, 'phone' ], [ 360, 780, 'phone360' ] ] ) {
			await page.setViewportSize( { width: w, height: h } );
			for ( const p of paths ) {
				await page.goto( p, { waitUntil: 'networkidle' } );
				await page.addStyleTag( { content: NO_FLOAT } );
				const info = await page.evaluate( () => ( {
					title: document.title,
					cards: [ ...document.querySelectorAll( '.mterms' ) ].map( ( c ) => [ c.className, c.querySelector( '.mterms__title' )?.textContent.trim(), c.querySelectorAll( 'li' ).length, Math.round( c.getBoundingClientRect().width ) ] ),
					reviews: document.querySelectorAll( '.rwo' ).length,
					reviewCards: document.querySelectorAll( '.rwo__card' ).length,
					reviewTexts: [ ...document.querySelectorAll( '.rwo__card .rwo__text' ) ].slice( 0, 3 ).map( ( t ) => t.textContent.trim().slice( 0, 80 ) ),
					minFont: Math.min( ...[ ...document.querySelectorAll( '.mterms li, .mterms p' ) ].map( ( e ) => parseFloat( getComputedStyle( e ).fontSize ) ), 99 ),
					faq: [ ...document.querySelectorAll( 'summary' ) ].map( ( s ) => s.textContent.trim() ).filter( ( t ) => /оплат|доставк|гаранти/i.test( t ) ),
					overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
				} ) );
				report.pages[ name + ' ' + p ] = info;
				const first = page.locator( '.mterms' ).first();
				if ( await first.count() ) {
					await first.scrollIntoViewIfNeeded();
					await page.waitForTimeout( 300 );
					const box = page.locator( '.mterms' );
					const n = await box.count();
					for ( let i = 0; i < n; i++ ) {
						await box.nth( i ).screenshot( { path: `${ DIR }/terms-${ name }-${ p.replace( /\W+/g, '_' ) }-${ i }.png` } ).catch( () => {} );
					}
				}
				if ( [ '/venki/', '/pamyatniki/' ].includes( p ) && name !== 'phone360' ) {
					const rwo = page.locator( '.rwo' ).first();
					if ( await rwo.count() ) {
						await rwo.scrollIntoViewIfNeeded();
						await page.waitForTimeout( 300 );
						await shot( page, `terms-reviews-${ name }-${ p.replace( /\W+/g, '_' ) }` );
					}
				}
				save();
			}
			// Шапка при открытом окне товара: страница прокручена вниз, открыто окно.
			await page.goto( '/venki/', { waitUntil: 'networkidle' } );
			await page.addStyleTag( { content: NO_FLOAT } );
			const link = page.locator( 'a[data-pv]' ).nth( 3 );
			if ( await link.count() ) {
				await link.scrollIntoViewIfNeeded();
				await page.mouse.wheel( 0, 400 );
				await page.waitForTimeout( 500 );
				const before = await page.evaluate( () => ( { y: window.scrollY, header: document.querySelector( '#header' )?.getBoundingClientRect().toJSON(), cw: document.documentElement.clientWidth } ) );
				await link.click();
				await page.waitForTimeout( 900 );
				const open = await page.evaluate( () => ( { y: window.scrollY, header: document.querySelector( '#header' )?.getBoundingClientRect().toJSON(), cw: document.documentElement.clientWidth, dialog: !! document.querySelector( 'dialog.pv[open]' ) } ) );
				await shot( page, `terms-pv-open-${ name }` );
				await page.keyboard.press( 'Escape' );
				await page.waitForTimeout( 600 );
				const after = await page.evaluate( () => ( { y: window.scrollY, header: document.querySelector( '#header' )?.getBoundingClientRect().toJSON(), cw: document.documentElement.clientWidth } ) );
				report.header[ name ] = { before, open, after };
				save();
			}
		}
		// robots.txt и список отзывов для выбора в редакторе.
		report.robots = await ( await page.request.get( '/robots.txt' ) ).text();
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const venki = PAGES.find( ( x ) => x.post_name === 'venki' );
		await page.goto( '/wp-admin/post.php?action=edit&post=' + venki.ID );
		await page.waitForFunction( () => window.wp && window.wp.apiFetch && window.wp.blocks, null, { timeout: 90000 } );
		await page.waitForTimeout( 4000 );
		report.editor = await page.evaluate( async () => {
			const pick = await window.wp.apiFetch( { path: '/memorial-plugin/v1/reviews/pick' } ).catch( ( e ) => ( { error: e.message } ) );
			const vars = ( window.wp.blocks.getBlockVariations( 'core/group' ) || [] ).filter( ( v ) => /^memorial-/.test( v.name ) ).map( ( v ) => [ v.name, v.title, ( v.innerBlocks || [] ).length ] );
			const ed = window.wp.data.select( 'core/block-editor' );
			const blocks = ed.getClientIdsWithDescendants().map( ( id ) => ed.getBlock( id ) ).filter( ( b ) => b && ( /^memorial-plugin\//.test( b.name ) || /mterms/.test( b.attributes.className || '' ) ) ).map( ( b ) => b.name + ( b.attributes.className ? '.' + b.attributes.className : '' ) );
			return { pickCount: Array.isArray( pick ) ? pick.length : pick, pickFirst: Array.isArray( pick ) ? pick.slice( 0, 2 ) : null, vars, blocks, payment: !! window.wp.blocks.getBlockType( 'memorial-plugin/payment' ) };
		} );
		await shot( page, 'terms-editor' );
		// Блок отзывов выбран — настройки справа (слова, «Выбрать отзывы», книга отзывов).
		await page.evaluate( () => {
			const ids = window.wp.data.select( 'core/block-editor' ).getBlocksByName( 'memorial-plugin/rewio-reviews' );
			if ( ids.length ) {
				window.wp.data.dispatch( 'core/block-editor' ).selectBlock( ids[ 0 ] );
				window.wp.data.dispatch( 'core/edit-post' )?.openGeneralSidebar?.( 'edit-post/block' );
			}
		} );
		await page.waitForTimeout( 1500 );
		await shot( page, 'terms-editor-reviews' );
		const choose = page.getByRole( 'button', { name: /Выбрать отзывы|Choose reviews/ } );
		if ( await choose.count() ) {
			await choose.first().click();
			await page.waitForTimeout( 2500 );
			await shot( page, 'terms-editor-reviews-modal' );
			report.modal = await page.evaluate( () => document.querySelectorAll( '.memorial-plugin-reviews-picker__list input[type=checkbox]' ).length );
		}
		report.errs = errs;
		save();
	} );
} );

test.describe( 'выбор отзывов', () => {
	test( 'выбор отзывов: окно с галочками в редакторе', async ( { page } ) => {
		test.setTimeout( 300_000 );
		const report = {};
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		const venki = PAGES.find( ( x ) => x.post_name === 'venki' );
		await page.goto( '/wp-admin/post.php?action=edit&post=' + venki.ID );
		await page.waitForFunction( () => window.wp && window.wp.data && window.wp.blocks, null, { timeout: 90000 } );
		// Без приветственного окна редактора.
		await page.evaluate( () => window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ) );
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 3000 );
		await page.evaluate( () => {
			const ids = window.wp.data.select( 'core/block-editor' ).getBlocksByName( 'memorial-plugin/rewio-reviews' );
			if ( ids.length ) {
				window.wp.data.dispatch( 'core/block-editor' ).selectBlock( ids[ 0 ] );
			}
		} );
		await page.waitForTimeout( 1500 );
		const choose = page.getByRole( 'button', { name: /Выбрать отзывы|Choose reviews/ } ).first();
		await choose.click();
		await page.waitForSelector( '.memorial-plugin-reviews-picker__list input[type=checkbox]', { timeout: 30000 } ).catch( () => {} );
		report.boxes = await page.locator( '.memorial-plugin-reviews-picker__list input[type=checkbox]' ).count();
		await page.locator( '.memorial-plugin-reviews-picker__list input[type=checkbox]' ).nth( 1 ).check().catch( ( e ) => ( report.err1 = e.message ) );
		await page.locator( '.memorial-plugin-reviews-picker__list input[type=checkbox]' ).nth( 0 ).check().catch( ( e ) => ( report.err2 = e.message ) );
		await page.getByRole( 'searchbox' ).first().fill( 'оградк' ).catch( () => {} );
		await page.waitForTimeout( 500 );
		report.found = await page.locator( '.memorial-plugin-reviews-picker__list input[type=checkbox]' ).count();
		await shot( page, 'pick-modal' );
		await page.getByRole( 'button', { name: /Готово|Done/ } ).click();
		await page.waitForTimeout( 2500 );
		report.pick = await page.evaluate( () => {
			const ids = window.wp.data.select( 'core/block-editor' ).getBlocksByName( 'memorial-plugin/rewio-reviews' );
			return ids.length ? window.wp.data.select( 'core/block-editor' ).getBlockAttributes( ids[ 0 ] ).pick : null;
		} );
		await shot( page, 'pick-sidebar' );
		report.errs = errs;
		fs.writeFileSync( `${ DIR }/log-pick.json`, JSON.stringify( report, null, 1 ) );
	} );
} );

test.describe( 'антиспам и услуги', () => {
	test( 'антиспам и услуги: форма из окна, комментарии, XML-RPC, «Наши услуги»', async ( { page } ) => {
		test.setTimeout( 600_000 );
		page.setDefaultTimeout( 60000 );
		const report = {};
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( '/', { waitUntil: 'domcontentloaded' } ).then( () => page.waitForTimeout( 1500 ) );
		await page.addStyleTag( { content: NO_FLOAT } );
		report.homeCards = await page.evaluate( () => [ ...document.querySelectorAll( '.svc__item h3' ) ].map( ( h ) => h.textContent.trim() ) );
		await page.goto( '/ritualnye-uslugi/', { waitUntil: 'domcontentloaded' } ).then( () => page.waitForTimeout( 1500 ) );
		report.uslugiCards = await page.evaluate( () => [ ...document.querySelectorAll( '.svc__item h3' ) ].map( ( h ) => h.textContent.trim() ) );
		report.titles = {};
		for ( const p of [ '/', '/ritualnye-uslugi/', '/pomoshh-v-organizaczii-pohoron/', '/ceny/', '/groby/' ] ) {
			const html = await ( await page.request.get( p ) ).text();
			report.titles[ p ] = [ ( html.match( /<title>([^<]*)<\/title>/ ) || [] )[ 1 ], ( html.match( /<meta name="description" content="([^"]*)"/ ) || [] )[ 1 ] ];
		}
		// Заявка из окна, как посетитель: открыть, подождать, заполнить, отправить.
		await page.locator( '[data-callback]:visible' ).first().click();
		await page.waitForTimeout( 3500 );
		await page.fill( '#callback input[name=phone]', '9001234567' );
		await page.fill( '#callback input[name=name]', 'Проверка probe' );
		await page.check( '#callback input[name=consent]' );
		const resp = page.waitForResponse( ( r ) => r.url().includes( '/mem/v1/callback' ) && r.request().method() === 'POST' );
		await page.click( '#callback [type=submit]' );
		const r = await resp;
		report.formStatus = r.status();
		report.formDone = await page.locator( '#callback .cb__done' ).isVisible();
		await shot( page, 'spam-form-done' );
		// Бот без окна: заявка сохраняется с пометкой.
		const bot = await page.request.post( '/wp-json/mem/v1/callback', { data: { phone: '+7 900 765-43-21', consent: 1, name: 'buy http://x.example' } } );
		report.botStatus = bot.status();
		report.xmlrpc = ( await page.request.post( '/xmlrpc.php', { data: '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>', maxRedirects: 0 } ) ).status();
		report.comment = ( await page.request.post( '/wp-comments-post.php', { form: { comment_post_ID: '1', comment: 'spam', author: 'x', email: 'x@example.org' }, maxRedirects: 0 } ) ).status();
		report.pingback = ( await page.request.get( '/', { maxRedirects: 0 } ) ).headers()[ 'x-pingback' ] || '';
		// Список заявок: пометка «Проверить».
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/admin.php?page=memorial-plugin-leads' );
		await page.waitForTimeout( 4000 );
		await shot( page, 'spam-leads' );
		report.leadsText = ( await page.locator( 'body' ).innerText() ).match( /Проверка probe|buy http|Отправлено не из окна|Ссылка или код|Не из окна[^\n]*/g );
		report.errs = errs;
		fs.writeFileSync( `${ DIR }/log-spam.json`, JSON.stringify( report, null, 1 ) );
	} );
} );

test.describe( 'обработка фото', () => {
	test( 'обработка фото: экран с предпросмотром, фото каталога после обработки', async ( { page } ) => {
		test.setTimeout( 900_000 );
		page.setDefaultTimeout( 60000 );
		const report = {};
		const errs = errorsOf( page );
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'probe' );
		await page.fill( '#user_pass', 'probe-pass-123' );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
		await page.goto( '/wp-admin/admin.php?page=mem-photos' );
		await page.waitForSelector( '.memorial-plugin-photo-preview canvas, .memorial-plugin-photo-preview .components-notice', { timeout: 90000 } ).catch( () => {} );
		await page.waitForTimeout( 2500 );
		await shot( page, 'photos-screen', { fullPage: true } );
		report.samples = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/photos/samples' } ).then( ( d ) => ( { items: d.items.length, all: d.all.length, logo: d.logo, kinds: d.items.map( ( i ) => i.kind + ':' + i.type + ( i.bg ? ' bg' : '' ) ) } ) ).catch( ( e ) => e.message ) );
		// Главный переключатель: выключить — все поля ниже неактивны; снимок; включить обратно.
		const master = page.locator( '.memorial-plugin-settings__master input[type=checkbox]' );
		report.master = { exists: await master.count(), on: await master.isChecked().catch( () => null ) };
		await master.click().catch( ( e ) => ( report.masterErr = e.message ) );
		await page.waitForTimeout( 800 );
		report.master.offDisabled = await page.evaluate( () => document.querySelector( '.memorial-plugin-settings__cards' )?.disabled );
		report.master.offFieldDisabled = await page.getByLabel( 'Цветокоррекция', { exact: true } ).isDisabled().catch( ( e ) => e.message );
		await shot( page, 'photos-screen-off', { fullPage: true } );
		await master.click().catch( () => {} );
		await page.waitForTimeout( 800 );
		report.master.onEnabled = ! ( await page.evaluate( () => document.querySelector( '.memorial-plugin-settings__cards' )?.disabled ) );
		// Тёмная подложка логотипа: включить — картинка логотипа делается заново (вариант «-p»).
		await page.getByLabel( 'Тёмная подложка под логотипом', { exact: true } ).check().catch( ( e ) => ( report.plateErr = e.message ) );
		await page.waitForTimeout( 3000 );
		const pbox = await page.locator( '.memorial-plugin-photo-preview' ).boundingBox();
		if ( pbox ) {
			await page.screenshot( { path: `${ DIR }/photos-plate.png`, clip: { x: pbox.x, y: pbox.y, width: pbox.width, height: Math.min( pbox.height, 1000 ) } } );
		}
		report.plateKeys = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/photos/samples' } ).then( ( d ) => d.make?.keys ).catch( ( e ) => e.message ) );
		await page.getByLabel( 'Тёмная подложка под логотипом', { exact: true } ).uncheck().catch( () => {} );
		await page.waitForTimeout( 1500 );
		// Настройки уже включены (PROBE_CLI): выключить «Цветокоррекция» — предпросмотр меняется до сохранения, потом вернуть.
		const canvasLen = () => page.evaluate( () => document.querySelector( '.memorial-plugin-photo-preview canvas' )?.toDataURL().length || 0 );
		const before = await canvasLen();
		await page.getByLabel( 'Цветокоррекция', { exact: true } ).uncheck().catch( ( e ) => ( report.errUncheck = e.message ) );
		await page.waitForTimeout( 2500 );
		report.previewChanged = before !== ( await canvasLen() );
		await page.getByLabel( 'Цветокоррекция', { exact: true } ).check().catch( () => {} );
		await page.waitForTimeout( 2500 );
		report.previewBack = before === ( await canvasLen() );
		await shot( page, 'photos-screen-on', { fullPage: true } );
		const box = await page.locator( '.memorial-plugin-photo-preview' ).boundingBox();
		await page.getByRole( 'button', { name: 'Сравнить' } ).click().catch( () => {} );
		await page.waitForTimeout( 800 );
		if ( box ) {
			await page.screenshot( { path: `${ DIR }/photos-compare.png`, clip: { x: box.x, y: box.y, width: box.width, height: Math.min( box.height, 1000 ) } } );
		}
		// Картинка логотипа сделана экраном и сохранена; очередь (WP-Cron) ставит её на фото.
		report.logoMade = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/photos/samples' } ).then( ( d ) => ( { logo: d.logo, own: d.own, keys: d.make?.keys, svg: !! d.make?.svg } ) ).catch( ( e ) => e.message ) );
		for ( let i = 0; i < 25; i++ ) {
			await page.request.get( '/wp-cron.php?doing_wp_cron=' + Date.now() ).catch( () => {} );
			await page.waitForTimeout( 4000 );
			const st = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/photos/report' } ).then( ( d ) => d.state ).catch( () => null ) );
			report.cronState = st;
			if ( st && st.left === 0 && i > 2 ) {
				break;
			}
		}
		report.report = await page.evaluate( () => window.wp.apiFetch( { path: '/memorial-plugin/v1/photos/report' } ).then( ( d ) => ( { processed: d.processed, scope: d.scope, state: d.state, items: d.items.length, first: d.items.slice( 0, 5 ).map( ( r ) => [ r.title, r.width, r.problems ] ) } ) ).catch( ( e ) => e.message ) );
		// Каталог после пакетной обработки (PROBE_CLI): памятники — без замены фона, сразу обработаны.
		for ( const [ w, h, name ] of [ [ 1440, 900, 'pc' ], [ 390, 844, 'phone' ] ] ) {
			await page.setViewportSize( { width: w, height: h } );
			await page.goto( '/pamyatniki/', { waitUntil: 'domcontentloaded' } );
			await page.waitForTimeout( 2500 );
			await page.addStyleTag( { content: NO_FLOAT } );
			await page.locator( '.mcat__grid, .mcard' ).first().scrollIntoViewIfNeeded().catch( () => {} );
			await page.waitForTimeout( 1500 );
			await shot( page, `photos-pamyatniki-${ name }` );
		}
		report.cards = await page.evaluate( () => [ ...document.querySelectorAll( '.mcard img' ) ].slice( 0, 4 ).map( ( i ) => i.currentSrc ) );
		// Большая копия обработанного фото — с логотипом в правом нижнем углу.
		const big = ( report.cards[ 0 ] || '' ).replace( /-\d+x\d+(\.\w+)$/, '$1' );
		if ( big ) {
			await page.setViewportSize( { width: 1440, height: 1100 } );
			await page.goto( big );
			await page.waitForTimeout( 1000 );
			await shot( page, 'photos-big' );
			report.big = big;
		}
		report.errs = errs;
		fs.writeFileSync( `${ DIR }/log-photos.json`, JSON.stringify( report, null, 1 ) );
	} );

	test( 'обработка фото: окно товара на телефоне — стрелки и «Закрыть»', async ( { browser } ) => {
		test.setTimeout( 300_000 );
		const ctx = await browser.newContext( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2, baseURL: process.env.WP_BASE_URL || 'http://localhost:8888' } );
		const ph = await ctx.newPage();
		ph.setDefaultTimeout( 60000 );
		const report = {};
		await ph.goto( '/venki/', { waitUntil: 'domcontentloaded' } );
		await ph.waitForTimeout( 2500 );
		await ph.addStyleTag( { content: NO_FLOAT } );
		report.hoverNone = await ph.evaluate( () => window.matchMedia( '(hover: none)' ).matches );
		await ph.locator( '.mcard a[data-pv]' ).first().tap();
		await ph.waitForTimeout( 1500 );
		const t1 = await ph.locator( '#pv .pv__title' ).innerText().catch( () => '' );
		await ph.locator( '#pv .pv__next' ).tap();
		await ph.waitForTimeout( 1200 );
		report.changed = ( await ph.locator( '#pv .pv__title' ).innerText().catch( () => '' ) ) !== t1;
		// После нажатия стрелка белая, не «наведённая» зелёная.
		report.nextBg = await ph.locator( '#pv .pv__next' ).evaluate( ( e ) => getComputedStyle( e ).backgroundColor );
		await shot( ph, 'pv-phone-after-next' );
		// Прокрутить окно так, чтобы стрелка оказалась на высоте крестика: сверху должен быть крестик.
		report.overlap = await ph.evaluate( async () => {
			const pv = document.getElementById( 'pv' );
			const x = pv.querySelector( '.pv__x' ).getBoundingClientRect();
			const n = pv.querySelector( '.pv__next' ).getBoundingClientRect();
			pv.scrollTop += n.top - x.top;
			await new Promise( ( r ) => setTimeout( r, 400 ) );
			const x2 = pv.querySelector( '.pv__x' ).getBoundingClientRect();
			const n2 = pv.querySelector( '.pv__next' ).getBoundingClientRect();
			const top = document.elementFromPoint( x2.left + x2.width / 2, x2.top + x2.height / 2 );
			return { x: [ x2.top, x2.left ], next: [ n2.top, n2.left ], topIsClose: !! top?.closest( '.pv__x' ) };
		} );
		await shot( ph, 'pv-phone-overlap' );
		fs.writeFileSync( `${ DIR }/log-pv-phone.json`, JSON.stringify( report, null, 1 ) );
		await ctx.close();
	} );
} );

// Ввод по буквам в новой строке «Отдельных цен» (жалоба 24.11: после первой буквы страница прыгает).
// Фазы before (код живого сайта) и block (ветка): фокус, прокрутка, снимки ПК и телефонов.
for ( const [ width, height ] of [ [ 1440, 1000 ], [ 390, 844 ], [ 360, 780 ] ] ) {
	test.describe( 'ввод в ценах ' + width, () => {
		test.use( { viewport: { width, height } } );
		test( 'ввод в ценах ' + width, async ( { page } ) => {
			test.setTimeout( 180_000 );
			const errs = errorsOf( page );
			await page.goto( '/wp-login.php' );
			await page.fill( '#user_login', 'probe' );
			await page.fill( '#user_pass', 'probe-pass-123' );
			await page.click( '#wp-submit' );
			await page.waitForURL( /wp-admin/ );
			await page.goto( '/wp-admin/admin.php?page=mem-prices' );
			await page.waitForSelector( '.memorial-plugin-settings__card' );
			const out = { width };
			const card = page.locator( '.memorial-plugin-settings__card' ).filter( { hasText: 'Отдельные цены' } );
			// Свёрнутые строки: сводка не должна заходить под «Развернуть», стрелки и корзину.
			await card.locator( '.memorial-plugin-settings__row' ).first().scrollIntoViewIfNeeded();
			out.overlaps = await page.evaluate( () => {
				const hit = ( a, b ) => a && b && a.left < b.right - 1 && b.left < a.right - 1 && a.top < b.bottom - 1 && b.top < a.bottom - 1;
				const box = ( el ) => el && el.getBoundingClientRect();
				const bad = [];
				document.querySelectorAll( '.memorial-plugin-settings__row.is-folded' ).forEach( ( row, i ) => {
					const sum = box( row.querySelector( '.memorial-plugin-settings__row-sum' ) );
					const title = box( row.querySelector( '.memorial-plugin-settings__row-title' ) );
					const toggle = box( row.querySelector( '.memorial-plugin-settings__row-toggle' ) );
					const acts = box( row.querySelector( '.memorial-plugin-settings__row-actions' ) );
					const rowBox = box( row );
					if ( hit( sum, toggle ) || hit( sum, acts ) || hit( title, toggle ) || hit( title, acts ) || ( sum && sum.right > rowBox.right + 1 ) ) {
						bad.push( i + 1 );
					}
				} );
				return bad;
			} );
			await shot( page, 'prices-folded-' + width );
			await card.getByRole( 'button', { name: /Добавить цену/ } ).click();
			const row = card.locator( '.memorial-plugin-settings__row' ).last();
			const name = row.getByRole( 'textbox', { name: /^Название/ } );
			await name.scrollIntoViewIfNeeded();
			await name.click();
			out.scrollBefore = await page.evaluate( () => window.scrollY );
			const steps = [];
			for ( const ch of 'Доставка' ) {
				await page.keyboard.type( ch );
				await page.waitForTimeout( 120 );
				steps.push( {
					ch,
					focused: await name.evaluate( ( el ) => el === document.activeElement ).catch( () => false ),
					scrollY: await page.evaluate( () => window.scrollY ),
				} );
			}
			out.steps = steps;
			out.nameValue = await name.inputValue().catch( () => null );
			await shot( page, 'prices-typing-' + width );
			const price = row.getByRole( 'textbox', { name: /^Цена/ } );
			await price.click();
			await page.keyboard.type( '500', { delay: 120 } );
			out.priceFocused = await price.evaluate( ( el ) => el === document.activeElement ).catch( () => false );
			out.priceValue = await price.inputValue().catch( () => null );
			out.scrollAfter = await page.evaluate( () => window.scrollY );
			await shot( page, 'prices-typed-' + width );
			out.errors = errs;
			log( 'prices-typing-' + width, out );
		} );
	} );
}

// Страницы из репозитория на копии сайта (только фаза block): новая страница с пунктом меню создаётся при
// заходе в админку, старая страница (катафалк уже есть на сайте) ждёт решения; разница и «Оставить как на сайте».
test.describe( 'страницы из репозитория', () => {
	test.use( { viewport: { width: 1440, height: 1000 } } );
	test( 'страницы из репозитория', async ( { page } ) => {
		test.skip( PHASE !== 'block', 'модуль есть только в исправленном коде' );
		test.setTimeout( 240_000 );
		const path = require( 'path' );
		const dir = path.resolve( __dirname, '../../memorial-plugin/content/pages/ritualnye-uslugi' );
		fs.mkdirSync( dir, { recursive: true } );
		const file = ( slug, head, body ) =>
			fs.writeFileSync(
				`${ dir }/${ slug }.html`,
				'<!-- memorial-page ' + JSON.stringify( { slug, parent: 'ritualnye-uslugi', seo_title: head.title, seo_description: head.title, status: 'publish', version: 1, ...head } ) + ' -->\n<!-- wp:paragraph -->\n<p>' + body + '</p>\n<!-- /wp:paragraph -->\n'
			);
		file( 'eksgumaciya', { title: 'Эксгумация (проверка)', menu: { parent: '/ritualnye-uslugi/', label: 'Эксгумация', after: '/ritualnye-uslugi/gruz-200/' } }, 'Текст страницы из репозитория. <a href="/ritualnye-uslugi/traurnyj-zal/">Траурный зал</a>.' );
		file( 'katafalk', { title: 'Катафалк на похороны', menu: { parent: '/ritualnye-uslugi/', label: 'Катафалк', after: '/ritualnye-uslugi/traurnyj-zal/' } }, 'Новый текст катафалка из репозитория.' );
		const errs = errorsOf( page );
		const out = {};
		try {
			await page.goto( '/wp-login.php' );
			await page.fill( '#user_login', 'probe' );
			await page.fill( '#user_pass', 'probe-pass-123' );
			await page.click( '#wp-submit' );
			await page.waitForURL( /wp-admin/ );
			const pending = new Map();
			page.on( 'request', ( r ) => pending.set( r, Date.now() ) );
			page.on( 'requestfinished', ( r ) => pending.delete( r ) );
			page.on( 'requestfailed', ( r ) => pending.delete( r ) );
			let t = Date.now();
			await page.goto( '/wp-admin/', { waitUntil: 'domcontentloaded', timeout: 90_000 } );
			out.dashboardMs = Date.now() - t;
			out.notice = await page.locator( '.notice-warning' ).filter( { hasText: /репозитори/i } ).innerText().catch( () => '' );
			const consoleAll = [];
			page.on( 'console', ( m ) => consoleAll.push( m.type() + ': ' + m.text().slice( 0, 300 ) ) );
			// Сначала другой экран на том же скрипте (что ищут на сайте): виснет весь скрипт или только этот экран?
			t = Date.now();
			await page.goto( '/wp-admin/admin.php?page=mem-sq', { waitUntil: 'domcontentloaded', timeout: 60_000 } ).catch( ( e ) => ( out.sqError = e.message ) );
			await page.waitForTimeout( 4000 );
			out.sqMs = Date.now() - t;
			out.sqShot = await page.screenshot( { path: `${ DIR }/repo-sq.png`, timeout: 15_000 } ).then( () => 'ok' ).catch( ( e ) => e.message );
			log( 'repo-pages', out );
			const screenHtml = await page.request.get( '/wp-admin/admin.php?page=mem-repo-pages' ).then( async ( r ) => r.status() + ' ' + ( await r.text() ).length ).catch( ( e ) => e.message );
			out.screenHtml = screenHtml;
			const api = await page.request.get( '/wp-json/memorial-plugin/v1/repo-pages', { headers: { 'X-WP-Nonce': await page.evaluate( () => window.wpApiSettings?.nonce || '' ).catch( () => '' ) } } ).then( async ( r ) => r.status() + ' ' + ( await r.text() ).slice( 0, 3000 ) ).catch( ( e ) => e.message );
			out.api = api;
			log( 'repo-pages', out );
			t = Date.now();
			const resp = await page.goto( '/wp-admin/admin.php?page=mem-repo-pages', { waitUntil: 'domcontentloaded', timeout: 90_000 } ).catch( ( e ) => ( out.screenError = e.message ) );
			out.screenMs = Date.now() - t;
			out.screenStatus = resp && resp.status ? resp.status() : 0;
			await page.waitForTimeout( 5000 );
			out.console = consoleAll.slice( -40 );
			out.errorsSoFar = [ ...errs ];
			log( 'repo-pages', out );
			out.firstShot = await page.screenshot( { path: `${ DIR }/repo-first-1440.png`, timeout: 15_000 } ).then( () => 'ok' ).catch( ( e ) => e.message );
			log( 'repo-pages', out );
			await page.waitForSelector( '.dataviews-view-table, table', { timeout: 60_000 } ).catch( ( e ) => ( out.tableError = e.message ) );
			out.pending = [ ...pending.entries() ].map( ( [ r, at ] ) => r.url() + ' ' + ( Date.now() - at ) + 'ms' );
			await page.waitForTimeout( 1500 );
			out.rows = await page.getByRole( 'row' ).allInnerTexts();
			await shot( page, 'repo-1440', { fullPage: true } );
			for ( const [ w, h ] of [ [ 390, 844 ], [ 360, 780 ] ] ) {
				await page.setViewportSize( { width: w, height: h } );
				await page.waitForTimeout( 500 );
				await shot( page, 'repo-' + w, { fullPage: true } );
			}
			await page.setViewportSize( { width: 1440, height: 1000 } );
			const row = page.getByRole( 'row' ).filter( { hasText: '/ritualnye-uslugi/katafalk/' } );
			await row.getByRole( 'button', { name: /Показать разницу/ } ).click().catch( ( e ) => ( out.diffError = e.message ) );
			await page.waitForSelector( '.memorial-plugin-repo__diff', { timeout: 15000 } ).catch( () => {} );
			out.diff = await page.locator( '.memorial-plugin-repo__diff' ).innerText().catch( () => '' );
			await shot( page, 'repo-diff-1440' );
			await page.getByRole( 'dialog' ).getByRole( 'button', { name: 'Оставить как на сайте' } ).click().catch( ( e ) => ( out.keepError = e.message ) );
			await page.waitForTimeout( 2500 );
			out.after = await page.getByRole( 'row' ).allInnerTexts();
			await page.getByRole( 'tab', { name: 'Журнал' } ).click().catch( () => {} );
			await page.waitForTimeout( 800 );
			out.log = await page.getByRole( 'row' ).allInnerTexts();
			await shot( page, 'repo-log-1440', { fullPage: true } );
			const res = await page.goto( '/ritualnye-uslugi/eksgumaciya/' );
			out.newPage = res ? res.status() : 0;
			const html = await page.content();
			out.menuHasNew = /href="[^"]*\/ritualnye-uslugi\/eksgumaciya\/"/.test( html );
			out.hallLink = html.includes( '/ritualnye-uslugi/traurnyj-zal/' );
			await shot( page, 'repo-new-page-1440' );
			out.katafalkUntouched = ! ( await ( await page.goto( '/ritualnye-uslugi/katafalk/' ) ).text() ).includes( 'Новый текст катафалка из репозитория.' );
		} finally {
			fs.rmSync( dir, { recursive: true, force: true } );
		}
		out.errors = errs;
		log( 'repo-pages', out );
	} );
} );
