/**
 * Catalog → Types and categories: product types on tabs; for a type — settings (DataForm),
 * specifications and categories (DataViews).
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexBlock,
	FlexItem,
	Modal,
	Notice,
	SelectControl,
	TabPanel,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	DataViews,
	DataForm,
	filterSortAndPaginate,
} from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';
import BooleanEdit from '../shared/boolean-edit';
import HintedEdit from '../shared/hinted-edit';
import { ModalButtons, moved } from './list-screen';
import { termsField } from './fields';

const API = '/memorial-plugin/v1/catalog/';

function TextareaEdit( { data, field, onChange } ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ field.label }
			help={ field.description }
			rows={ 3 }
			value={ data[ field.id ] || '' }
			onChange={ ( v ) => onChange( { [ field.id ]: v } ) }
		/>
	);
}

/**
 * Type → form data.
 *
 * @param {Object} t Type.
 * @return {Object} Data.
 */
const typeForm = ( t ) => ( {
	name: t.name,
	desc: t.desc,
	price: t.defaults.price || 0,
	from: !! t.defaults.from,
	unit: t.defaults.unit,
	cats: t.defaults.cats || [],
	showSub: !! t.showSub,
	ratio: t.ratio,
	cols: t.cols || '',
	colsPhone: String( t.colsPhone || 2 ),
	sort: t.sort || '',
} );

// How the products go on the site: '' is kept only for types made before this setting.
const sortElements = ( sorts, own, inherit ) => [
	...( inherit
		? [ { value: '', label: __( 'As in the type', 'memorial-plugin' ) } ]
		: [] ),
	...( ! inherit && ! own
		? [
				{
					value: '',
					label: __(
						'Former order (as arranged by hand)',
						'memorial-plugin'
					),
				},
			]
		: [] ),
	...( sorts || [] ),
];

