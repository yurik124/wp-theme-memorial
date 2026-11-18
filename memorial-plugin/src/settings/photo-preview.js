/**
 * Preview of «Memorial → Photo processing»: a photo of the site with the settings of the screen as they are now —
 * before saving. The formulas are the ones of pages/photo-tools.php (colour tables from a 200 px copy, saturation,
 * sharpness 3×3, frame of the object, logo), so the photo on the site looks the same.
 * The background is replaced in GitHub Actions: its samples come from the last run of “Photos — background”.
 * Under the photo: the state of the processing and the report of bad photos.
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	Notice,
	RangeControl,
	SelectControl,
	Spinner,
	__experimentalToggleGroupControl as ToggleGroupControl, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const KINDS = {
	product: __( 'Product', 'memorial-plugin' ),
	work: __( 'Work', 'memorial-plugin' ),
	page: __( 'Page', 'memorial-plugin' ),
};

const PROBLEMS = {
	small: __( 'small', 'memorial-plugin' ),
	alt: __( 'no description', 'memorial-plugin' ),
	dark: __( 'too dark', 'memorial-plugin' ),
	light: __( 'too light', 'memorial-plugin' ),
	blur: __( 'blurred', 'memorial-plugin' ),
	repeat: __( 'repeats another photo', 'memorial-plugin' ),
};

const loadImage = ( src ) =>
	new Promise( ( ok, fail ) => {
		const im = new window.Image();
		im.crossOrigin = 'anonymous';
		im.onload = () => ok( im );
		im.onerror = fail;
		im.src = src;
	} );

const canvasOf = ( w, h ) => {
	const c = document.createElement( 'canvas' );
	c.width = w;
	c.height = h;
	return c;
};

// What is done to a photo — memorial_plugin_photo_recipe().
function recipeOf( s, kind, type ) {
	const r = {};
	if ( s.color?.on ) {
		r.color = s.color;
	}
	if ( kind === 'product' && s.bg?.on && s.bg[ 'type_' + type ] ) {
		r.bg = /^#[0-9a-f]{6}$/i.test( s.bg.color || '' )
			? s.bg.color
			: '#fbfaf6';
	}
	if ( s.crop?.on ) {
		const ratio = kind === 'product' ? s.crop.product : s.crop.work;
		const trim = kind === 'product' && !! s.crop.trim;
		if ( ratio || trim ) {
			r.crop = { ratio: ratio || '', trim, pad: Number( s.crop.pad ) };
		}
	}
	if ( s.logo?.on ) {
		r.logo = s.logo;
	}
	return r;
}

// A copy with the longest side at most 200 px — the statistics are taken from it, as on the server.
function sampleOf( src ) {
	const k = Math.min( 1, 200 / Math.max( src.width, src.height ) );
	const c = canvasOf(
		Math.max( 1, Math.round( src.width * k ) ),
		Math.max( 1, Math.round( src.height * k ) )
	);
	c.getContext( '2d' ).drawImage( src, 0, 0, c.width, c.height );
	return c;
}

// Colour tables — memorial_plugin_photo_luts().
function lutsOf( sample, c ) {
	const d = sample
		.getContext( '2d' )
		.getImageData( 0, 0, sample.width, sample.height ).data;
	let n = 0;
	const hist = [ 0, 1, 2 ].map( () => new Array( 256 ).fill( 0 ) );
	for ( let i = 0; i < d.length; i += 4 ) {
		if ( d[ i + 3 ] < 54 ) {
			continue; // прозрачное вокруг товара с новым фоном — не считается
		}
		n++;
		hist[ 0 ][ d[ i ] ]++;
		hist[ 1 ][ d[ i + 1 ] ]++;
		hist[ 2 ][ d[ i + 2 ] ]++;
	}
	n = Math.max( 1, n );
	const lo = [ 0, 0, 0 ];
	const hi = [ 255, 255, 255 ];
	if ( c.levels ) {
		for ( let ch = 0; ch < 3; ch++ ) {
			let sum = 0;
			for ( let i = 0; i < 256; i++ ) {
				sum += hist[ ch ][ i ];
				if ( sum > n * 0.005 ) {
					lo[ ch ] = i;
					break;
				}
			}
			sum = 0;
			for ( let i = 255; i >= 0; i-- ) {
				sum += hist[ ch ][ i ];
				if ( sum > n * 0.005 ) {
					hi[ ch ] = i;
					break;
				}
			}
			if ( hi[ ch ] - lo[ ch ] < 32 ) {
				lo[ ch ] = 0;
				hi[ ch ] = 255;
			}
		}
	}
	const mean = [ 0, 0, 0 ];
	for ( let i = 0; i < d.length; i += 4 ) {
		if ( d[ i + 3 ] < 54 ) {
			continue;
		}
		for ( let ch = 0; ch < 3; ch++ ) {
			mean[ ch ] +=
				( ( d[ i + ch ] - lo[ ch ] ) * 255 ) / ( hi[ ch ] - lo[ ch ] );
		}
	}
	const m = mean.map( ( x ) => Math.max( 1, x / n ) );
	const grey = ( m[ 0 ] + m[ 1 ] + m[ 2 ] ) / 3;
	const wb = Number( c.wb || 0 ) / 100;
	const b = Number( c.bright || 0 ) * 1.28;
	const k = 1 + Number( c.contrast || 0 ) / 100;
	const warm = Number( c.warmth || 0 ) * 0.8;
	return [ 0, 1, 2 ].map( ( ch ) => {
		const gain = 1 + ( grey / m[ ch ] - 1 ) * wb;
		const t = new Uint8ClampedArray( 256 );
		for ( let i = 0; i < 256; i++ ) {
			let v = ( ( i - lo[ ch ] ) * 255 ) / ( hi[ ch ] - lo[ ch ] );
			v *= gain;
			v += b;
			v = ( v - 128 ) * k + 128;
			if ( ch === 0 ) {
				v += warm;
			} else if ( ch === 2 ) {
				v -= warm;
			}
			t[ i ] = Math.max( 0, Math.min( 255, Math.round( v ) ) );
		}
		return t;
	} );
}

// Colour correction in place — memorial_plugin_photo_color().
function colorOf( canvas, c ) {
	const [ lr, lg, lb ] = lutsOf( sampleOf( canvas ), c );
	const ctx = canvas.getContext( '2d' );
	const img = ctx.getImageData( 0, 0, canvas.width, canvas.height );
	const d = img.data;
	const k = 1 + Number( c.sat || 0 ) / 100;
	for ( let i = 0; i < d.length; i += 4 ) {
		let r = lr[ d[ i ] ];
		let g = lg[ d[ i + 1 ] ];
		let b = lb[ d[ i + 2 ] ];
		if ( k !== 1 ) {
			const l = 0.299 * r + 0.587 * g + 0.114 * b;
			r = l + ( r - l ) * k;
			g = l + ( g - l ) * k;
			b = l + ( b - l ) * k;
		}
		d[ i ] = r;
		d[ i + 1 ] = g;
		d[ i + 2 ] = b;
	}
	const a = ( Number( c.sharp || 0 ) / 100 ) * 0.5;
	if ( a > 0 ) {
		const w = canvas.width;
		const h = canvas.height;
		const src = new Uint8ClampedArray( d );
		for ( let y = 1; y < h - 1; y++ ) {
			for ( let x = 1; x < w - 1; x++ ) {
				const i = ( y * w + x ) * 4;
				for ( let ch = 0; ch < 3; ch++ ) {
					d[ i + ch ] =
						( 1 + 4 * a ) * src[ i + ch ] -
						a *
							( src[ i + ch - 4 ] +
								src[ i + ch + 4 ] +
								src[ i + ch - w * 4 ] +
								src[ i + ch + w * 4 ] );
				}
			}
		}
	}
	ctx.putImageData( img, 0, 0 );
}

// Median colour of the border — memorial_plugin_photo_border().
function borderOf( sample ) {
	const w = sample.width;
	const h = sample.height;
	const d = sample.getContext( '2d' ).getImageData( 0, 0, w, h ).data;
	const c = [ [], [], [] ];
	const add = ( x, y ) => {
		const i = ( y * w + x ) * 4;
		c[ 0 ].push( d[ i ] );
		c[ 1 ].push( d[ i + 1 ] );
		c[ 2 ].push( d[ i + 2 ] );
	};
	for ( let x = 0; x < w; x++ ) {
		add( x, 0 );
		add( x, h - 1 );
	}
	for ( let y = 0; y < h; y++ ) {
		add( 0, y );
		add( w - 1, y );
	}
	return c.map( ( list ) => {
		list.sort( ( p, q ) => p - q );
		return list[ Math.floor( list.length / 2 ) ];
	} );
}

// The frame of the photo on its new canvas — memorial_plugin_photo_frame().
function frameOf( src, crop, bg ) {
	const w = src.width;
	const h = src.height;
	let bx = 0;
	let by = 0;
	let bw = w;
	let bh = h;
	if ( crop.trim ) {
		const s = sampleOf( src );
		const sw = s.width;
		const sh = s.height;
		const d = s.getContext( '2d' ).getImageData( 0, 0, sw, sh ).data;
		let x0 = sw;
		let y0 = sh;
		let x1 = -1;
		let y1 = -1;
		for ( let y = 0; y < sh; y++ ) {
			for ( let x = 0; x < sw; x++ ) {
				const i = ( y * sw + x ) * 4;
				const diff = Math.max(
					Math.abs( d[ i ] - bg[ 0 ] ),
					Math.abs( d[ i + 1 ] - bg[ 1 ] ),
					Math.abs( d[ i + 2 ] - bg[ 2 ] )
				);
				if ( d[ i + 3 ] > 54 && diff > 28 ) {
					x0 = Math.min( x0, x );
					y0 = Math.min( y0, y );
					x1 = Math.max( x1, x );
					y1 = Math.max( y1, y );
				}
			}
		}
		if ( x1 >= 0 && ( x1 - x0 + 1 ) * ( y1 - y0 + 1 ) >= 0.05 * sw * sh ) {
			const k = w / sw;
			bx = Math.floor( x0 * k );
			by = Math.floor( y0 * k );
			bw = Math.min( w - bx, Math.ceil( ( x1 - x0 + 1 ) * k ) );
			bh = Math.min( h - by, Math.ceil( ( y1 - y0 + 1 ) * k ) );
			const p = Math.round( ( Math.max( bw, bh ) * crop.pad ) / 100 );
			bx -= p;
			by -= p;
			bw += 2 * p;
			bh += 2 * p;
		}
	}
	const m = /^(\d+):(\d+)$/.exec( crop.ratio || '' );
	if ( m ) {
		const r = Number( m[ 1 ] ) / Number( m[ 2 ] );
		if ( ! crop.trim ) {
			if ( bw / bh > r ) {
				const nw = Math.round( bh * r );
				bx += Math.floor( ( bw - nw ) / 2 );
				bw = nw;
			} else {
				const nh = Math.round( bw / r );
				by += Math.floor( ( bh - nh ) / 2 );
				bh = nh;
			}
		} else if ( bw / bh > r ) {
			const nh = Math.round( bw / r );
			by -= Math.floor( ( nh - bh ) / 2 );
			bh = nh;
		} else {
			const nw = Math.round( bh * r );
			bx -= Math.floor( ( nw - bw ) / 2 );
			bw = nw;
		}
	}
	return { bx, by, bw, bh };
}

const hexOf = ( hex ) =>
	[ 1, 3, 5 ].map( ( i ) => parseInt( hex.slice( i, i + 2 ), 16 ) );

// The whole processing of one photo in the browser.
async function processOf( base, recipe, logo ) {
	const src = canvasOf( base.naturalWidth, base.naturalHeight );
	src.getContext( '2d' ).drawImage( base, 0, 0 );
	const bg = recipe.bg ? hexOf( recipe.bg ) : borderOf( sampleOf( src ) );
	// С новым фоном сначала цвет самого предмета, потом он ложится на цвет фона.
	if ( recipe.bg && recipe.color ) {
		colorOf( src, recipe.color );
	}
	let out = src;
	if ( recipe.crop ) {
		const f = frameOf( src, recipe.crop, bg );
		out = canvasOf( f.bw, f.bh );
		const ctx = out.getContext( '2d' );
		ctx.fillStyle = `rgb(${ bg[ 0 ] },${ bg[ 1 ] },${ bg[ 2 ] })`;
		ctx.fillRect( 0, 0, f.bw, f.bh );
		ctx.drawImage( src, -f.bx, -f.by );
	} else if ( recipe.bg ) {
		// The sample of Actions is transparent around the object: on the background colour.
		out = canvasOf( src.width, src.height );
		const ctx = out.getContext( '2d' );
		ctx.fillStyle = recipe.bg;
		ctx.fillRect( 0, 0, src.width, src.height );
		ctx.drawImage( src, 0, 0 );
	}
	if ( recipe.color && ! recipe.bg ) {
		colorOf( out, recipe.color );
	}
	if ( recipe.logo && logo ) {
		const ctx = out.getContext( '2d' );
		const lw = Math.max(
			1,
			Math.round( ( out.width * Number( recipe.logo.size ) ) / 100 )
		);
		const lh = Math.round(
			( logo.naturalHeight * lw ) / logo.naturalWidth
		);
		const mg = Math.round(
			( out.width * Number( recipe.logo.margin ) ) / 100
		);
		ctx.globalAlpha = Number( recipe.logo.opacity ) / 100;
		ctx.drawImage(
			logo,
			out.width - lw - mg,
			out.height - lh - mg,
			lw,
			lh
		);
		ctx.globalAlpha = 1;
	}
	return out;
}

// The report of bad photos and the state of the processing.
function Report() {
	const [ data, setData ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const load = () =>
		apiFetch( { path: '/memorial-plugin/v1/photos/report' } )
			.then( setData )
			.catch( () => setData( { items: [] } ) );
	useEffect( () => {
		load();
	}, [] );
	if ( ! data ) {
		return <Spinner />;
	}
	const restore = async () => {
		if (
			! window.confirm(
				__(
					'Return the originals of all photos and switch the processing off?',
					'memorial-plugin'
				)
			)
		) {
			return;
		}
		setBusy( true );
		await apiFetch( {
			path: '/memorial-plugin/v1/photos/restore-all',
			method: 'POST',
		} ).catch( () => {} );
		setBusy( false );
		load();
	};
	return (
		<>
			<p>
				{ sprintf(
					/* translators: 1: processed photos, 2: photos to process. */
					__( 'Processed: %1$d of %2$d.', 'memorial-plugin' ),
					data.processed || 0,
					data.scope || 0
				) }{ ' ' }
				{ data.state?.wait
					? sprintf(
							/* translators: %d: number of photos. */
							__(
								'Waiting for the background from GitHub Actions: %d.',
								'memorial-plugin'
							),
							data.state.wait
						)
					: '' }
			</p>
			{ data.processed > 0 && (
				<Button
					variant="secondary"
					isDestructive
					isBusy={ busy }
					onClick={ restore }
				>
					{ __( 'Return all originals', 'memorial-plugin' ) }
				</Button>
			) }
			{ data.items.length > 0 && (
				<>
					<h3 className="memorial-plugin-photo-preview__h">
						{ sprintf(
							/* translators: %d: number of photos. */
							__( 'Photos to look at: %d', 'memorial-plugin' ),
							data.items.length
						) }
					</h3>
					<ul className="memorial-plugin-photo-preview__report">
						{ data.items.map( ( r ) => (
							<li key={ r.id }>
								{ r.thumb && <img src={ r.thumb } alt="" /> }
								<span>
									<a href={ r.edit }>{ r.title || r.id }</a>
									{ r.width ? ` · ${ r.width } px` : '' }
									<br />
									{ r.problems
										.map(
											( p ) =>
												PROBLEMS[
													p.split( ':' )[ 0 ]
												] || p
										)
										.join( ', ' ) }
								</span>
							</li>
						) ) }
					</ul>
				</>
			) }
		</>
	);
}

