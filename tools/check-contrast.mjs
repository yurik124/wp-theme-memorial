/**
 * WCAG 2.2 AA: contrast of the colour pairs the theme and the plugin use — in theme.json and in every
 * style variation (styles/*.json). Text ≥ 4.5:1; large text and UI parts (borders of fields, icons) ≥ 3:1.
 * Run: node tools/check-contrast.mjs (also part of npm run lint:json).
 */
import { readFileSync, readdirSync } from 'node:fs';

const DIR = 'memorial-theme';
// [foreground, background, minimum, where it is used]
const PAIRS = [
	[ 'contrast', 'base', 4.5, 'text on the page' ],
	[ 'contrast', 'base-2', 4.5, 'text on alternating sections' ],
	[ 'contrast', 'surface', 4.5, 'text on notes, footer' ],
	[ 'contrast-2', 'base', 4.5, 'secondary text (captions, dates)' ],
	[ 'contrast-2', 'base-2', 4.5, 'secondary text on alternating sections' ],
	[ 'on-primary', 'primary', 4.5, 'buttons' ],
	[ 'secondary', 'base', 4.5, 'links, prices' ],
	[ 'secondary', 'surface', 4.5, 'links in the footer' ],
	[ 'primary', 'base', 3, 'icons, borders of buttons and chips (UI)' ],
];

const hex = ( c ) => {
	let h = c.replace( '#', '' ).trim();
	if ( h.length === 3 ) {
		h = [ ...h ].map( ( x ) => x + x ).join( '' );
	}
	return [ 0, 2, 4 ].map( ( i ) => parseInt( h.slice( i, i + 2 ), 16 ) / 255 );
};
const lum = ( c ) => {
	const [ r, g, b ] = hex( c ).map( ( v ) =>
		v <= 0.03928 ? v / 12.92 : ( ( v + 0.055 ) / 1.055 ) ** 2.4
	);
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const ratio = ( a, b ) => {
	const [ x, y ] = [ lum( a ), lum( b ) ].sort( ( m, n ) => n - m );
	return ( x + 0.05 ) / ( y + 0.05 );
};

const palette = ( file ) => {
	const json = JSON.parse( readFileSync( file, 'utf8' ) );
	const out = {};
	( json.settings?.color?.palette || [] ).forEach( ( c ) => {
		out[ c.slug ] = c.color;
	} );
	return out;
};

const base = palette( `${ DIR }/theme.json` );
const files = [
	[ 'theme.json', base ],
	...readdirSync( `${ DIR }/styles` )
		.filter( ( f ) => f.endsWith( '.json' ) )
		.map( ( f ) => [ `styles/${ f }`, { ...base, ...palette( `${ DIR }/styles/${ f }` ) } ] ),
];

let failed = 0;
for ( const [ name, colors ] of files ) {
	for ( const [ fg, bg, min, use ] of PAIRS ) {
		if ( ! colors[ fg ] || ! colors[ bg ] ) {
			continue;
		}
		const r = ratio( colors[ fg ], colors[ bg ] );
		if ( r < min ) {
			failed++;
			process.stdout.write(
				`✖ ${ name }: ${ fg } ${ colors[ fg ] } on ${ bg } ${ colors[ bg ] } — ${ r.toFixed( 2 ) }:1, needs ${ min }:1 (${ use })\n`
			);
		}
	}
}
if ( failed ) {
	process.exitCode = 1;
} else {
	process.stdout.write( `Contrast is fine: ${ files.length } palettes × ${ PAIRS.length } pairs.\n` );
}