function TypeSettings( { type, ratios, save } ) {
	const [ data, setData ] = useState( () => typeForm( type ) );
	const [ dirty, setDirty ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		setData( typeForm( type ) );
		setDirty( false );
	}, [ type ] );
	const fields = useMemo(
		() => [
			{
				id: 'name',
				label: __( 'Name', 'memorial-plugin' ),
				type: 'text',
			},
			{
				id: 'desc',
				label: __( 'Description', 'memorial-plugin' ),
				description: __(
					'Under the title of the catalog on the site (optional).',
					'memorial-plugin'
				),
				type: 'text',
				Edit: TextareaEdit,
			},
			{
				id: 'price',
				label: __( 'Price of a new product, ₽', 'memorial-plugin' ),
				description: __(
					'Empty — “price on request”.',
					'memorial-plugin'
				),
				type: 'integer',
				Edit: HintedEdit,
			},
			{
				id: 'from',
				label: __( 'New products show “from”', 'memorial-plugin' ),
				type: 'boolean',
				Edit: BooleanEdit,
			},
			{
				id: 'unit',
				label: __( 'Unit of a new product', 'memorial-plugin' ),
				placeholder: __( 'per piece', 'memorial-plugin' ),
				type: 'text',
				Edit: HintedEdit,
			},
			{
				id: 'showSub',
				label: __(
					'Products have a line under the name',
					'memorial-plugin'
				),
				description: __(
					'For example “height 90 cm”: a field in the products table and in the product window, a line under the name on the site. When it is off, the lines already typed are kept, only not shown.',
					'memorial-plugin'
				),
				type: 'boolean',
				Edit: BooleanEdit,
			},
			...( type.cats.length
				? [
						termsField(
							'cats',
							__(
								'Categories of a new product',
								'memorial-plugin'
							),
							type.cats.map( ( c ) => ( {
								value: c.id,
								label: '— '.repeat( c.depth ) + c.name,
							} ) )
						),
					]
				: [] ),
			{
				id: 'sort',
				label: __( 'Order of products on the site', 'memorial-plugin' ),
				description: __(
					'How the products of this type go when a visitor opens the section. A category can have its own order.',
					'memorial-plugin'
				),
				type: 'text',
				elements: sortElements( type.sorts, type.sort, false ),
				Edit: HintedEdit,
				look: true,
			},
			{
				id: 'ratio',
				label: __( 'Photos in the cards', 'memorial-plugin' ),
				type: 'text',
				elements: [
					{ value: '', label: __( 'Usual 4:3', 'memorial-plugin' ) },
					...ratios,
				],
				Edit: HintedEdit,
				look: true,
			},
			{
				id: 'cols',
				label: __( 'Columns on a computer', 'memorial-plugin' ),
				description: __( 'From 2 to 6; empty — 4.', 'memorial-plugin' ),
				type: 'integer',
				placeholder: '4',
				Edit: HintedEdit,
				look: true,
			},
			{
				id: 'colsPhone',
				label: __( 'Columns on a phone', 'memorial-plugin' ),
				description: __(
					'One column — large photos, one product under another; two — more products on the screen.',
					'memorial-plugin'
				),
				type: 'text',
				elements: [
					{ value: '1', label: '1' },
					{ value: '2', label: '2' },
				],
				Edit: HintedEdit,
				look: true,
			},
		],
		[ ratios, type ]
	);
	const change = ( e ) => {
		setData( ( d ) => ( { ...d, ...e } ) );
		setDirty( true );
	};
	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Type of product', 'memorial-plugin' ) }</h2>
			</CardHeader>
			<CardBody>
				<DataForm
					data={ data }
					fields={ fields }
					form={ {
						layout: { type: 'regular' },
						fields: fields
							.filter( ( f ) => ! f.look )
							.map( ( f ) => f.id ),
					} }
					onChange={ change }
				/>
				{ /* How the products look on the site: one row of four short fields. */ }
				<h3 className="memorial-plugin-catalog__subhead">
					{ __( 'Products on the site', 'memorial-plugin' ) }
				</h3>
				<div className="memorial-plugin-catalog__look">
					<DataForm
						data={ data }
						fields={ fields }
						form={ {
							layout: { type: 'regular' },
							fields: fields
								.filter( ( f ) => f.look )
								.map( ( f ) => f.id ),
						} }
						onChange={ change }
					/>
				</div>
				<Flex
					justify="flex-end"
					className="memorial-plugin-catalog__buttons"
				>
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ ! dirty || busy }
						onClick={ async () => {
							setBusy( true );
							await save( {
								name: data.name,
								desc: data.desc,
								ratio: data.ratio,
								cols: data.cols,
								colsPhone: Number( data.colsPhone ) || 2,
								showSub: data.showSub,
								sort: data.sort,
								defaults: {
									price: data.price,
									from: data.from,
									unit: data.unit,
									cats: data.cats,
								},
							} );
							setBusy( false );
						} }
					>
						{ __( 'Save', 'memorial-plugin' ) }
					</Button>
				</Flex>
			</CardBody>
		</Card>
	);
}

const SPEC_TYPES = () => [
	{ value: 'text', label: __( 'Text', 'memorial-plugin' ) },
	{ value: 'number', label: __( 'Number', 'memorial-plugin' ) },
	{ value: 'list', label: __( 'Choice from a list', 'memorial-plugin' ) },
];

