/**
 * Проверка правок CSS без сайта: по слепкам страниц (workflow «Проверка на копии сайта», сценарий
 * «слепки страниц», папка snap в ветке ci-probe) сравнивает вычисленные стили КАЖДОГО элемента
 * (и его ::before / ::after) до и после правки.
 *
 *   node tools/css-check.mjs <папка snap> [часть имени страницы]
 *
 * «До» — слепок как есть (стили сайта на момент съёмки). «После» — тот же слепок, в котором таблицы
 * стилей темы и плагина заменены файлами из рабочей копии. Нет отличий — правка не меняет вид страниц
 * (кроме состояний :hover/:focus и редактора — их слепок не покрывает).
 * Размеры (width, height) сравниваются тоже, поэтому одно изменённое правило даёт отличия и у соседей и предков.
 */
/* eslint-disable no-console, no-bitwise -- a command-line tool; the hash uses bit operations. */
import { createRequire } from 'node:module';
import { readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
// Playwright comes with @playwright/test in CI and is installed globally on a developer machine.
// eslint-disable-next-line import/no-extraneous-dependencies
const { chromium } = require( 'playwright' );

const ROOT = resolve( dirname( fileURLToPath( import.meta.url ) ), '..' );
const DIR = process.argv[ 2 ];
const FILTER =
	process.argv[ 3 ] && ! process.argv[ 3 ].startsWith( '--' )
		? process.argv[ 3 ]
		: '';
// --selectors=<файл.json>: [ { id, selector } ] → в --out=<файл.json> попадут id правил, чьи селекторы
// совпадают с изменившимися элементами (changed) и с любыми элементами слепков вообще (present).
const opt = ( name ) =>
	( process.argv.find( ( a ) => a.startsWith( `--${ name }=` ) ) || '' )
		.split( '=' )
		.slice( 1 )
		.join( '=' );
const SELECTORS = opt( 'selectors' )
	? JSON.parse( readFileSync( opt( 'selectors' ), 'utf8' ) )
	: [];
const OUT = opt( 'out' );
const changedIds = new Set();
const presentIds = new Set();
if ( ! DIR ) {
	console.error( 'node tools/css-check.mjs <папка snap> [страница]' );
	process.exit( 2 );
}

// Какой файл рабочей копии стоит за адресом таблицы стилей в слепке.
function localFile( href ) {
	const m = href.match(
		/wp-content\/(?:themes|plugins)\/(memorial-(?:theme|plugin)\/[^?]+\.css)/
	);
	if ( ! m ) {
		return null;
	}
	return join( ROOT, m[ 1 ].replace( /\.min\.css$/, '.css' ) );
}

function swapStyles( html ) {
	let swapped = 0;
	const out = html.replace(
		/<style([^>]*?)data-href="([^"]+)"([^>]*)>([\s\S]*?)<\/style>/g,
		( all, a, href, b ) => {
			const file = localFile( href.replace( /&amp;/g, '&' ) );
			if ( ! file ) {
				return all;
			}
			let css;
			try {
				css = readFileSync( file, 'utf8' );
			} catch {
				return all;
			}
			const base = href.replace( /&amp;/g, '&' );
			css = css.replace(
				/url\((['"]?)(?!data:|https?:|\/\/|#)([^'")]+)\1\)/g,
				( m, q, u ) => `url(${ q }${ new URL( u, base ).href }${ q })`
			);
			swapped++;
			return `<style${ a }data-href="${ href }"${ b }>${ css.replace( /<\/style/gi, '<\\/style' ) }</style>`;
		}
	);
	return { html: out, swapped };
}

// Следуют из размеров — в сравнение не входят (сами размеры входят).
const SKIP = new Set( [ 'perspective-origin', 'transform-origin' ] );

// В странице: отпечаток стилей каждого элемента; подробности — по запросу.
const collect = () => {
	const hash = ( s ) => {
		let h = 2166136261;
		for ( let i = 0; i < s.length; i++ ) {
			h ^= s.charCodeAt( i );
			h = Math.imul( h, 16777619 );
		}
		return h >>> 0;
	};
	const skip = new Set( window.__skip );
	const print = ( el, pseudo ) => {
		const cs = window.getComputedStyle( el, pseudo );
		if ( pseudo && cs.content === 'none' ) {
			return '';
		}
		// По алфавиту: браузер перечисляет свойства (особенно переменные --*) в разном порядке от загрузки к загрузке.
		const out = [];
		for ( let i = 0; i < cs.length; i++ ) {
			const p = cs[ i ];
			if ( ! skip.has( p ) ) {
				out.push(
					p + ':' + cs.getPropertyValue( p ).replace( /;/g, ',' )
				);
			}
		}
		return out.sort().join( ';' );
	};
	window.__els = [ ...document.querySelectorAll( 'body, body *' ) ];
	// Отпечатки хранятся: подробности берутся из тех же значений, что сравнивались.
	window.__prints = window.__els.map( ( el ) => [
		print( el, null ),
		print( el, '::before' ),
		print( el, '::after' ),
	] );
	return window.__prints.map( ( p ) => p.map( hash ) );
};
const detail = ( [ index, pseudo ] ) => {
	const el = window.__els[ index ];
	const path = [];
	for (
		let e = el;
		e && e !== document.body && path.length < 4;
		e = e.parentElement
	) {
		path.unshift(
			e.tagName.toLowerCase() +
				( e.classList.length
					? '.' + [ ...e.classList ].slice( 0, 3 ).join( '.' )
					: '' )
		);
	}
	return { path: path.join( ' > ' ) + ( pseudo || '' ) };
};

const browser = await chromium.launch();
const files = readdirSync( DIR )
	.filter( ( f ) => /\.(pc|phone)\.html$/.test( f ) && f.includes( FILTER ) )
	.sort();
let total = 0;
const byRule = new Map();
for ( const file of files ) {
	const viewport = /\.phone\.html$/.test( file )
		? { width: 390, height: 800 }
		: { width: 1280, height: 900 };
	const before = readFileSync( join( DIR, file ), 'utf8' );
	const { html: after, swapped } = swapStyles( before );
	const pages = [];
	for ( const html of [ before, after ] ) {
		const context = await browser.newContext( {
			viewport,
			javaScriptEnabled: true,
			reducedMotion: 'reduce',
		} );
		const page = await context.newPage();
		await page.route( '**/*', ( route ) => route.abort() );
		await page.setContent( html, { waitUntil: 'load' } );
		// Стили читаются, когда страница успокоилась: без переходов и анимаций, после шрифтов и картинок (их нет — ошибки загрузки).
		await page.addStyleTag( {
			content:
				'*,*::before,*::after{transition:none!important;animation:none!important}',
		} );
		await page.evaluate( () => document.fonts.ready );
		await page.waitForTimeout( 400 );
		await page.evaluate(
			( skip ) => ( window.__skip = skip ),
			[ ...SKIP ]
		);
		pages.push( page );
	}
	const [ a, b ] = await Promise.all(
		pages.map( ( p ) => p.evaluate( collect ) )
	);
	const diffs = [];
	if ( a.length !== b.length ) {
		console.log(
			`${ file }: разное число элементов (${ a.length } / ${ b.length })`
		);
	}
	for ( let i = 0; i < Math.min( a.length, b.length ); i++ ) {
		[ null, '::before', '::after' ].forEach( ( pseudo, k ) => {
			if ( a[ i ][ k ] !== b[ i ][ k ] ) {
				diffs.push( [ i, pseudo ] );
			}
		} );
	}
	total += diffs.length;
	console.log(
		`${ file }: таблиц заменено ${ swapped }, элементов ${ a.length }, отличается ${ diffs.length }`
	);
	// Какие свойства изменились у каждого отличающегося элемента (значения — те же, что сравнивались).
	const props = ( text ) => {
		const map = {};
		text.split( ';' ).forEach( ( kv ) => {
			const i = kv.indexOf( ':' );
			if ( i > 0 ) {
				map[ kv.slice( 0, i ) ] = kv.slice( i + 1 );
			}
		} );
		return map;
	};
	const changedOf = new Map();
	for ( let from = 0; from < diffs.length; from += 150 ) {
		const part = diffs.slice( from, from + 150 );
		const [ xs, ys ] = await Promise.all(
			pages.map( ( p ) =>
				p.evaluate(
					( ds ) =>
						ds.map(
							( [ i, pseudo ] ) =>
								window.__prints[ i ][
									[ null, '::before', '::after' ].indexOf(
										pseudo
									)
								]
						),
					part
				)
			)
		);
		part.forEach( ( d, n ) => {
			const x = props( xs[ n ] );
			const y = props( ys[ n ] );
			const changed = Object.keys( { ...x, ...y } ).filter(
				( p ) => x[ p ] !== y[ p ]
			);
			const list = changedOf.get( d[ 0 ] ) || [];
			changedOf.set( d[ 0 ], list.concat( changed ) );
			if ( from + n < 400 ) {
				d.push(
					changed
						.map( ( p ) => `${ p }: ${ x[ p ] } → ${ y[ p ] }` )
						.join( '; ' )
				);
			}
		} );
	}
	if ( SELECTORS.length ) {
		const found = await pages[ 1 ].evaluate(
			( [ list, changed ] ) => {
				// Связано ли свойство правила с изменившимися свойствами элемента.
				const related = ( prop, names ) => {
					if (
						prop.startsWith( '--' ) ||
						[
							'display',
							'position',
							'float',
							'content',
							'all',
						].includes( prop )
					) {
						return true;
					}
					const size =
						/^(min-|max-)?(width|height)$|^aspect-ratio$|^flex|^grid/.test(
							prop
						);
					return names.some(
						( n ) =>
							n === prop ||
							n.startsWith( prop + '-' ) ||
							( size &&
								/width|height|block-size|inline-size/.test(
									n
								) ) ||
							( prop === 'border-radius' &&
								n.includes( 'radius' ) ) ||
							( prop === 'font' &&
								( n.startsWith( 'font' ) ||
									n === 'line-height' ) ) ||
							( prop === 'color' && n.includes( 'color' ) ) ||
							( prop === 'text-decoration' &&
								n.includes( 'text-decoration' ) ) ||
							( prop === 'white-space' &&
								( n.includes( 'white-space' ) ||
									n.includes( 'text-wrap' ) ) ) ||
							( prop === 'inset' &&
								/^(top|right|bottom|left|inset)/.test( n ) ) ||
							( /^(margin|padding)-(left|right|top|bottom)$/.test(
								prop
							) &&
								n.startsWith( prop.split( '-' )[ 0 ] ) )
					);
				};
				const ids = { changed: [], present: [] };
				for ( const { id, selector, prop } of list ) {
					try {
						if ( document.querySelector( selector ) ) {
							ids.present.push( id );
						}
						if (
							changed.some(
								( [ index, names ] ) =>
									window.__els[ index ].matches( selector ) &&
									related( prop || '--', names )
							)
						) {
							ids.changed.push( id );
						}
					} catch {
						// A selector the browser cannot parse matches nothing.
					}
				}
				return ids;
			},
			[ SELECTORS, [ ...changedOf.entries() ] ]
		);
		found.changed.forEach( ( id ) => changedIds.add( id ) );
		found.present.forEach( ( id ) => presentIds.add( id ) );
	}
	for ( const d of diffs.slice( 0, 400 ) ) {
		const x = await pages[ 0 ].evaluate( detail, [ d[ 0 ], d[ 1 ] ] );
		const key = x.path.replace( /^.* > /, '' ) + ' | ' + d[ 2 ];
		const item = byRule.get( key ) || {
			count: 0,
			where: new Set(),
			path: x.path,
		};
		item.count++;
		item.where.add( file );
		byRule.set( key, item );
	}
	await Promise.all( pages.map( ( p ) => p.context().close() ) );
}
await browser.close();

if ( OUT ) {
	writeFileSync(
		OUT,
		JSON.stringify( {
			total,
			changed: [ ...changedIds ],
			present: [ ...presentIds ],
		} )
	);
}
console.log( `\nВсего отличий: ${ total }` );
[ ...byRule.entries() ]
	.sort( ( x, y ) => y[ 1 ].count - x[ 1 ].count )
	.slice( 0, 80 )
	.forEach( ( [ key, item ] ) => {
		console.log(
			`\n× ${ item.count } (${ [ ...item.where ].slice( 0, 3 ).join( ', ' ) }${ item.where.size > 3 ? '…' : '' })\n  ${ item.path }\n  ${ key.slice( key.indexOf( ' | ' ) + 3, 900 ) }`
		);
	} );
process.exit( total ? 1 : 0 );
