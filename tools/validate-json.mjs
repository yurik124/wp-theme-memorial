/**
 * Проверка theme.json, вариаций стилей и block.json по JSON-схемам WordPress (schemas.wp.org).
 * Схема берётся из поля $schema файла; файла без $schema — ошибка. Нет файлов — проверять нечего (этап 1).
 * Запуск: npm run lint:json
 */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const ROOTS = [ 'memorial-theme', 'memorial-plugin' ];
const SKIP = new Set( [ 'node_modules', 'vendor', 'build', '.git' ] );

function walk( dir, out = [] ) {
	for ( const name of readdirSync( dir ) ) {
		if ( SKIP.has( name ) ) {
			continue;
		}
		const path = join( dir, name );
		if ( statSync( path ).isDirectory() ) {
			walk( path, out );
		} else if (
			name === 'block.json' ||
			name === 'theme.json' ||
			( /\.json$/.test( name ) &&
				/(^|\/)styles\//.test( relative( '.', path ) ) )
		) {
			out.push( path );
		}
	}
	return out;
}

const files = ROOTS.flatMap( ( root ) => walk( root ) );
if ( ! files.length ) {
	// eslint-disable-next-line no-console
	console.log( 'theme.json / block.json пока нет — проверять нечего.' );
	process.exit( 0 );
}

const { default: Ajv } = await import( 'ajv' );
const ajv = new Ajv( { allErrors: true, strict: false } );
const cache = new Map();
let failed = 0;

for ( const file of files ) {
	const json = JSON.parse( readFileSync( file, 'utf8' ) );
	const url = json.$schema;
	if ( ! url ) {
		// eslint-disable-next-line no-console
		console.error( `${ file }: нет поля $schema` );
		failed++;
		continue;
	}
	if ( ! cache.has( url ) ) {
		const res = await fetch( url );
		if ( ! res.ok ) {
			throw new Error( `${ url }: HTTP ${ res.status }` );
		}
		const schema = await res.json();
		delete schema.$schema; // draft-04/07 различия Ajv не нужны: проверяем структуру.
		cache.set( url, ajv.compile( schema ) );
	}
	const validate = cache.get( url );
	if ( ! validate( json ) ) {
		failed++;
		// eslint-disable-next-line no-console
		console.error(
			`${ file }:\n${ ajv.errorsText( validate.errors, { separator: '\n' } ) }`
		);
	} else {
		// eslint-disable-next-line no-console
		console.log( `${ file }: ok` );
	}
}
process.exit( failed ? 1 : 0 );