function Specs( { type, save } ) {
	const init = () => type.fields.map( ( f ) => ( { ...f, orig: f.label } ) );
	const [ rows, setRows ] = useState( init );
	const [ dirty, setDirty ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	useEffect( () => {
		setRows( type.fields.map( ( f ) => ( { ...f, orig: f.label } ) ) );
		setDirty( false );
	}, [ type ] );
	const set = ( list ) => {
		setRows( list );
		setDirty( true );
	};
	const update = ( i, patch ) =>
		set( rows.map( ( r, j ) => ( j === i ? { ...r, ...patch } : r ) ) );
	const move = ( i, d ) => {
		const list = [ ...rows ];
		[ list[ i ], list[ i + d ] ] = [ list[ i + d ], list[ i ] ];
		set( list );
	};
	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Specifications', 'memorial-plugin' ) }</h2>
			</CardHeader>
			<CardBody>
				<p className="memorial-plugin-catalog__help">
					{ __(
						'Every specification is a column in “Products” and a line in the product card on the site. The default value goes into new products of the type. Renaming a specification renames it in all products.',
						'memorial-plugin'
					) }
				</p>
				{ rows.map( ( r, i ) => (
					<fieldset
						key={ i }
						className="memorial-plugin-catalog__spec"
					>
						<legend className="screen-reader-text">
							{ r.label ||
								sprintf(
									/* translators: %d: number. */
									__( 'Specification %d', 'memorial-plugin' ),
									i + 1
								) }
						</legend>
						<Flex align="flex-end" wrap>
							<FlexBlock>
								<TextControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Name', 'memorial-plugin' ) }
									placeholder={ __(
										'For example: Height',
										'memorial-plugin'
									) }
									value={ r.label }
									onChange={ ( v ) =>
										update( i, { label: v } )
									}
								/>
							</FlexBlock>
							<FlexItem>
								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Kind', 'memorial-plugin' ) }
									value={ r.type }
									options={ SPEC_TYPES() }
									onChange={ ( v ) =>
										update( i, { type: v } )
									}
								/>
							</FlexItem>
							{ r.type === 'number' && (
								<FlexItem>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Unit',
											'memorial-plugin'
										) }
										placeholder={ __(
											'cm, mm, kg',
											'memorial-plugin'
										) }
										value={ r.unit }
										onChange={ ( v ) =>
											update( i, { unit: v } )
										}
									/>
								</FlexItem>
							) }
							{ r.type === 'list' && (
								<FlexBlock>
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Choices, separated by commas',
											'memorial-plugin'
										) }
										value={ ( r.options || [] ).join(
											', '
										) }
										onChange={ ( v ) =>
											update( i, {
												options: v
													.split( ',' )
													.map( ( s ) => s.trim() ),
											} )
										}
									/>
								</FlexBlock>
							) }
							<FlexItem>
								{ r.type === 'list' ? (
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __(
											'Default',
											'memorial-plugin'
										) }
										value={ r.default }
										options={ [
											{
												value: '',
												label: __(
													'— none —',
													'memorial-plugin'
												),
											},
											...( r.options || [] )
												.filter( Boolean )
												.map( ( o ) => ( {
													value: o,
													label: o,
												} ) ),
										] }
										onChange={ ( v ) =>
											update( i, { default: v } )
										}
									/>
								) : (
									<TextControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										type={
											r.type === 'number'
												? 'number'
												: 'text'
										}
										label={ __(
											'Default',
											'memorial-plugin'
										) }
										value={ r.default }
										onChange={ ( v ) =>
											update( i, { default: v } )
										}
									/>
								) }
							</FlexItem>
							<FlexItem>
								<Flex gap={ 0 }>
									<Button
										icon="arrow-up-alt2"
										label={ __(
											'Move up',
											'memorial-plugin'
										) }
										disabled={ i === 0 }
										onClick={ () => move( i, -1 ) }
									/>
									<Button
										icon="arrow-down-alt2"
										label={ __(
											'Move down',
											'memorial-plugin'
										) }
										disabled={ i === rows.length - 1 }
										onClick={ () => move( i, 1 ) }
									/>
									<Button
										icon="no-alt"
										isDestructive
										label={ __(
											'Remove specification',
											'memorial-plugin'
										) }
										onClick={ () =>
											set(
												rows.filter(
													( x, j ) => j !== i
												)
											)
										}
									/>
								</Flex>
							</FlexItem>
						</Flex>
					</fieldset>
				) ) }
				<Flex
					justify="space-between"
					className="memorial-plugin-catalog__buttons"
				>
					<Button
						variant="secondary"
						onClick={ () =>
							set( [
								...rows,
								{
									label: '',
									type: 'text',
									unit: '',
									options: [],
									default: '',
								},
							] )
						}
					>
						{ __( 'Add specification', 'memorial-plugin' ) }
					</Button>
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ ! dirty || busy }
						onClick={ async () => {
							setBusy( true );
							const clean = rows.filter( ( r ) =>
								r.label.trim()
							);
							const renames = {};
							clean.forEach( ( r ) => {
								if ( r.orig && r.orig !== r.label.trim() ) {
									renames[ r.orig ] = r.label.trim();
								}
							} );
							await save( {
								fields: clean.map( ( { orig, ...f } ) => ( {
									...f,
									label: f.label.trim(),
									options: ( f.options || [] ).filter(
										Boolean
									),
								} ) ),
								renames,
							} );
							setBusy( false );
						} }
					>
						{ __( 'Save specifications', 'memorial-plugin' ) }
					</Button>
				</Flex>
			</CardBody>
		</Card>
	);
}

