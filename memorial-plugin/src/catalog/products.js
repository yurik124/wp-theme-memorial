/**
 * Catalog → Products: one tab per product type, the specifications of the type as columns.
 */
import { useCallback, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Flex,
	Notice,
	RadioControl,
	SelectControl,
	TabPanel,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';
import BooleanEdit from '../shared/boolean-edit';
import { runAbility, download } from '../shared/abilities';
import {
	useListData,
	bySiteOrder,
	EditModal,
	ModalButtons,
	ListPage,
	arrayFilters,
} from './list-screen';
import { photosField, termsField, statusField, rub } from './fields';

const API = '/memorial-plugin/v1/catalog/products';
const catsFilter = arrayFilters( [ 'cats' ] );

/**
 * Product → row: the specifications become spec0, spec1… by the order of the type’s fields.
 *
 * @param {Object}   p     Product from the server.
 * @param {Object[]} specs Type fields.
 * @return {Object} Row.
 */
function toRow( p, specs ) {
	const row = { ...p };
	specs.forEach( ( s, i ) => {
		row[ 'spec' + i ] = p.specs?.[ s.label ] || '';
	} );
	return row;
}

// Description of a product: plain text; one made in the editor with lists or photos is only shown here.
function DescEdit( { data, field, onChange } ) {
	if ( data.rich ) {
		return (
			<div className="memorial-plugin-catalog__desc">
				<span className="memorial-plugin-catalog__desc-label">
					{ field.label }
				</span>
				<p>{ data.desc }</p>
				<p>
					<a href={ data.edit }>
						{ __(
							'This description has formatting — change it in the editor',
							'memorial-plugin'
						) }
					</a>
				</p>
			</div>
		);
	}
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ field.label }
			rows={ 5 }
			value={ data.desc || '' }
			onChange={ ( v ) => onChange( { desc: v } ) }
		/>
	);
}

function PriceModal( { items, closeModal, run } ) {
	const [ mode, setMode ] = useState( 'pct' );
	const [ value, setValue ] = useState( '' );
	const [ round, setRound ] = useState( '10' );
	const [ busy, setBusy ] = useState( false );
	return (
		<Flex direction="column" gap={ 4 }>
			<RadioControl
				label={ __( 'How to change', 'memorial-plugin' ) }
				selected={ mode }
				options={ [
					{
						value: 'pct',
						label: __(
							'By percent (+10 or −5)',
							'memorial-plugin'
						),
					},
					{
						value: 'add',
						label: __(
							'Add or subtract roubles',
							'memorial-plugin'
						),
					},
					{
						value: 'set',
						label: __( 'Set the same price', 'memorial-plugin' ),
					},
				] }
				onChange={ setMode }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={
					mode === 'pct'
						? __( 'Percent', 'memorial-plugin' )
						: __( 'Roubles', 'memorial-plugin' )
				}
				value={ value }
				onChange={ setValue }
				help={
					mode === 'set'
						? ''
						: __(
								'Products with the price “on request” stay as they are.',
								'memorial-plugin'
							)
				}
			/>
			{ /* One price is set exactly as typed: rounding is only for a computed price. */ }
			{ mode !== 'set' && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Round to', 'memorial-plugin' ) }
					value={ round }
					options={ [ '1', '10', '50', '100' ].map( ( r ) => ( {
						value: r,
						label: rub( r ),
					} ) ) }
					onChange={ setRound }
				/>
			) }
			<ModalButtons
				label={ sprintf(
					/* translators: %d: number of products. */
					_n(
						'Change %d price',
						'Change %d prices',
						items.length,
						'memorial-plugin'
					),
					items.length
				) }
				busy={ busy }
				disabled={ value === '' }
				onCancel={ closeModal }
				onOk={ async () => {
					setBusy( true );
					await run( items, {
						mode,
						value: Number( String( value ).replace( ',', '.' ) ),
						round: Number( round ),
					} );
					closeModal();
				} }
			/>
		</Flex>
	);
}

