/**
 * Catalog → Moving from pages (catalog/catalog-import.php).
 *
 * The pages whose catalog is made of “Cards with captions” galleries or examples of works with price
 * tables. “Move…” opens a form (DataForm): the catalog category, the galleries to turn into products,
 * the works and whether to replace the galleries on the page with the catalog block. The move itself is
 * the ability memorial-plugin/import-from-pages; moving again does not create duplicates.
 */
import {
	createRoot,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Flex,
	Notice,
	BaseControl,
} from '@wordpress/components';
import {
	DataForm,
	DataViews,
	filterSortAndPaginate,
} from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';
import BooleanEdit from '../shared/boolean-edit';
import { runAbility } from '../shared/abilities';
import './style.scss';

const API = '/memorial-plugin/v1/import';

/**
 * Text of one gallery: heading, number of cards and a sample card.
 *
 * @param {Object} g Gallery from the REST list.
 * @return {string} Label.
 */
function galleryLabel( g ) {
	return (
		( g.heading || __( 'Untitled', 'memorial-plugin' ) ) +
		' — ' +
		sprintf(
			/* translators: %d: number of items */
			__( '%d pcs.', 'memorial-plugin' ),
			g.count
		) +
		( g.sample
			? ' (' + __( 'for example:', 'memorial-plugin' ) + ' ' + g.sample + ')'
			: '' )
	);
}

/**
 * The galleries of a page as checkboxes (DataForm field).
 *
 * @param {Object}   props          Field props.
 * @param {Object}   props.data     Form data.
 * @param {Object}   props.field    Field.
 * @param {Function} props.onChange Change handler.
 * @return {Element} Checkboxes.
 */
function GalleriesEdit( { data, field, onChange } ) {
	const chosen = data.gal || [];
	return (
		<BaseControl
			__nextHasNoMarginBottom
			label={ field.label }
			help={ field.description }
			id="memorial-plugin-import-galleries"
		>
			{ field.galleries.map( ( g ) => (
				<CheckboxControl
					key={ g.index }
					__nextHasNoMarginBottom
					label={ galleryLabel( g ) }
					checked={ chosen.includes( g.index ) }
					onChange={ ( on ) =>
						onChange( {
							gal: on
								? [ ...chosen, g.index ]
								: chosen.filter( ( i ) => i !== g.index ),
						} )
					}
				/>
			) ) }
		</BaseControl>
	);
}

/**
 * Form of one page in the “Move…” modal.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.item       Page from the REST list.
 * @param {Function} props.closeModal Close the modal.
 * @param {Function} props.onMove     Run the move.
 * @return {Element} Form.
 */
function MoveForm( { item, closeModal, onMove } ) {
	const [ data, setData ] = useState( {
		cat: item.title,
		gal: item.galleries.filter( ( g ) => g.checked ).map( ( g ) => g.index ),
		works: item.works > 0,
		replace: true,
	} );
	const fields = useMemo(
		() =>
			[
				item.galleries.length && {
					id: 'cat',
					label: __( 'Catalog category:', 'memorial-plugin' ),
					type: 'text',
				},
				item.galleries.length && {
					id: 'gal',
					label: __(
						'Galleries that become products',
						'memorial-plugin'
					),
					galleries: item.galleries,
					Edit: GalleriesEdit,
				},
				item.works && {
					id: 'works',
					label: sprintf(
						/* translators: %d: number of works */
						_n(
							'Move %d example of works to “Our works”',
							'Move %d examples of works to “Our works”',
							item.works,
							'memorial-plugin'
						),
						item.works
					),
					type: 'boolean',
					Edit: BooleanEdit,
				},
				{
					id: 'replace',
					label: __(
						'Replace the galleries on the page with the catalog from the admin (the old version stays in the page’s “Revisions”)',
						'memorial-plugin'
					),
					type: 'boolean',
					Edit: BooleanEdit,
				},
			].filter( Boolean ),
		[ item ]
	);
	const nothing = ! data.gal.length && ! data.works;
	return (
		<Flex direction="column" gap={ 4 }>
			<DataForm
				data={ data }
				fields={ fields }
				form={ {
					layout: { type: 'regular' },
					fields: fields.map( ( f ) => f.id ),
				} }
				onChange={ ( edits ) =>
					setData( ( prev ) => ( { ...prev, ...edits } ) )
				}
			/>
			<Flex justify="flex-end">
				<Button variant="tertiary" onClick={ closeModal }>
					{ __( 'Cancel', 'memorial-plugin' ) }
				</Button>
				<Button
					variant="primary"
					disabled={ nothing }
					onClick={ () => {
						closeModal();
						onMove( { id: item.id, ...data } );
					} }
				>
					{ __( 'Move the ticked ones', 'memorial-plugin' ) }
				</Button>
			</Flex>
		</Flex>
	);
}

