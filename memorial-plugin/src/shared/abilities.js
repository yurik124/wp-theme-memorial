/**
 * Calling the plugin’s abilities (admin/abilities.php) from the screens and the Command Palette.
 *
 * WordPress loads the abilities of the server into the @wordpress/abilities store on admin pages
 * (script module @wordpress/core-abilities). The module is imported at run time through the page’s
 * import map; where it is missing (an old page, a blocked module) the same ability is run through its
 * REST endpoint /wp-abilities/v1/abilities/<name>/run — with the same input schema and permission check.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

let modulePromise = null;

function abilitiesModule() {
	if ( ! modulePromise ) {
		modulePromise = import(
			/* webpackIgnore: true */ '@wordpress/abilities'
		).catch( () => null );
	}
	return modulePromise;
}

/**
 * HTTP method of an ability run: readonly — GET, destructive and idempotent — DELETE, otherwise POST.
 *
 * @param {Object} meta Ability meta (annotations).
 * @return {string} Method.
 */
function methodOf( meta ) {
	const a = meta?.annotations || {};
	if ( a.readonly ) {
		return 'GET';
	}
	return a.destructive && a.idempotent ? 'DELETE' : 'POST';
}

/**
 * Run an ability.
 *
 * @param {string} name  Ability name, e.g. memorial-plugin/export-catalog.
 * @param {Object} input Input matching the ability’s input schema.
 * @return {Promise<*>} Output of the ability.
 */
export async function runAbility( name, input = {} ) {
	const mod = await abilitiesModule();
	if ( mod?.executeAbility && mod.getAbility?.( name ) ) {
		return mod.executeAbility( name, input );
	}
	const path = '/wp-abilities/v1/abilities/' + name;
	const ability = await apiFetch( { path } );
	const method = methodOf( ability.meta );
	const run = path + '/run';
	if ( method === 'POST' ) {
		return apiFetch( { path: run, method, data: { input } } );
	}
	return apiFetch( {
		path: Object.keys( input ).length
			? addQueryArgs( run, { input } )
			: run,
		method,
	} );
}

/**
 * Give the browser a text file to save.
 *
 * @param {string} filename File name.
 * @param {string} text     Contents.
 * @param {string} type     MIME type.
 */
export function download( filename, text, type = 'text/csv;charset=utf-8' ) {
	const url = URL.createObjectURL( new Blob( [ text ], { type } ) );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = filename;
	document.body.append( a );
	a.click();
	a.remove();
	window.setTimeout( () => URL.revokeObjectURL( url ), 1000 );
}
