/**
 * Memorial → Pages from the repository (pages/repo-pages.php): what the import of the page files would do,
 * a dry check, applying, the decisions on conflicts and old pages, the journal.
 */
import { RawHTML, useCallback, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Modal, Spinner, TabPanel } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import { useListData, ListPage } from './list-screen';

const API = '/memorial-plugin/v1/repo-pages';

const STATUS = () => [
	{ value: 'create', label: __( 'Will be created', 'memorial-plugin' ) },
	{ value: 'update', label: __( 'Will be updated', 'memorial-plugin' ) },
	{ value: 'same', label: __( 'Up to date', 'memorial-plugin' ) },
	{
		value: 'conflict',
		label: __( 'Conflict: edited on the site', 'memorial-plugin' ),
	},
	{
		value: 'legacy',
		label: __( 'Old page: choose', 'memorial-plugin' ),
	},
	{ value: 'kept', label: __( 'Site text kept', 'memorial-plugin' ) },
	{ value: 'error', label: __( 'Error', 'memorial-plugin' ) },
];

const ACTIONS = () => [
	{ value: 'created', label: __( 'Created', 'memorial-plugin' ) },
	{ value: 'updated', label: __( 'Updated', 'memorial-plugin' ) },
	{ value: 'kept', label: __( 'Site text kept', 'memorial-plugin' ) },
	{ value: 'menu', label: __( 'Menu', 'memorial-plugin' ) },
	{ value: 'warning', label: __( 'Warning', 'memorial-plugin' ) },
	{ value: 'error', label: __( 'Error', 'memorial-plugin' ) },
];

const DECIDE = [ 'conflict', 'legacy', 'kept' ];

const labelOf = ( list, value ) =>
	list.find( ( s ) => s.value === value )?.label || value;

