/**
 * Our works → Works.
 */
import { useCallback, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Notice } from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';
import BooleanEdit from '../shared/boolean-edit';
import {
	useListData,
	moved,
	bySiteOrder,
	EditModal,
	ModalButtons,
	ListPage,
	arrayFilters,
} from './list-screen';
import {
	photosField,
	termsField,
	statusField,
	orderField,
	RowsEdit,
	rub,
} from './fields';

const API = '/memorial-plugin/v1/catalog/works';
const kindsFilter = arrayFilters( [ 'kinds' ] );

function KindsModal( { items, closeModal, run, op, kinds } ) {
	const [ chosen, setChosen ] = useState( [] );
	const [ busy, setBusy ] = useState( false );
	const field = termsField(
		'kinds',
		__( 'Kinds of works', 'memorial-plugin' ),
		kinds
	);
	return (
		<Flex direction="column" gap={ 4 }>
			<field.Edit
				data={ { kinds: chosen } }
				field={ field }
				onChange={ ( e ) => setChosen( e.kinds ) }
			/>
			<ModalButtons
				label={
					op === 'kinds_add'
						? __( 'Add', 'memorial-plugin' )
						: __( 'Remove', 'memorial-plugin' )
				}
				busy={ busy }
				disabled={ ! chosen.length }
				onCancel={ closeModal }
				onOk={ async () => {
					setBusy( true );
					await run( items, { op, kinds: chosen } );
					closeModal();
				} }
			/>
		</Flex>
	);
}