function CatForm( { cat, parents, sorts, onCancel, onOk } ) {
	const [ data, setData ] = useState( {
		name: cat?.name || '',
		desc: cat?.desc || '',
		parent: parents[ 0 ]?.value,
		// A new category: newest products first.
		sort: cat ? cat.sort || '' : 'new',
	} );
	const [ busy, setBusy ] = useState( false );
	const fields = [
		{ id: 'name', label: __( 'Name', 'memorial-plugin' ), type: 'text' },
		...( cat
			? []
			: [
					{
						id: 'parent',
						label: __( 'Inside', 'memorial-plugin' ),
						type: 'integer',
						elements: parents,
					},
				] ),
		{
			id: 'desc',
			label: __( 'Description', 'memorial-plugin' ),
			description: __(
				'Under the section title on the site; the catalog itself adds “47 models, from 800 ₽”. A price in the text — {price:code} (the codes are in Memorial → Prices → Separate prices). A price in the text — {price:code} (the codes are in Memorial → Prices → Separate prices).',
				'memorial-plugin'
			),
			type: 'text',
			Edit: TextareaEdit,
		},
		{
			id: 'sort',
			label: __( 'Order of products on the site', 'memorial-plugin' ),
			type: 'text',
			elements: sortElements( sorts, data.sort, true ),
		},
	];
	return (
		<>
			<DataForm
				data={ data }
				fields={ fields }
				form={ {
					layout: { type: 'regular' },
					fields: fields.map( ( f ) => f.id ),
				} }
				onChange={ ( e ) => setData( ( d ) => ( { ...d, ...e } ) ) }
			/>
			<ModalButtons
				label={
					cat
						? __( 'Save', 'memorial-plugin' )
						: __( 'Add', 'memorial-plugin' )
				}
				busy={ busy }
				disabled={ ! data.name.trim() }
				onCancel={ onCancel }
				onOk={ async () => {
					setBusy( true );
					await onOk( data );
				} }
			/>
		</>
	);
}

function Cats( { type, call } ) {
	const [ adding, setAdding ] = useState( false );
	const [ view, setView ] = useState( {
		type: 'table',
		titleField: 'name',
		fields: [ 'count', 'desc' ],
		sort: { field: 'row', direction: 'asc' },
		perPage: 100,
		page: 1,
		filters: [],
		search: '',
	} );
	const items = useMemo(
		() => type.cats.map( ( c, i ) => ( { ...c, row: i } ) ),
		[ type ]
	);
	const fields = useMemo(
		() => [
			{
				id: 'name',
				label: __( 'Category', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableHiding: false,
				render: ( { item } ) => '— '.repeat( item.depth ) + item.name,
			},
			{
				id: 'count',
				label: __( 'Products', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
				render: ( { item } ) => (
					<a href={ type.products }>{ item.count }</a>
				),
			},
			{
				id: 'desc',
				label: __( 'Description', 'memorial-plugin' ),
				type: 'text',
				filterBy: false,
				enableSorting: false,
				render: ( { item } ) =>
					item.desc.length > 100
						? item.desc.slice( 0, 100 ) + '…'
						: item.desc,
			},
			{
				id: 'row',
				label: __( 'Order', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
			},
		],
		[ type ]
	);
	const parents = useMemo(
		() => [
			{ value: type.id, label: type.name },
			...type.cats.map( ( c ) => ( {
				value: c.id,
				label: '— '.repeat( c.depth + 1 ) + c.name,
			} ) ),
		],
		[ type ]
	);
	const actions = useMemo( () => {
		const move =
			( dir ) =>
			( [ item ] ) => {
				const siblings = type.cats.filter(
					( c ) => c.parent === item.parent
				);
				const ids = moved( siblings, item.id, dir );
				if ( ids ) {
					call( 'order', 'POST', { ids } );
				}
			};
		return [
			{
				id: 'edit',
				label: __( 'Edit', 'memorial-plugin' ),
				isPrimary: true,
				icon: 'edit',
				RenderModal: ( { items: [ item ], closeModal } ) => (
					<CatForm
						cat={ item }
						parents={ parents }
						sorts={ type.sorts }
						onCancel={ closeModal }
						onOk={ async ( d ) => {
							await call( 'cats/' + item.id, 'PUT', {
								name: d.name,
								desc: d.desc,
								sort: d.sort,
							} );
							closeModal();
						} }
					/>
				),
			},
			{
				id: 'up',
				label: __( 'Move up', 'memorial-plugin' ),
				callback: move( -1 ),
			},
			{
				id: 'down',
				label: __( 'Move down', 'memorial-plugin' ),
				callback: move( 1 ),
			},
			{
				id: 'delete',
				label: __( 'Delete', 'memorial-plugin' ),
				isDestructive: true,
				RenderModal: ( { items: [ item ], closeModal } ) => (
					<Flex direction="column" gap={ 4 }>
						<p>
							{ item.count
								? sprintf(
										/* translators: 1: category, 2: number of products, 3: type. */
										_n(
											'Delete “%1$s”? Its %2$d product stays in “%3$s”.',
											'Delete “%1$s”? Its %2$d products stay in “%3$s”.',
											item.count,
											'memorial-plugin'
										),
										item.name,
										item.count,
										type.name
									)
								: sprintf(
										/* translators: %s: category. */
										__( 'Delete “%s”?', 'memorial-plugin' ),
										item.name
									) }
						</p>
						<ModalButtons
							label={ __( 'Delete', 'memorial-plugin' ) }
							isDestructive
							onCancel={ closeModal }
							onOk={ async () => {
								await call( 'cats/' + item.id, 'DELETE' );
								closeModal();
							} }
						/>
					</Flex>
				),
			},
		];
	}, [ type, parents, call ] );
	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( items, view, fields ),
		[ items, view, fields ]
	);
	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'Categories', 'memorial-plugin' ) }</h2>
				<Button variant="secondary" onClick={ () => setAdding( true ) }>
					{ __( 'Add category', 'memorial-plugin' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<p className="memorial-plugin-catalog__help">
					{ __(
						'Sections inside the type — on the site these are the buttons above the catalog.',
						'memorial-plugin'
					) }
				</p>
				<DataViews
					data={ data }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					actions={ actions }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {} } }
					getItemId={ ( item ) => String( item.id ) }
				/>
				{ adding && (
					<Modal
						title={ __( 'New category', 'memorial-plugin' ) }
						onRequestClose={ () => setAdding( false ) }
					>
						<CatForm
							parents={ parents }
							sorts={ type.sorts }
							onCancel={ () => setAdding( false ) }
							onOk={ async ( d ) => {
								await call( 'cats', 'POST', {
									name: d.name,
									parent: d.parent,
									desc: d.desc,
									sort: d.sort,
								} );
								setAdding( false );
							} }
						/>
					</Modal>
				) }
			</CardBody>
		</Card>
	);
}