function CatsModal( { items, closeModal, run, op, cats } ) {
	const [ chosen, setChosen ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const field = termsField(
		'cats',
		__( 'Categories', 'memorial-plugin' ),
		cats
	);
	return (
		<Flex direction="column" gap={ 4 }>
			<field.Edit
				data={ { cats: chosen } }
				field={ field }
				onChange={ ( e ) => setChosen( e.cats ) }
			/>
			<ModalButtons
				label={
					op === 'cats_add'
						? __( 'Add', 'memorial-plugin' )
						: __( 'Remove', 'memorial-plugin' )
				}
				busy={ busy }
				disabled={ ! chosen.length }
				onCancel={ closeModal }
				onOk={ async () => {
					setBusy( true );
					await run( items, { op, cats: chosen } );
					closeModal();
				} }
			/>
		</Flex>
	);
}

function MergeModal( { items, closeModal, run } ) {
	const [ conflicts, setConflicts ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const main = bySiteOrder( items )[ 0 ];
	return (
		<Flex direction="column" gap={ 4 }>
			<p>
				{ sprintf(
					/* translators: %s: product title. */
					__(
						'The products become one: “%s” gets the photos of all of them, the missing fields and all categories; the others go to the trash and their addresses lead to it.',
						'memorial-plugin'
					),
					main.title
				) }
			</p>
			{ conflicts && (
				<Notice status="warning" isDismissible={ false }>
					<p>
						{ __(
							'Cannot merge: the values differ.',
							'memorial-plugin'
						) }
					</p>
					<ul>
						{ conflicts.map( ( c ) => (
							<li key={ c }>{ c }</li>
						) ) }
					</ul>
				</Notice>
			) }
			<ModalButtons
				label={ __( 'Merge', 'memorial-plugin' ) }
				busy={ busy }
				disabled={ !! conflicts }
				onCancel={ closeModal }
				onOk={ async () => {
					setBusy( true );
					const res = await run( bySiteOrder( items ), {
						op: 'merge',
					} );
					if ( res?.conflicts ) {
						setConflicts( res.conflicts );
						setBusy( false );
						return;
					}
					closeModal();
				} }
			/>
		</Flex>
	);
}

export default function Products() {
	const { data, error, merge, reload } = useListData( API );
	const [ notice, setNotice ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ selection, setSelection ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const [ typeId, setTypeId ] = useState(
		() =>
			Number(
				new URLSearchParams( window.location.search ).get( 'type' )
			) || 0
	);
	const [ view, setView ] = useState( {
		type: 'table',
		titleField: 'title',
		mediaField: 'photos',
		fields: [ 'price', 'sub', 'cats', 'status' ],
		// New products first: a product just added is at the top of the list.
		sort: { field: 'date', direction: 'desc' },
		perPage: 50,
		page: 1,
		filters: [],
		search: '',
	} );

	const types = useMemo( () => {
		if ( ! data ) {
			return [];
		}
		const list = [ ...data.types ];
		if ( data.items.some( ( i ) => ! i.type ) ) {
			list.push( {
				id: 0,
				name: __( 'Without type', 'memorial-plugin' ),
				fields: [],
				cats: [],
			} );
		}
		return list;
	}, [ data ] );
	const type = types.find( ( t ) => t.id === typeId ) || types[ 0 ];
	const specs = useMemo( () => type?.fields || [], [ type ] );

	const items = useMemo(
		() =>
			data && type
				? data.items
						.filter( ( i ) => i.type === type.id )
						.map( ( i ) => toRow( i, specs ) )
				: [],
		[ data, type, specs ]
	);

	const fields = useMemo( () => {
		const cats = ( type?.cats || [] ).map( ( c ) => ( {
			value: c.id,
			label: c.name,
		} ) );
		return [
			photosField( __( 'Photos', 'memorial-plugin' ), data?.logo ),
			{
				id: 'title',
				label: __( 'Title', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableHiding: false,
			},
			{
				id: 'price',
				label: __( 'Price, ₽', 'memorial-plugin' ),
				type: 'integer',
				description: __( '0 — “price on request”.', 'memorial-plugin' ),
				filterBy: false,
				render: ( { item } ) =>
					item.price
						? ( item.from
								? __( 'from', 'memorial-plugin' ) + ' '
								: '' ) +
							rub( item.price ) +
							( item.unit ? ' ' + item.unit : '' )
						: __( 'on request', 'memorial-plugin' ),
			},
			{
				id: 'from',
				label: __( 'Show “from”', 'memorial-plugin' ),
				type: 'boolean',
				elements: [
					{ value: true, label: __( 'Yes', 'memorial-plugin' ) },
					{ value: false, label: __( 'No', 'memorial-plugin' ) },
				],
				Edit: BooleanEdit,
				filterBy: false,
			},
			{
				id: 'unit',
				label: __( 'Unit', 'memorial-plugin' ),
				type: 'text',
				placeholder: __( 'for example: per metre', 'memorial-plugin' ),
				filterBy: false,
			},
			{
				id: 'sub',
				label: __( 'Line under the title', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				filterBy: false,
			},
			...specs.map( ( s, i ) => ( {
				id: 'spec' + i,
				label: s.label + ( s.unit ? ', ' + s.unit : '' ),
				type: 'text',
				enableGlobalSearch: true,
				...( s.type === 'list' && s.options.length
					? {
							elements: [
								{ value: '', label: '—' },
								...s.options.map( ( o ) => ( {
									value: o,
									label: o,
								} ) ),
							],
							filterBy: { operators: [ 'isAny' ] },
						}
					: { filterBy: false } ),
			} ) ),
			...( cats.length
				? [
						termsField(
							'cats',
							__( 'Categories', 'memorial-plugin' ),
							cats
						),
					]
				: [] ),
			{
				id: 'desc',
				label: __( 'Description', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableSorting: false,
				filterBy: false,
				Edit: DescEdit,
				render: ( { item } ) =>
					( item.desc || '' ).length > 80
						? item.desc.slice( 0, 80 ) + '…'
						: item.desc || '',
			},
			statusField(),
			{
				id: 'date',
				label: __( 'Added', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
				readOnly: true,
				render: ( { item } ) =>
					item.date
						? new Date( item.date * 1000 ).toLocaleDateString(
								'ru-RU'
							)
						: '',
			},
		];
	}, [ type, specs, data?.logo ] );
	// The form: everything but the date of adding.
	const formFields = useMemo(
		() => fields.filter( ( f ) => f.id !== 'date' ),
		[ fields ]
	);

	const run = useCallback(
		async ( list, body ) => {
			setBusy( true );
			try {
				const res = await apiFetch( {
					path: API + '/bulk',
					method: 'POST',
					data: { ids: list.map( ( i ) => i.id ), ...body },
				} );
				if ( ! res.conflicts ) {
					merge( res );
					// Merged or removed products are gone: nothing stays selected.
					if ( [ 'merge', 'trash' ].includes( body.op ) ) {
						setSelection( [] );
					}
					setNotice( {
						status: 'success',
						text: __( 'Done.', 'memorial-plugin' ),
					} );
				}
				return res;
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			} finally {
				setBusy( false );
			}
		},
		[ merge ]
	);

	// Abilities (admin/abilities.php): the same actions as in the Command Palette and the REST API.
	const ability = useCallback(
		async ( name, input ) => {
			setBusy( true );
			try {
				const res = await runAbility( name, input );
				await reload();
				setNotice( {
					status: 'success',
					text: __( 'Done.', 'memorial-plugin' ),
				} );
				return res;
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			} finally {
				setBusy( false );
			}
		},
		[ reload ]
	);

	const exportCsv = useCallback( async () => {
		setBusy( true );
		try {
			const res = await runAbility( 'memorial-plugin/export-catalog', {
				type: type?.id || 0,
			} );
			download( res.filename, res.csv );
		} catch ( e ) {
			setNotice( { status: 'error', text: e.message } );
		} finally {
			setBusy( false );
		}
	}, [ type ] );

	const create = useCallback(
		async ( photos = [], values = null ) => {
			setBusy( true );
			try {
				const res = await apiFetch( {
					path: API,
					method: 'POST',
					data: values
						? { type: type.id, values }
						: { type: type.id, photos },
				} );
				merge( res );
				setView( ( v ) => ( {
					...v,
					page: 1,
					sort: { field: 'date', direction: 'desc' },
				} ) );
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: %d: number of products. */
						_n(
							'%d product added at the top of the list. It is hidden from the site: fill it in and tick “Show on the site”.',
							'%d products added at the top of the list. They are hidden from the site: fill them in and tick “Show on the site”.',
							res.items.length,
							'memorial-plugin'
						),
						res.items.length
					),
				} );
				if ( ! values && res.items.length === 1 ) {
					setEditing( toRow( res.items[ 0 ], specs ) );
				}
			} catch ( e ) {
				if ( values ) {
					throw e; // The form shows the reason and stays open.
				}
				setNotice( { status: 'error', text: e.message } );
			} finally {
				setBusy( false );
			}
		},
		[ type, specs, merge ]
	);

	// A product that does not exist yet: the form of “Add product”; it is created only by “Save”.
	const blank = useCallback(
		() =>
			toRow(
				{
					id: 0,
					title: '',
					status: 'draft',
					type: type.id,
					photos: [],
					price: type.defaults?.price || 0,
					from: !! type.defaults?.from,
					unit: type.defaults?.unit || '',
					sub: '',
					desc: '',
					specs: Object.fromEntries(
						specs
							.filter( ( s ) => s.default )
							.map( ( s ) => [ s.label, s.default ] )
					),
					cats: type.defaults?.cats || [],
				},
				specs
			),
		[ type, specs ]
	);

	const save = useCallback(
		async ( item, edits ) => {
			const values = {};
			const specValues = {};
			Object.entries( edits ).forEach( ( [ k, v ] ) => {
				const m = k.match( /^spec(\d+)$/ );
				if ( m ) {
					specValues[ specs[ m[ 1 ] ].label ] = v;
				} else if ( k === 'photos' ) {
					values.photos = v.map( ( p ) => p.id );
				} else {
					values[ k ] = v;
				}
			} );
			if ( Object.keys( specValues ).length ) {
				values.specs = specValues;
			}
			if ( ! item.id ) {
				await create( [], values );
				return;
			}
			const res = await apiFetch( {
				path: API + '/' + item.id,
				method: 'PUT',
				data: { values },
			} );
			merge( { items: [ res ] } );
			setNotice( {
				status: 'success',
				text: __( 'Saved.', 'memorial-plugin' ),
			} );
		},
		[ specs, merge, create ]
	);

	const actions = useMemo( () => {
		const cats = ( type?.cats || [] ).map( ( c ) => ( {
			value: c.id,
			label: c.name,
		} ) );
		const list = [
			{
				id: 'edit',
				label: __( 'Edit', 'memorial-plugin' ),
				isPrimary: true,
				icon: 'edit',
				callback: ( [ item ] ) => setEditing( item ),
			},
			{
				id: 'editor',
				label: __( 'Open in the editor', 'memorial-plugin' ),
				callback: ( [ item ] ) => ( window.location.href = item.edit ),
			},
			{
				id: 'view',
				label: __( 'View on the site', 'memorial-plugin' ),
				isEligible: ( item ) => !! item.view,
				callback: ( [ item ] ) =>
					window.open( item.view, '_blank', 'noopener' ),
			},
			{
				id: 'price',
				label: __( 'Change prices…', 'memorial-plugin' ),
				supportsBulk: true,
				RenderModal: ( props ) => (
					<PriceModal
						{ ...props }
						run={ ( list2, input ) =>
							ability( 'memorial-plugin/change-prices', {
								ids: list2.map( ( i ) => i.id ),
								...input,
							} )
						}
					/>
				),
			},
			{
				id: 'publish',
				label: __( 'Show on the site', 'memorial-plugin' ),
				supportsBulk: true,
				isEligible: ( item ) => item.status !== 'publish',
				callback: ( list2 ) =>
					ability( 'memorial-plugin/set-visibility', {
						kind: 'products',
						ids: list2.map( ( i ) => i.id ),
						visible: true,
					} ),
			},
			{
				id: 'draft',
				label: __( 'Hide from the site', 'memorial-plugin' ),
				supportsBulk: true,
				isEligible: ( item ) => item.status === 'publish',
				callback: ( list2 ) =>
					ability( 'memorial-plugin/set-visibility', {
						kind: 'products',
						ids: list2.map( ( i ) => i.id ),
						visible: false,
					} ),
			},
			{
				id: 'copy',
				label: __( 'Copy', 'memorial-plugin' ),
				supportsBulk: true,
				callback: ( list2 ) => run( list2, { op: 'copy' } ),
			},
			{
				id: 'merge',
				label: __( 'Merge into one…', 'memorial-plugin' ),
				supportsBulk: true,
				RenderModal: ( props ) => (
					<MergeModal { ...props } run={ run } />
				),
			},
			{
				id: 'trash',
				label: __( 'Move to trash', 'memorial-plugin' ),
				isDestructive: true,
				supportsBulk: true,
				callback: ( list2 ) => run( list2, { op: 'trash' } ),
			},
		];
		if ( cats.length ) {
			list.splice(
				4,
				0,
				{
					id: 'cats_add',
					label: __( 'Add to categories…', 'memorial-plugin' ),
					supportsBulk: true,
					RenderModal: ( props ) => (
						<CatsModal
							{ ...props }
							run={ run }
							op="cats_add"
							cats={ cats }
						/>
					),
				},
				{
					id: 'cats_remove',
					label: __( 'Remove from categories…', 'memorial-plugin' ),
					supportsBulk: true,
					RenderModal: ( props ) => (
						<CatsModal
							{ ...props }
							run={ run }
							op="cats_remove"
							cats={ cats }
						/>
					),
				}
			);
		}
		return list;
	}, [ type, run, ability ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	const tabs =
		types.length > 1 ? (
			<TabPanel
				className="memorial-plugin-catalog__tabs"
				tabs={ types.map( ( t ) => ( {
					name: String( t.id ),
					title: `${ t.name } (${ data.items.filter( ( i ) => i.type === t.id ).length })`,
				} ) ) }
				initialTabName={ type ? String( type.id ) : undefined }
				onSelect={ ( name ) => {
					setTypeId( Number( name ) );
					setView( ( v ) => ( { ...v, page: 1, filters: [] } ) );
					const url = new URL( window.location.href );
					url.searchParams.set( 'type', name );
					window.history.replaceState( null, '', url );
				} }
			>
				{ () => null }
			</TabPanel>
		) : null;

	return (
		<>
			<ListPage
				title={ __( 'Products', 'memorial-plugin' ) }
				subTitle={ __(
					'Price, specifications, categories and the description are changed right here. Select several products to change prices or categories at once.',
					'memorial-plugin'
				) }
				actions={
					data && (
						<Flex gap={ 2 } wrap>
							{ type && type.id > 0 && (
								<>
									<MediaUpload
										allowedTypes={ [ 'image' ] }
										multiple
										onSelect={ ( media ) =>
											create(
												( Array.isArray( media )
													? media
													: [ media ]
												).map( ( m ) => m.id )
											)
										}
										render={ ( { open } ) => (
											<Button
												variant="primary"
												onClick={ open }
												isBusy={ busy }
											>
												{ __(
													'Add from photos',
													'memorial-plugin'
												) }
											</Button>
										) }
									/>
									<Button
										variant="secondary"
										onClick={ () => setEditing( blank() ) }
										disabled={ busy }
									>
										{ __(
											'Add product',
											'memorial-plugin'
										) }
									</Button>
								</>
							) }
							{ data.canManage && (
								<Button
									variant="tertiary"
									href={ data.typesUrl }
								>
									{ __(
										'Types and categories',
										'memorial-plugin'
									) }
								</Button>
							) }
							<Button
								variant="tertiary"
								onClick={ exportCsv }
								disabled={ busy }
							>
								{ __( 'Export to CSV', 'memorial-plugin' ) }
							</Button>
							<Button variant="tertiary" href={ data.trashUrl }>
								{ __( 'Trash', 'memorial-plugin' ) }
							</Button>
						</Flex>
					)
				}
				tabs={
					data && ! data.types.length ? (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'There are no product types yet. Add one in “Types and categories”, for example “Wreaths”.',
								'memorial-plugin'
							) }
						</Notice>
					) : (
						tabs
					)
				}
				notice={ notice }
				onNotice={ () => setNotice( null ) }
				items={ items }
				fields={ fields }
				actionsOf={ actions }
				view={ view }
				setView={ setView }
				prefilter={ catsFilter }
				loading={ ! data }
				selection={ selection }
				setSelection={ setSelection }
			/>
			{ editing && (
				<EditModal
					title={
						editing.id
							? editing.title
							: __( 'New product', 'memorial-plugin' )
					}
					item={ editing }
					fields={ formFields }
					isNew={ ! editing.id }
					onSave={ ( edits ) => save( editing, edits ) }
					onClose={ () => setEditing( null ) }
				/>
			) }
		</>
	);
}
