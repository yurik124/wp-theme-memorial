/**
 * Editor: previews of the plugin blocks are loaded with one request instead of one request per block.
 *
 * Each block rendered on the server asks /wp/v2/block-renderer/<block> for its preview. A page with twenty
 * plugin blocks sent twenty requests at once; a hosting that limits the number of PHP processes answered
 * part of them with 503 («Error loading block: [object Object]»), the rest waited for each other.
 * This middleware of wp.apiFetch collects such requests made within a short moment and sends them as
 * one request to memorial-plugin/v1/render-blocks; the server renders them one after another in one
 * process with the same checks as WordPress, and every block gets its own answer.
 */
( function ( apiFetch ) {
	'use strict';

	if ( ! apiFetch || ! apiFetch.use ) {
		return;
	}

	var PREFIX = '/wp/v2/block-renderer/memorial-plugin/';
	var WAIT = 30;
	var MAX = 40;
	var queue = [];
	var timer = 0;

	function abortError() {
		try {
			return new DOMException( 'The operation was aborted.', 'AbortError' );
		} catch ( e ) {
			var err = new Error( 'The operation was aborted.' );
			err.name = 'AbortError';
			return err;
		}
	}

	function errorOf( body, status ) {
		var message = body && body.message ? body.message : 'HTTP ' + status;
		var err = new Error( message );
		err.code = body && body.code ? body.code : 'http_' + status;
		err.data = body && body.data ? body.data : { status: status };
		return err;
	}

	function flush( next ) {
		timer = 0;
		var items = queue.filter( function ( item ) {
			return ! item.aborted;
		} );
		queue = [];
		while ( items.length ) {
			send( items.splice( 0, MAX ), next );
		}
	}

	function send( items, next ) {
		next( {
			path: '/memorial-plugin/v1/render-blocks',
			method: 'POST',
			data: {
				requests: items.map( function ( item ) {
					return { path: item.path, attributes: item.attributes };
				} ),
			},
		} ).then(
			function ( answers ) {
				items.forEach( function ( item, i ) {
					var answer = answers && answers[ i ];
					if ( ! answer ) {
						item.reject( errorOf( null, 500 ) );
					} else if ( answer.status >= 400 ) {
						item.reject( errorOf( answer.body, answer.status ) );
					} else {
						item.resolve( answer.body );
					}
				} );
			},
			function ( error ) {
				// The batch itself failed: every block gets a readable error instead of «[object Object]».
				var err = error instanceof Error ? error : errorOf( error, ( error && error.data && error.data.status ) || 500 );
				items.forEach( function ( item ) {
					item.reject( err );
				} );
			}
		);
	}

	apiFetch.use( function ( options, next ) {
		var path = options && options.path;
		if ( typeof path !== 'string' || path.indexOf( PREFIX ) !== 0 || options.parse === false ) {
			return next( options );
		}
		var attributes = null;
		if ( options.method === 'POST' ) {
			try {
				attributes = JSON.parse( options.body || '{}' ).attributes || null;
			} catch ( e ) {
				return next( options );
			}
		}
		return new Promise( function ( resolve, reject ) {
			var item = { path: path, attributes: attributes, resolve: resolve, reject: reject, aborted: false };
			if ( options.signal ) {
				if ( options.signal.aborted ) {
					reject( abortError() );
					return;
				}
				options.signal.addEventListener( 'abort', function () {
					item.aborted = true;
					reject( abortError() );
				} );
			}
			queue.push( item );
			if ( ! timer ) {
				timer = setTimeout( function () {
					flush( next );
				}, WAIT );
			}
		} );
	} );
} )( window.wp && window.wp.apiFetch );
