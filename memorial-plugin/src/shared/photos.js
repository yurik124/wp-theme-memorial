/**
 * Photo list field: IDs “12,34,56” (the first one is the main photo) — add from the media library
 * (several at once, files can be dropped into the window), reorder, remove.
 */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Flex } from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';

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

function Thumb( { id } ) {
	const url = useSelect(
		( select ) => {
			const media = select( coreStore ).getMedia( id, {
				context: 'view',
			} );
			return (
				media?.media_details?.sizes?.thumbnail?.source_url ||
				media?.source_url
			);
		},
		[ id ]
	);
	return url ? (
		<img src={ url } alt="" width="72" height="72" />
	) : (
		<span className="memorial-plugin-photos__empty" />
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
						<Thumb id={ id } />
						<Flex justify="center" gap={ 0 }>
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
							<Button
								size="small"
								icon="no-alt"
								isDestructive
								label={ sprintf(
									/* translators: %d: photo number. */
									__( 'Remove photo %d', 'memorial-plugin' ),
									i + 1
								) }
								onClick={ () =>
									save( ids.filter( ( x ) => x !== id ) )
								}
							/>
						</Flex>
					</li>
				) ) }
			</ul>
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
