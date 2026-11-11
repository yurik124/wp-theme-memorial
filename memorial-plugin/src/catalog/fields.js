/**
 * Field kinds shared by the catalog lists: photos, a set of terms (categories, kinds), status, order, money.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Flex,
	FlexBlock,
	FlexItem,
	TextControl,
} from '@wordpress/components';
import Photos from '../shared/photos';

/**
 * Price in roubles: 12 500 ₽.
 *
 * @param {number} n Number.
 * @return {string} Text.
 */
export const rub = ( n ) =>
	sprintf(
		/* translators: %s: amount. */
		__( '%s ₽', 'memorial-plugin' ),
		Number( n || 0 ).toLocaleString( 'ru-RU' )
	);

/**
 * Photos: [{id, url}] (the first one is the main photo).
 *
 * @param {string} label Label.
 * @return {Object} Field.
 */
export function photosField( label ) {
	return {
		id: 'photos',
		label,
		enableSorting: false,
		enableHiding: false,
		render: ( { item } ) => {
			const [ first ] = item.photos || [];
			if ( ! first ) {
				return (
					<span className="memorial-plugin-catalog__nophoto">
						{ __( 'No photo', 'memorial-plugin' ) }
					</span>
				);
			}
			return (
				<span className="memorial-plugin-catalog__photo">
					<img src={ first.url } alt="" />
					{ item.photos.length > 1 && (
						<span className="memorial-plugin-catalog__count">
							{ item.photos.length }
						</span>
					) }
				</span>
			);
		},
		Edit: ( { data, field, onChange } ) => (
			<Photos
				label={ field.label }
				value={ ( data.photos || [] )
					.map( ( p ) => ( p.id ? p.id : p ) )
					.join( ',' ) }
				onChange={ ( v ) =>
					onChange( {
						photos: v
							? v
									.split( ',' )
									.map( ( id ) => ( { id: Number( id ) } ) )
							: [],
					} )
				}
			/>
		),
	};
}

/**
 * A set of terms: [ids] with checkboxes.
 *
 * @param {string}   id       Field ID.
 * @param {string}   label    Label.
 * @param {Object[]} elements [{value, label}].
 * @return {Object} Field.
 */
export function termsField( id, label, elements ) {
	return {
		id,
		label,
		type: 'text',
		elements,
		enableSorting: false,
		filterBy: { operators: [ 'isAny', 'isNone' ] },
		getValue: ( { item } ) => ( item[ id ] || [] ).join( ',' ),
		render: ( { item } ) =>
			( item[ id ] || [] )
				.map( ( v ) => elements.find( ( e ) => e.value === v )?.label )
				.filter( Boolean )
				.map( ( l ) => l.replace( /^(— )+/, '' ) )
				.join( ', ' ) || '—',
		Edit: ( { data, field, onChange } ) => {
			const value = data[ id ] || [];
			if ( ! elements.length ) {
				return null;
			}
			return (
				<fieldset className="memorial-plugin-catalog__terms">
					<legend>{ field.label }</legend>
					{ elements.map( ( e ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ e.value }
							label={ e.label }
							checked={ value.includes( e.value ) }
							onChange={ ( on ) =>
								onChange( {
									[ id ]: on
										? [ ...value, e.value ]
										: value.filter(
												( v ) => v !== e.value
											),
								} )
							}
						/>
					) ) }
				</fieldset>
			);
		},
	};
}

/**
 * Shown on the site / hidden.
 *
 * @return {Object} Field.
 */
export function statusField() {
	return {
		id: 'status',
		label: __( 'On the site', 'memorial-plugin' ),
		type: 'text',
		elements: [
			{ value: 'publish', label: __( 'Shown', 'memorial-plugin' ) },
			{ value: 'draft', label: __( 'Hidden', 'memorial-plugin' ) },
		],
		filterBy: { operators: [ 'is' ] },
		render: ( { item } ) =>
			item.status === 'publish' ? (
				__( 'Shown', 'memorial-plugin' )
			) : (
				<span className="memorial-plugin-catalog__hidden">
					{ __( 'Hidden', 'memorial-plugin' ) }
				</span>
			),
	};
}

/**
 * Order on the site.
 *
 * @return {Object} Field.
 */
export function orderField() {
	return {
		id: 'order',
		label: __( 'Order', 'memorial-plugin' ),
		type: 'integer',
		description: __(
			'Smaller first. The arrows in the row menu move the item one place.',
			'memorial-plugin'
		),
		filterBy: false,
	};
}

/**
 * “What was done — price” rows of a work.
 *
 * @param {Object}   props
 * @param {Object}   props.data     Item.
 * @param {Object}   props.field    Field.
 * @param {() => void} props.onChange Change.
 */
export function RowsEdit( { data, field, onChange } ) {
	const rows = data.rows || [];
	const set = ( list ) => onChange( { rows: list } );
	const total = rows.reduce( ( s, r ) => s + ( Number( r[ 1 ] ) || 0 ), 0 );
	return (
		<fieldset className="memorial-plugin-catalog__rows">
			<legend>{ field.label }</legend>
			{ rows.map( ( r, i ) => (
				<Flex key={ i } align="flex-end">
					<FlexBlock>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ sprintf(
								/* translators: %d: row number. */
								__( 'Work %d', 'memorial-plugin' ),
								i + 1
							) }
							value={ r[ 0 ] }
							onChange={ ( v ) =>
								set(
									rows.map( ( x, j ) =>
										j === i ? [ v, x[ 1 ] ] : x
									)
								)
							}
						/>
					</FlexBlock>
					<FlexItem>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							type="number"
							min={ 0 }
							label={ __( 'Price, ₽', 'memorial-plugin' ) }
							value={ r[ 1 ] || '' }
							onChange={ ( v ) =>
								set(
									rows.map( ( x, j ) =>
										j === i
											? [ x[ 0 ], parseInt( v, 10 ) || 0 ]
											: x
									)
								)
							}
						/>
					</FlexItem>
					<FlexItem>
						<Button
							icon="no-alt"
							label={ __( 'Remove row', 'memorial-plugin' ) }
							onClick={ () =>
								set( rows.filter( ( x, j ) => j !== i ) )
							}
						/>
					</FlexItem>
				</Flex>
			) ) }
			<Flex>
				<Button
					variant="secondary"
					onClick={ () => set( [ ...rows, [ '', 0 ] ] ) }
				>
					{ __( 'Add row', 'memorial-plugin' ) }
				</Button>
				<span>
					{ sprintf(
						/* translators: %s: total price. */
						__( 'Total: %s', 'memorial-plugin' ),
						rub( total )
					) }
				</span>
			</Flex>
		</fieldset>
	);
}