function App() {
	const [ items, setItems ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ view, setView ] = useState( {
		type: 'table',
		fields: [ 'galleries', 'works', 'moved' ],
		titleField: 'title',
		perPage: 20,
		page: 1,
		sort: { field: 'title', direction: 'asc' },
		filters: [],
	} );

	const load = useCallback(
		() =>
			apiFetch( { path: API } )
				.then( setItems )
				.catch( ( e ) => {
					setItems( [] );
					setNotice( { status: 'error', text: e.message } );
				} ),
		[]
	);
	useEffect( () => {
		load();
	}, [ load ] );

	const move = useCallback(
		async ( page ) => {
			setBusy( true );
			try {
				const res = await runAbility(
					'memorial-plugin/import-from-pages',
					{ pages: [ page ] }
				);
				setNotice( { status: 'success', text: res.message } );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			}
			await load();
			setBusy( false );
		},
		[ load ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Page', 'memorial-plugin' ),
				enableHiding: false,
				enableGlobalSearch: true,
				render: ( { item } ) => (
					<a href={ item.link } target="_blank" rel="noreferrer">
						{ item.title || __( '(no title)', 'memorial-plugin' ) }
					</a>
				),
			},
			{
				id: 'galleries',
				label: __( 'Galleries', 'memorial-plugin' ),
				getValue: ( { item } ) => item.galleries.length,
				render: ( { item } ) =>
					item.galleries.length
						? item.galleries
								.map(
									( g ) =>
										( g.heading ||
											__( 'Untitled', 'memorial-plugin' ) ) +
										' (' +
										g.count +
										')'
								)
								.join( ', ' )
						: '—',
				enableSorting: true,
			},
			{
				id: 'works',
				label: __( 'Examples of works', 'memorial-plugin' ),
				getValue: ( { item } ) => item.works,
				render: ( { item } ) => item.works || '—',
				enableSorting: true,
			},
			{
				id: 'moved',
				label: __( 'State', 'memorial-plugin' ),
				getValue: ( { item } ) => ( item.moved ? 'moved' : 'new' ),
				elements: [
					{
						value: 'new',
						label: __( 'Not moved yet', 'memorial-plugin' ),
					},
					{
						value: 'moved',
						label: __( 'already moved', 'memorial-plugin' ),
					},
				],
				filterBy: { operators: [ 'isAny' ] },
			},
		],
		[]
	);

	const actions = useMemo(
		() => [
			{
				id: 'move',
				label: __( 'Move…', 'memorial-plugin' ),
				isPrimary: true,
				modalHeader: __( 'Moving the catalog from pages', 'memorial-plugin' ),
				modalSize: 'medium',
				RenderModal: ( { items: [ item ], closeModal } ) => (
					<MoveForm
						item={ item }
						closeModal={ closeModal }
						onMove={ move }
					/>
				),
			},
		],
		[ move ]
	);

	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( items || [], view, fields ),
		[ items, view, fields ]
	);

	return (
		<Page
			title={ __( 'Moving the catalog from pages', 'memorial-plugin' ) }
			subTitle={ __(
				'Here are the pages where the catalog is made of “Cards with captions” galleries or examples of works with price tables. The ticked sections become products (the name, price, caption and photo are taken from the captions) or “Our works”. Moving again does not create duplicates.',
				'memorial-plugin'
			) }
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			<DataViews
				data={ data }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				actions={ actions }
				paginationInfo={ paginationInfo }
				isLoading={ items === null || busy }
				defaultLayouts={ { table: {}, list: {} } }
				getItemId={ ( item ) => String( item.id ) }
				empty={
					<p>
						{ __(
							'No suitable pages were found.',
							'memorial-plugin'
						) }
					</p>
				}
			/>
		</Page>
	);
}

const root = document.getElementById( 'memorial-plugin-import' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
