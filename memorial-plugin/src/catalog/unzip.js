/**
 * A ZIP archive read in the browser, without libraries: the list of files from the central directory,
 * the contents unpacked by the browser itself (DecompressionStream “deflate-raw”). So an archive of
 * any size never goes to the server as a whole — the import sends only the photos it needs, one by one.
 *
 * File names: UTF-8 when the archive says so (macOS, 7-Zip), otherwise the DOS code page CP866
 * (the archiver built into Windows).
 */

const TYPES = {
	jpg: 'image/jpeg',
	jpeg: 'image/jpeg',
	png: 'image/png',
	webp: 'image/webp',
	gif: 'image/gif',
	avif: 'image/avif',
	csv: 'text/csv',
	xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
};

/**
 * MIME type by the extension of a file name.
 *
 * @param {string} name File name.
 * @return {string} MIME type or ''.
 */
export function typeOf( name ) {
	return TYPES[ ( name.split( '.' ).pop() || '' ).toLowerCase() ] || '';
}

/**
 * Files of a ZIP archive.
 *
 * @param {File} zip Archive.
 * @return {Promise<Array<{path: string, size: number, file: Function}>>} Files; file() gives a File.
 */
export async function unzip( zip ) {
	const buf = new Uint8Array( await zip.arrayBuffer() );
	const view = new DataView( buf.buffer );
	// End of the central directory: its signature within the last 64 KB.
	let end = -1;
	for (
		let i = buf.length - 22;
		i >= Math.max( 0, buf.length - 65557 );
		i--
	) {
		if ( view.getUint32( i, true ) === 0x06054b50 ) {
			end = i;
			break;
		}
	}
	if ( end < 0 ) {
		throw new Error( 'not a zip' );
	}
	const count = view.getUint16( end + 10, true );
	let p = view.getUint32( end + 16, true );
	const out = [];
	for ( let n = 0; n < count; n++ ) {
		if ( view.getUint32( p, true ) !== 0x02014b50 ) {
			throw new Error( 'broken zip' );
		}
		const flags = view.getUint16( p + 8, true );
		const method = view.getUint16( p + 10, true );
		const csize = view.getUint32( p + 20, true );
		const usize = view.getUint32( p + 24, true );
		const nameLen = view.getUint16( p + 28, true );
		const extraLen = view.getUint16( p + 30, true );
		const noteLen = view.getUint16( p + 32, true );
		const local = view.getUint32( p + 42, true );
		const path = new TextDecoder(
			// eslint-disable-next-line no-bitwise
			flags & 0x800 ? 'utf-8' : 'ibm866'
		)
			.decode( buf.subarray( p + 46, p + 46 + nameLen ) )
			.replace( /\\/g, '/' );
		p += 46 + nameLen + extraLen + noteLen;
		const base = path.split( '/' ).pop();
		// Folders and the service files of macOS and Windows.
		if (
			! base ||
			path.startsWith( '__MACOSX/' ) ||
			base.startsWith( '._' ) ||
			/^(thumbs\.db|\.ds_store|desktop\.ini)$/i.test( base )
		) {
			continue;
		}
		if ( csize === 0xffffffff ) {
			throw new Error( 'zip64' );
		}
		out.push( {
			path,
			size: usize,
			file: async () => {
				const start =
					local +
					30 +
					view.getUint16( local + 26, true ) +
					view.getUint16( local + 28, true );
				const data = buf.subarray( start, start + csize );
				let bytes = data;
				if ( method === 8 ) {
					bytes = await new window.Response(
						new window.Blob( [ data ] )
							.stream()
							.pipeThrough(
								new window.DecompressionStream( 'deflate-raw' )
							)
					).arrayBuffer();
				} else if ( method !== 0 ) {
					throw new Error( 'zip method ' + method );
				}
				return new window.File( [ bytes ], base, {
					type: typeOf( base ),
				} );
			},
		} );
	}
	return out;
}

/**
 * SHA-1 of a file as hex: the import knows a photo it has already uploaded. '' where the browser
 * cannot count it (a page without HTTPS).
 *
 * @param {File} file File.
 * @return {Promise<string>} Hash.
 */
export async function sha1( file ) {
	if ( ! window.crypto?.subtle ) {
		return '';
	}
	const hash = await window.crypto.subtle.digest(
		'SHA-1',
		await file.arrayBuffer()
	);
	return [ ...new Uint8Array( hash ) ]
		.map( ( b ) => b.toString( 16 ).padStart( 2, '0' ) )
		.join( '' );
}