export default function RepoPages() {
	const { data, error, setData, reload } = useListData( API );
	const [ notice, setNotice ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ tab, setTab ] = useState( 'files' );
	const [ diff, setDiff ] = useState( null );
	const [ view, setView ] = useState( {
		type: 'table',
		titleField: 'title',
		fields: [ 'path', 'status', 'version', 'menu', 'message' ],
		sort: { field: 'path', direction: 'asc' },
		perPage: 50,
		page: 1,
		filters: [],
		search: '',
	} );
	const [ logView, setLogView ] = useState( {
		type: 'table',
		titleField: 'path',
		fields: [ 'time', 'action', 'message', 'how' ],
		sort: { field: 'time', direction: 'desc' },
		perPage: 50,
		page: 1,
		filters: [],
		search: '',
	} );

	const apply = useCallback(
		async ( choices = {} ) => {
			setBusy( true );
			try {
				const res = await apiFetch( {
					path: API,
					method: 'POST',
					data: { choices },
				} );
				setData( res );
				const errors = ( res.done || [] ).filter(
					( d ) => d.action === 'error'
				).length;
				setNotice( {
					status: errors ? 'warning' : 'success',
					text: errors
						? sprintf(
								/* translators: %d: number of errors. */
								__(
									'Done, with errors: %d. See the journal.',
									'memorial-plugin'
								),
								errors
							)
						: __(
								'Done. What was changed is in the journal.',
								'memorial-plugin'
							),
				} );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			}
			setBusy( false );
			setDiff( null );
		},
		[ setData ]
	);

	const openDiff = useCallback( async ( item ) => {
		setDiff( { item, loading: true } );
		try {
			const res = await apiFetch( {
				path: addQueryArgs( API + '/diff', { path: item.id } ),
			} );
			setDiff( { item, ...res } );
		} catch ( e ) {
			setDiff( null );
			setNotice( { status: 'error', text: e.message } );
		}
	}, [] );

	const fields = useMemo(
		() => [
			{
				id: 'title',
				label: __( 'Page', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableHiding: false,
				render: ( { item } ) =>
					item.view ? (
						<a href={ item.view } target="_blank" rel="noreferrer">
							{ item.title || item.path }
						</a>
					) : (
						item.title || item.path
					),
			},
			{
				id: 'path',
				label: __( 'Address', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				filterBy: false,
			},
			{
				id: 'status',
				label: __( 'What the import does', 'memorial-plugin' ),
				type: 'text',
				elements: STATUS(),
				filterBy: { operators: [ 'isAny' ], isPrimary: true },
				render: ( { item } ) => (
					<strong
						className={
							'memorial-plugin-repo__status is-' + item.status
						}
					>
						{ labelOf( STATUS(), item.status ) }
					</strong>
				),
			},
			{
				id: 'version',
				label: __( 'Version: file / site', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
				render: ( { item } ) =>
					item.version + ' / ' + ( item.site_version || '—' ),
			},
			{
				id: 'menu',
				label: __( 'Menu', 'memorial-plugin' ),
				type: 'text',
				filterBy: false,
				enableSorting: false,
				render: ( { item } ) => {
					if ( item.menu === 'has' ) {
						return __( 'in the menu', 'memorial-plugin' );
					}
					if ( item.menu === 'add' ) {
						return __( 'will be added', 'memorial-plugin' );
					}
					return item.menu || '—';
				},
			},
			{
				id: 'message',
				label: __( 'Details', 'memorial-plugin' ),
				type: 'text',
				filterBy: false,
				enableSorting: false,
			},
		],
		[]
	);

	const logFields = useMemo(
		() => [
			{
				id: 'path',
				label: __( 'Page', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableHiding: false,
				render: ( { item } ) =>
					'/' === item.path ? '/' : '/' + item.path + '/',
			},
			{
				id: 'time',
				label: __( 'When', 'memorial-plugin' ),
				type: 'datetime',
				filterBy: false,
				getValue: ( { item } ) => item.time * 1000,
				render: ( { item } ) =>
					new Date( item.time * 1000 ).toLocaleString(),
			},
			{
				id: 'action',
				label: __( 'What was done', 'memorial-plugin' ),
				type: 'text',
				elements: ACTIONS(),
				filterBy: { operators: [ 'isAny' ], isPrimary: true },
			},
			{
				id: 'message',
				label: __( 'Details', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				filterBy: false,
				enableSorting: false,
			},
			{
				id: 'how',
				label: __( 'How', 'memorial-plugin' ),
				type: 'text',
				filterBy: false,
				render: ( { item } ) =>
					( item.how === 'auto'
						? __( 'by itself after the update', 'memorial-plugin' )
						: __( 'on this screen', 'memorial-plugin' ) ) +
					( item.user ? ' · ' + item.user : '' ),
			},
		],
		[]
	);

	const actions = useMemo(
		() => [
			{
				id: 'diff',
				label: __( 'Show the difference', 'memorial-plugin' ),
				isPrimary: true,
				icon: 'visibility',
				isEligible: ( item ) =>
					item.page > 0 && item.status !== 'error',
				callback: ( [ item ] ) => openDiff( item ),
			},
			{
				id: 'repo',
				label: __( 'Take from the repository', 'memorial-plugin' ),
				isEligible: ( item ) => DECIDE.includes( item.status ),
				callback: ( [ item ] ) => apply( { [ item.id ]: 'repo' } ),
			},
			{
				id: 'site',
				label: __( 'Keep as on the site', 'memorial-plugin' ),
				isEligible: ( item ) =>
					DECIDE.includes( item.status ) && item.status !== 'kept',
				callback: ( [ item ] ) => apply( { [ item.id ]: 'site' } ),
			},
			{
				id: 'edit',
				label: __( 'Open in the editor', 'memorial-plugin' ),
				isEligible: ( item ) => !! item.edit,
				callback: ( [ item ] ) => {
					window.location.href = item.edit;
				},
			},
		],
		[ apply, openDiff ]
	);

	if ( error ) {
		return <p className="notice notice-error">{ error }</p>;
	}

	const pending = ( data?.items || [] ).filter( ( i ) =>
		[ 'create', 'update' ].includes( i.status )
	).length;

	return (
		<>
			<ListPage
				title={ __( 'Pages from the repository', 'memorial-plugin' ) }
				subTitle={ sprintf(
					/* translators: %s: folder of the page files. */
					__(
						'Pages written as files in %s come to the site with the update. New and untouched pages are written by themselves when an administrator opens the admin area after the update; pages edited on the site and pages made before the import wait for your decision here. Menu items are only added.',
						'memorial-plugin'
					),
					data?.dir || 'content/pages'
				) }
				actions={
					<Flex gap={ 2 }>
						<Button
							variant="secondary"
							disabled={ busy }
							onClick={ () => {
								setNotice( null );
								reload();
							} }
						>
							{ __( 'Check (dry run)', 'memorial-plugin' ) }
						</Button>
						<Button
							variant="primary"
							isBusy={ busy }
							disabled={ busy || ! pending }
							onClick={ () => apply() }
						>
							{ pending
								? sprintf(
										/* translators: %d: number of pages. */
										__( 'Apply (%d)', 'memorial-plugin' ),
										pending
									)
								: __( 'Apply', 'memorial-plugin' ) }
						</Button>
					</Flex>
				}
				tabs={
					<TabPanel
						className="memorial-plugin-repo__tabs"
						initialTabName={ tab }
						onSelect={ setTab }
						tabs={ [
							{
								name: 'files',
								title: __( 'Files', 'memorial-plugin' ),
							},
							{
								name: 'log',
								title: __( 'Journal', 'memorial-plugin' ),
							},
						] }
					>
						{ () => null }
					</TabPanel>
				}
				notice={ notice }
				onNotice={ () => setNotice( null ) }
				items={ tab === 'log' ? data?.log : data?.items }
				fields={ tab === 'log' ? logFields : fields }
				actionsOf={ tab === 'log' ? [] : actions }
				view={ tab === 'log' ? logView : view }
				setView={ tab === 'log' ? setLogView : setView }
				loading={ ! data }
			/>
			{ diff && (
				<Modal
					title={ sprintf(
						/* translators: %s: page title. */
						__( 'Difference: %s', 'memorial-plugin' ),
						diff.item.title || diff.item.path
					) }
					size="large"
					onRequestClose={ () => setDiff( null ) }
				>
					{ diff.loading ? (
						<Spinner />
					) : (
						<RawHTML className="memorial-plugin-repo__diff">
							{ diff.html }
						</RawHTML>
					) }
					{ DECIDE.includes( diff.item.status ) && (
						<Flex
							justify="flex-end"
							className="memorial-plugin-catalog__buttons"
						>
							<Button
								variant="tertiary"
								disabled={ busy }
								onClick={ () =>
									apply( { [ diff.item.id ]: 'site' } )
								}
							>
								{ __(
									'Keep as on the site',
									'memorial-plugin'
								) }
							</Button>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={ busy }
								onClick={ () =>
									apply( { [ diff.item.id ]: 'repo' } )
								}
							>
								{ __(
									'Take from the repository',
									'memorial-plugin'
								) }
							</Button>
						</Flex>
					) }
				</Modal>
			) }
		</>
	);
}