export default function PhotoPreview( { value } ) {
	const [ data, setData ] = useState( null );
	const [ chosen, setChosen ] = useState( 0 );
	const [ mode, setMode ] = useState( 'after' );
	const [ split, setSplit ] = useState( 50 );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const holder = useRef( null );

	useEffect( () => {
		apiFetch( {
			path:
				'/memorial-plugin/v1/photos/samples' +
				( chosen ? '?id=' + chosen : '' ),
		} )
			.then( setData )
			.catch( ( e ) => setError( e.message ) );
	}, [ chosen ] );

	const photo = useMemo( () => {
		if ( ! data?.items?.length ) {
			return null;
		}
		return data.items.find( ( p ) => p.id === chosen ) || data.items[ 0 ];
	}, [ data, chosen ] );

	const recipe = useMemo(
		() => ( photo ? recipeOf( value, photo.kind, photo.type ) : {} ),
		[ value, photo ]
	);
	const bgMissing = !! ( recipe.bg && photo && ! photo.bg );

	// Recount after every change of the settings (a short pause — the sliders do not freeze the screen).
	useEffect( () => {
		if ( ! photo ) {
			return;
		}
		let stop = false;
		const timer = setTimeout( async () => {
			setBusy( true );
			try {
				const base = await loadImage(
					recipe.bg && photo.bg ? photo.bg : photo.url
				);
				const logo =
					recipe.logo && data.logo
						? await loadImage(
								value.logo?.file || data.logo
							).catch( () => null )
						: null;
				const out = await processOf( base, recipe, logo );
				if ( ! stop && holder.current ) {
					out.className = 'memorial-plugin-photo-preview__after';
					holder.current.replaceChildren( out );
				}
				setError( '' );
			} catch ( e ) {
				setError(
					__( 'The photo could not be loaded.', 'memorial-plugin' )
				);
			}
			setBusy( false );
		}, 250 );
		return () => {
			stop = true;
			clearTimeout( timer );
		};
	}, [ photo, recipe, data, value.logo?.file ] );

	if ( ! data && ! error ) {
		return (
			<Card className="memorial-plugin-photo-preview">
				<CardBody>
					<Spinner />
				</CardBody>
			</Card>
		);
	}

	return (
		<Card className="memorial-plugin-photo-preview">
			<CardHeader>
				<h2 className="memorial-plugin-settings__card-title">
					{ __( 'Preview', 'memorial-plugin' ) }
				</h2>
				{ busy && <Spinner /> }
			</CardHeader>
			<CardBody>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ data?.items?.length > 0 && (
					<Flex direction="column" gap={ 3 }>
						<SelectControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __( 'Photo', 'memorial-plugin' ) }
							value={ String( photo?.id || '' ) }
							options={ ( data.all || data.items ).map(
								( p ) => ( {
									value: String( p.id ),
									label:
										( KINDS[ p.kind ] || '' ) +
										': ' +
										( p.title || p.id ),
								} )
							) }
							onChange={ ( v ) => setChosen( Number( v ) ) }
						/>
						<ToggleGroupControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							isBlock
							label={ __( 'Show', 'memorial-plugin' ) }
							value={ mode }
							onChange={ setMode }
						>
							<ToggleGroupControlOption
								value="before"
								label={ __( 'Before', 'memorial-plugin' ) }
							/>
							<ToggleGroupControlOption
								value="after"
								label={ __( 'After', 'memorial-plugin' ) }
							/>
							<ToggleGroupControlOption
								value="split"
								label={ __( 'Compare', 'memorial-plugin' ) }
							/>
						</ToggleGroupControl>
						<div
							className={
								'memorial-plugin-photo-preview__stage is-' +
								mode
							}
							style={ { '--split': split + '%' } }
						>
							{ photo && (
								<img
									className="memorial-plugin-photo-preview__before"
									src={ photo.url }
									alt={ __( 'Before', 'memorial-plugin' ) }
								/>
							) }
							<div
								ref={ holder }
								className="memorial-plugin-photo-preview__holder"
							/>
						</div>
						{ mode === 'split' && (
							<RangeControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Border', 'memorial-plugin' ) }
								value={ split }
								min={ 0 }
								max={ 100 }
								onChange={ ( v ) => setSplit( v ?? 50 ) }
							/>
						) }
						{ bgMissing && (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'The background of this photo is not ready yet: run “Photos — background” in GitHub Actions (Actions → Photos — background → Run workflow); in 3–5 minutes the sample appears here. Until then the preview shows the rest without the new background.',
									'memorial-plugin'
								) }
							</Notice>
						) }
						{ value.main && ! value.main.on && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Processing is switched off: the site shows the originals. The preview shows what the photos will look like when it is on.',
									'memorial-plugin'
								) }
							</Notice>
						) }
					</Flex>
				) }
				{ data && ! data.items?.length && (
					<p>{ __( 'No photos to show yet.', 'memorial-plugin' ) }</p>
				) }
				<Report />
			</CardBody>
		</Card>
	);
}
