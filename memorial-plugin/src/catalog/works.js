/**
 * Our works → Works.
 */
import { useCallback, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Notice } from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';
import BooleanEdit from '../shared/boolean-edit';
import { runAbility } from '../shared/abilities';
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
	DescEdit,
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
	const { data, error, merge, reload } = useListData( API );
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
			orderField(),
		],
		[ kinds ]
	);
	// The window: no total (it is counted) and no place in the list (“Move up / down” change it).
	const formFields = useMemo(
		() => fields.filter( ( f ) => ! [ 'total', 'order' ].includes( f.id ) ),
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

	// “New work from photos”: the window of a work that does not exist yet; it is created only by “Save”.
	const blank = useCallback(
		( media ) => ( {
			id: 0,
			title: '',
			status: 'publish',
			year: new Date().getFullYear(),
			size: '',
			rows: [],
			total: 0,
			photos: media.map( ( m ) => ( {
				id: m.id,
				url: m.sizes?.thumbnail?.url || m.url,
				big: m.sizes?.large?.url || m.url,
			} ) ),
			kinds: [],
			example: false,
			order: 0,
			desc: '',
			rich: false,
		} ),
		[]
	);

	const create = useCallback(
		async ( values ) => {
			setBusy( true );
			try {
				const res = await apiFetch( {
					path: API,
					method: 'POST',
					data: { values },
				} );
				merge( res );
				setNotice( {
					status: 'success',
					text: __( 'Saved.', 'memorial-plugin' ),
				} );
			} finally {
				setBusy( false );
			}
		},
		[ merge ]
	);

	const save = useCallback(
		async ( item, edits ) => {
			// A new work gets the photos and the year of the window even if they were not changed.
			const values = item.id
				? { ...edits }
				: {
						photos: item.photos,
						year: item.year,
						status: item.status,
						...edits,
					};
			if ( values.photos ) {
				values.photos = values.photos.map( ( p ) => p.id );
			}
			if ( ! item.id ) {
				await create( values ); // An error stays in the window, which stays open.
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
		[ merge, create ]
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
				callback: async ( list ) => {
					// Ability memorial-plugin/set-visibility (admin/abilities.php).
					try {
						await runAbility( 'memorial-plugin/set-visibility', {
							kind: 'works',
							ids: list.map( ( i ) => i.id ),
							visible: true,
						} );
						await reload();
					} catch ( e ) {
						setNotice( { status: 'error', text: e.message } );
					}
				},
			},
			{
				id: 'draft',
				label: __( 'Hide from the site', 'memorial-plugin' ),
				supportsBulk: true,
				isEligible: ( item ) => item.status === 'publish',
				callback: async ( list ) => {
					// Ability memorial-plugin/set-visibility (admin/abilities.php).
					try {
						await runAbility( 'memorial-plugin/set-visibility', {
							kind: 'works',
							ids: list.map( ( i ) => i.id ),
							visible: false,
						} );
						await reload();
					} catch ( e ) {
						setNotice( { status: 'error', text: e.message } );
					}
				},
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
	}, [ data, kinds, run, reload ] );

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
					'Photos, year, size, prices and the description of a work are changed right here.',
					'memorial-plugin'
				) }
				actions={
					data && (
						<Flex gap={ 2 } wrap>
							<MediaUpload
								allowedTypes={ [ 'image' ] }
								multiple
								onSelect={ ( media ) =>
									setEditing(
										blank(
											Array.isArray( media )
												? media
												: [ media ]
										)
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
					title={
						editing.id
							? editing.title
							: __( 'New work', 'memorial-plugin' )
					}
					item={ editing }
					fields={ formFields }
					onSave={ ( edits ) => save( editing, edits ) }
					onClose={ () => setEditing( null ) }
					isNew={ ! editing.id }
				/>
			) }
		</>
	);
}
