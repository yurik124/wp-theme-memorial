/**
 * Photo list field: IDs “12,34,56” (the first one is the main photo) — add from the media library
 * (several at once, files can be dropped into the window), reorder, remove.
 */
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { store as coreStore } from '@wordpress/core-data';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';

/**
 * Large photos one at a time: the photo fits the window whole (no scrolling), arrows and keys ← → go on.
 *
 * @param {Object}     props
 * @param {string}     props.title   Title of the window.
 * @param {string[]}   props.urls    URLs of large photos.
 * @param {number}     props.start   Photo to open first.
 * @param {() => void} props.onClose Close.
 */
export function PhotoViewer( { title, urls, start = 0, onClose } ) {
	const [ i, setI ] = useState( start );
	const n = urls.length;
	const go = ( d ) => setI( ( x ) => ( x + d + n ) % n );
	return (
		<Modal
			title={ title }
			size="large"
			className="memorial-plugin-viewer"
			onRequestClose={ onClose }
		>
			{ /* eslint-disable-next-line jsx-a11y/no-static-element-interactions -- arrow keys of the dialog */ }
			<div
				className="memorial-plugin-viewer__stage"
				onKeyDown={ ( e ) => {
					if ( n > 1 && e.key === 'ArrowLeft' ) {
						go( -1 );
					}
					if ( n > 1 && e.key === 'ArrowRight' ) {
						go( 1 );
					}
				} }
			>
				{ n > 1 && (
					<Button
						className="memorial-plugin-viewer__arrow"
						icon="arrow-left-alt2"
						label={ __( 'Previous photo', 'memorial-plugin' ) }
						onClick={ () => go( -1 ) }
					/>
				) }
				<img src={ urls[ i ] } alt="" />
				{ n > 1 && (
					<Button
						className="memorial-plugin-viewer__arrow"
						icon="arrow-right-alt2"
						label={ __( 'Next photo', 'memorial-plugin' ) }
						onClick={ () => go( 1 ) }
					/>
				) }
			</div>
			{ n > 1 && (
				<p className="memorial-plugin-viewer__count">
					{ sprintf(
						/* translators: 1: photo number, 2: number of photos. */
						__( 'Photo %1$d of %2$d', 'memorial-plugin' ),
						i + 1,
						n
					) }
				</p>
			) }
		</Modal>
	);
}

/**
 * “12,34” or [12, 34] → [12, 34].
 *
 * @param {string|number[]} value Value.
 * @return {number[]} IDs.
 */
export function photoIds( value ) {
	const list = Array.isArray( value )
		? value
		: String( value || '' ).split( ',' );
	return list.map( ( v ) => parseInt( v, 10 ) ).filter( ( v ) => v > 0 );
}

// Thumbnail and large URL of a photo from the media library.
function useMediaUrls( id ) {
	return useSelect(
		( select ) => {
			const media = select( coreStore ).getMedia( id, {
				context: 'view',
			} );
			const sizes = media?.media_details?.sizes || {};
			return {
				thumb: sizes.thumbnail?.source_url || media?.source_url,
				big: sizes.large?.source_url || media?.source_url,
			};
		},
		[ id ]
	);
}

// A thumbnail that opens the photo large.
function Thumb( { id, n, onOpen } ) {
	const { thumb } = useMediaUrls( id );
	return thumb ? (
		<button
			type="button"
			className="memorial-plugin-photos__open"
			aria-label={ sprintf(
				/* translators: %d: photo number. */
				__( 'Open photo %d large', 'memorial-plugin' ),
				n
			) }
			onClick={ onOpen }
		>
			<img src={ thumb } alt="" width="96" height="96" />
		</button>
	) : (
		<span className="memorial-plugin-photos__empty" />
	);
}

// URLs of large photos for the viewer (asked only when it is open).
function Viewer( { ids, start, title, onClose } ) {
	const urls = useSelect(
		( select ) =>
			ids.map( ( id ) => {
				const m = select( coreStore ).getMedia( id, {
					context: 'view',
				} );
				return (
					m?.media_details?.sizes?.large?.source_url ||
					m?.source_url ||
					''
				);
			} ),
		[ ids ]
	);
	return (
		<PhotoViewer
			title={ title }
			urls={ urls.filter( Boolean ) }
			start={ start }
			onClose={ onClose }
		/>
	);
}

/**
 * @param {Object}     props
 * @param {string}     props.label    Field label.
 * @param {string}     props.value    IDs separated by commas.
 * @param {() => void} props.onChange Gets the new IDs string.
 */
export default function Photos( { label, value, onChange } ) {
	const ids = photoIds( value );
	const [ viewing, setViewing ] = useState( -1 );
	const save = ( list ) => onChange( list.join( ',' ) );
	const move = ( i, d ) => {
		const list = [ ...ids ];
		[ list[ i ], list[ i + d ] ] = [ list[ i + d ], list[ i ] ];
		save( list );
	};
	return (
		<fieldset className="memorial-plugin-photos">
			<legend>{ label }</legend>
			<ul>
				{ ids.map( ( id, i ) => (
					<li key={ id }>
						<Thumb
							id={ id }
							n={ i + 1 }
							onOpen={ () => setViewing( i ) }
						/>
						<Button
							size="small"
							className="memorial-plugin-photos__remove"
							icon="no-alt"
							label={ sprintf(
								/* translators: %d: photo number. */
								__( 'Remove photo %d', 'memorial-plugin' ),
								i + 1
							) }
							onClick={ () =>
								save( ids.filter( ( x ) => x !== id ) )
							}
						/>
						<div className="memorial-plugin-photos__move">
							<Button
								size="small"
								icon="arrow-left-alt2"
								label={ sprintf(
									/* translators: %d: photo number. */
									__(
										'Move photo %d earlier',
										'memorial-plugin'
									),
									i + 1
								) }
								disabled={ i === 0 }
								onClick={ () => move( i, -1 ) }
							/>
							<Button
								size="small"
								icon="arrow-right-alt2"
								label={ sprintf(
									/* translators: %d: photo number. */
									__(
										'Move photo %d later',
										'memorial-plugin'
									),
									i + 1
								) }
								disabled={ i === ids.length - 1 }
								onClick={ () => move( i, 1 ) }
							/>
						</div>
					</li>
				) ) }
			</ul>
			{ viewing >= 0 && (
				<Viewer
					ids={ ids }
					start={ viewing }
					title={ label }
					onClose={ () => setViewing( -1 ) }
				/>
			) }
			<MediaUpload
				allowedTypes={ [ 'image' ] }
				multiple
				value={ ids }
				onSelect={ ( media ) =>
					save( [
						...ids,
						...( Array.isArray( media ) ? media : [ media ] )
							.map( ( m ) => m.id )
							.filter( ( id ) => ! ids.includes( id ) ),
					] )
				}
				render={ ( { open } ) => (
					<Button variant="secondary" onClick={ open }>
						{ __( 'Add photos', 'memorial-plugin' ) }
					</Button>
				) }
			/>
		</fieldset>
	);
}