export default function Works() {
	const { data, error, merge } = useListData( API );
	const [ notice, setNotice ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ view, setView ] = useState( {
		type: 'table',
		titleField: 'title',
		mediaField: 'photos',
		fields: [ 'year', 'size', 'total', 'kinds', 'example', 'status' ],
		sort: { field: 'order', direction: 'asc' },
		perPage: 50,
		page: 1,
		filters: [],
		search: '',
	} );

	const kinds = useMemo(
		() =>
			( data?.kinds || [] ).map( ( k ) => ( {
				value: k.id,
				label: k.name,
			} ) ),
		[ data ]
	);

	const fields = useMemo(
		() => [
			photosField(
				__(
					'Photos — the first one is the main one',
					'memorial-plugin'
				)
			),
			{
				id: 'title',
				label: __( 'Title', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableHiding: false,
			},
			{
				id: 'year',
				label: __( 'Year', 'memorial-plugin' ),
				type: 'integer',
				description: __(
					'Tells the visitor that the prices are as of that year. Empty — the year is not shown.',
					'memorial-plugin'
				),
				render: ( { item } ) =>
					item.year || (
						<span className="memorial-plugin-catalog__hidden">
							{ __( 'not set', 'memorial-plugin' ) }
						</span>
					),
				filterBy: false,
			},
			{
				id: 'size',
				label: __( 'Plot size', 'memorial-plugin' ),
				type: 'text',
				placeholder: __( 'for example: 2.5 × 2 m', 'memorial-plugin' ),
				filterBy: false,
			},
			{
				id: 'rows',
				label: __( 'What was done and the price', 'memorial-plugin' ),
				enableSorting: false,
				enableHiding: false,
				filterBy: false,
				getValue: ( { item } ) =>
					( item.rows || [] ).map( ( r ) => r[ 0 ] ).join( '; ' ),
				render: ( { item } ) =>
					( item.rows || [] ).map( ( r ) => r[ 0 ] ).join( '; ' ),
				Edit: RowsEdit,
			},
			{
				id: 'total',
				label: __( 'Total', 'memorial-plugin' ),
				type: 'integer',
				readOnly: true,
				filterBy: false,
				render: ( { item } ) =>
					item.total ? rub( item.total ) : '—',
			},
			termsField(
				'kinds',
				__( 'Kinds of works', 'memorial-plugin' ),
				kinds
			),
			{
				id: 'example',
				label: __( 'In examples', 'memorial-plugin' ),
				description: __(
					'Shown in the “Examples of our works” cards on other pages.',
					'memorial-plugin'
				),
				type: 'boolean',
				elements: [
					{ value: true, label: __( 'Yes', 'memorial-plugin' ) },
					{ value: false, label: __( 'No', 'memorial-plugin' ) },
				],
				Edit: BooleanEdit,
				filterBy: { operators: [ 'is' ] },
				render: ( { item } ) => ( item.example ? '✓' : '' ),
			},
			statusField(),
			orderField(),
		],
		[ kinds ]
	);
	const formFields = useMemo(
		() => fields.filter( ( f ) => f.id !== 'total' ),
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
				merge( res );
				setNotice( {
					status: 'success',
					text: __( 'Done.', 'memorial-plugin' ),
				} );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			} finally {
				setBusy( false );
			}
		},
		[ merge ]
	);

	const create = useCallback(
		async ( photos = [] ) => {
			setBusy( true );
			try {
				const res = await apiFetch( {
					path: API,
					method: 'POST',
					data: { photos },
				} );
				merge( res );
				setEditing( res.items[ 0 ] );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			} finally {
				setBusy( false );
			}
		},
		[ merge ]
	);

	const save = useCallback(
		async ( item, edits ) => {
			const values = { ...edits };
			if ( values.photos ) {
				values.photos = values.photos.map( ( p ) => p.id );
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
		[ merge ]
	);

	const actions = useMemo( () => {
		const sorted = bySiteOrder( data?.items || [] );
		const move =
			( dir ) =>
			( [ item ] ) => {
				const ids = moved( sorted, item.id, dir );
				if ( ids ) {
					run(
						ids.map( ( id ) => ( { id } ) ),
						{ op: 'order' }
					);
				}
			};
		return [
			{
				id: 'edit',
				label: __( 'Edit', 'memorial-plugin' ),
				isPrimary: true,
				icon: 'edit',
				callback: ( [ item ] ) => setEditing( item ),
			},
			{
				id: 'editor',
				label: __( 'Edit text in the editor', 'memorial-plugin' ),
				callback: ( [ item ] ) => ( window.location.href = item.edit ),
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
				id: 'kinds_add',
				label: __( 'Add a kind of work…', 'memorial-plugin' ),
				supportsBulk: true,
				RenderModal: ( props ) => (
					<KindsModal
						{ ...props }
						run={ run }
						op="kinds_add"
						kinds={ kinds }
					/>
				),
			},
			{
				id: 'kinds_remove',
				label: __( 'Remove a kind of work…', 'memorial-plugin' ),
				supportsBulk: true,
				RenderModal: ( props ) => (
					<KindsModal
						{ ...props }
						run={ run }
						op="kinds_remove"
						kinds={ kinds }
					/>
				),
			},
			{
				id: 'publish',
				label: __( 'Show on the site', 'memorial-plugin' ),
				supportsBulk: true,
				isEligible: ( item ) => item.status !== 'publish',
				callback: ( list ) => run( list, { op: 'publish' } ),
			},
			{
				id: 'draft',
				label: __( 'Hide from the site', 'memorial-plugin' ),
				supportsBulk: true,
				isEligible: ( item ) => item.status === 'publish',
				callback: ( list ) => run( list, { op: 'draft' } ),
			},
			{
				id: 'copy',
				label: __( 'Copy', 'memorial-plugin' ),
				supportsBulk: true,
				callback: ( list ) => run( list, { op: 'copy' } ),
			},
			{
				id: 'trash',
				label: __( 'Move to trash', 'memorial-plugin' ),
				isDestructive: true,
				supportsBulk: true,
				callback: ( list ) => run( list, { op: 'trash' } ),
			},
		];
	}, [ data, kinds, run ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	return (
		<>
			<ListPage
				title={ __( 'Our works', 'memorial-plugin' ) }
				subTitle={ __(
					'Photos, year, size and prices are changed right here; the description of a work — in the editor.',
					'memorial-plugin'
				) }
				actions={
					data && (
						<Flex gap={ 2 } wrap>
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
											'New work from photos',
											'memorial-plugin'
										) }
									</Button>
								) }
							/>
							{ data.canManage && (
								<Button
									variant="tertiary"
									href={ data.kindsUrl }
								>
									{ __(
										'Kinds of works',
										'memorial-plugin'
									) }
								</Button>
							) }
							<Button variant="tertiary" href={ data.trashUrl }>
								{ __( 'Trash', 'memorial-plugin' ) }
							</Button>
						</Flex>
					)
				}
				notice={ notice }
				onNotice={ () => setNotice( null ) }
				items={ data?.items }
				fields={ fields }
				actionsOf={ actions }
				view={ view }
				setView={ setView }
				prefilter={ kindsFilter }
				loading={ ! data }
			/>
			{ editing && (
				<EditModal
					title={ editing.title }
					item={ editing }
					fields={ formFields }
					onSave={ ( edits ) => save( editing, edits ) }
					onClose={ () => setEditing( null ) }
					extra={
						<p>
							<a href={ editing.edit }>
								{ sprintf(
									/* translators: %s: work title. */
									__(
										'Description of “%s” — in the editor',
										'memorial-plugin'
									),
									editing.title
								) }
							</a>
						</p>
					}
				/>
			) }
		</>
	);
}