function NewType( { onCancel, onOk } ) {
	const [ name, setName ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	return (
		<>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Name', 'memorial-plugin' ) }
				help={ __(
					'A draft page for the new catalog section is created too.',
					'memorial-plugin'
				) }
				value={ name }
				onChange={ setName }
			/>
			<ModalButtons
				label={ __( 'Add', 'memorial-plugin' ) }
				busy={ busy }
				disabled={ ! name.trim() }
				onCancel={ onCancel }
				onOk={ async () => {
					setBusy( true );
					await onOk( name.trim() );
				} }
			/>
		</>
	);
}

export default function Types() {
	const [ data, setData ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ typeId, setTypeId ] = useState(
		() =>
			Number(
				new URLSearchParams( window.location.search ).get( 'type' )
			) || 0
	);
	const [ adding, setAdding ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );

	const call = useCallback( async ( path, method = 'GET', body ) => {
		try {
			const res = await apiFetch( {
				path: API + path,
				method,
				data: body,
			} );
			setData( res );
			if ( method !== 'GET' ) {
				setNotice( {
					status: 'success',
					text: __( 'Saved.', 'memorial-plugin' ),
				} );
			}
			return res;
		} catch ( e ) {
			setNotice( { status: 'error', text: e.message } );
		}
	}, [] );
	useEffect( () => {
		call( 'types' );
	}, [ call ] );

	if ( ! data ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : null;
	}
	const type = data.types.find( ( t ) => t.id === typeId ) || data.types[ 0 ];
	const select = ( id ) => {
		setTypeId( id );
		const url = new URL( window.location.href );
		url.searchParams.set( 'type', id );
		window.history.replaceState( null, '', url );
	};
	const moveType = ( dir ) => {
		const ids = moved( data.types, type.id, dir );
		if ( ids ) {
			call( 'order', 'POST', { ids } );
		}
	};
	const index = type ? data.types.indexOf( type ) : -1;

	return (
		<Page
			title={ __( 'Types and categories', 'memorial-plugin' ) }
			subTitle={ __(
				'A type is a top section of the catalog (Coffins, Wreaths…) with its own specifications and categories.',
				'memorial-plugin'
			) }
			actions={
				<Flex gap={ 2 }>
					<Button
						variant="primary"
						onClick={ () => setAdding( true ) }
					>
						{ __( 'New type', 'memorial-plugin' ) }
					</Button>
				</Flex>
			}
		>
			<Flex
				direction="column"
				gap={ 4 }
				className="memorial-plugin-catalog"
			>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.text }
					</Notice>
				) }
				{ ! type && (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'There are no product types yet. Add one, for example “Wreaths”.',
							'memorial-plugin'
						) }
					</Notice>
				) }
				{ type && (
					<>
						<TabPanel
							key={ data.types.map( ( t ) => t.id ).join() }
							className="memorial-plugin-catalog__tabs"
							tabs={ data.types.map( ( t ) => ( {
								name: String( t.id ),
								title: `${ t.name } (${ t.count })`,
							} ) ) }
							initialTabName={ String( type.id ) }
							onSelect={ ( name ) => select( Number( name ) ) }
						>
							{ () => null }
						</TabPanel>
						<Flex gap={ 2 } wrap justify="flex-start">
							<Button variant="primary" href={ type.products }>
								{ sprintf(
									/* translators: 1: type, 2: number of products. */
									__(
										'Products “%1$s” (%2$d)',
										'memorial-plugin'
									),
									type.name,
									type.count
								) }
							</Button>
							{ type.page ? (
								<Button
									variant="secondary"
									href={
										type.page.draft
											? type.page.edit
											: type.page.url
									}
								>
									{ type.page.draft
										? __(
												'Finish the page (draft)',
												'memorial-plugin'
											)
										: __(
												'Page on the site',
												'memorial-plugin'
											) }
								</Button>
							) : (
								<Button
									variant="secondary"
									onClick={ () =>
										call(
											'types/' + type.id + '/page',
											'POST'
										)
									}
								>
									{ __(
										'Create a page for this type',
										'memorial-plugin'
									) }
								</Button>
							) }
							<Button
								variant="tertiary"
								disabled={ index === 0 }
								onClick={ () => moveType( -1 ) }
							>
								{ __( 'Move type earlier', 'memorial-plugin' ) }
							</Button>
							<Button
								variant="tertiary"
								disabled={ index === data.types.length - 1 }
								onClick={ () => moveType( 1 ) }
							>
								{ __( 'Move type later', 'memorial-plugin' ) }
							</Button>
							<Button
								variant="tertiary"
								isDestructive
								disabled={ type.count > 0 }
								onClick={ () => setDeleting( true ) }
							>
								{ __( 'Delete type', 'memorial-plugin' ) }
							</Button>
						</Flex>
						<TypeSettings
							type={ type }
							ratios={ data.ratios }
							save={ ( values ) =>
								call( 'types/' + type.id, 'PUT', { values } )
							}
						/>
						<Cats type={ type } call={ call } />
						<Specs
							type={ type }
							save={ ( values ) =>
								call( 'types/' + type.id, 'PUT', { values } )
							}
						/>
					</>
				) }
			</Flex>
			{ adding && (
				<Modal
					title={ __( 'New type of product', 'memorial-plugin' ) }
					onRequestClose={ () => setAdding( false ) }
				>
					<NewType
						onCancel={ () => setAdding( false ) }
						onOk={ async ( name ) => {
							const res = await call( 'types', 'POST', { name } );
							if ( res?.created ) {
								select( res.created );
							}
							setAdding( false );
						} }
					/>
				</Modal>
			) }
			{ deleting && (
				<Modal
					title={ __( 'Delete type', 'memorial-plugin' ) }
					onRequestClose={ () => setDeleting( false ) }
				>
					<p>
						{ sprintf(
							/* translators: %s: type. */
							__(
								'Delete “%s” with its categories?',
								'memorial-plugin'
							),
							type.name
						) }
					</p>
					<ModalButtons
						label={ __( 'Delete', 'memorial-plugin' ) }
						isDestructive
						onCancel={ () => setDeleting( false ) }
						onOk={ async () => {
							await call( 'types/' + type.id, 'DELETE' );
							setDeleting( false );
						} }
					/>
				</Modal>
			) }
		</Page>
	);
}
