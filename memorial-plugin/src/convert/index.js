/**
 * Memorial → Convert to blocks: posts that still contain [mem_…] shortcodes.
 *
 * Preview shows the diff (server side, the same table as on the Revisions screen); Convert works in
 * batches (window.memorialPluginConvert.batch posts per request); Roll back restores the revision
 * saved before converting. Data and actions: REST memorial-plugin/v1/convert (admin/convert.php).
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
import { Button, Notice, Spinner } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';

const BATCH = window.memorialPluginConvert?.batch || 10;
const API = '/memorial-plugin/v1/convert';

const RESULT_TEXT = {
	converted: __( 'converted', 'memorial-plugin' ),
	unchanged: __( 'nothing to convert', 'memorial-plugin' ),
	'no-revision': __(
		'not converted: could not save a revision',
		'memorial-plugin'
	),
	forbidden: __( 'not converted: no permission', 'memorial-plugin' ),
	'not-found': __( 'not found', 'memorial-plugin' ),
};

function shortcodeList( shortcodes ) {
	return Object.entries( shortcodes || {} )
		.map( ( [ tag, n ] ) => ( n > 1 ? `${ tag } ×${ n }` : tag ) )
		.join( ', ' );
}

function stateOf( item ) {
	if ( item.pending ) {
		return 'pending';
	}
	return item.converted ? 'converted' : 'manual';
}

function Preview( { item, closeModal, onConvert } ) {
	const [ preview, setPreview ] = useState( null );
	const [ error, setError ] = useState( '' );
	useEffect( () => {
		apiFetch( { path: `${ API }/${ item.id }` } )
			.then( setPreview )
			.catch( ( e ) => setError( e.message ) );
	}, [ item.id ] );
	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! preview ) {
		return <Spinner />;
	}
	return (
		<div className="memorial-plugin-convert__preview">
			{ preview.changed ? (
				<div
					className="memorial-plugin-convert__diff revisions-diff"
					// The diff table is built by wp_text_diff() on the server; post content in it is escaped.
					dangerouslySetInnerHTML={ { __html: preview.diff } }
				/>
			) : (
				<p>
					{ __(
						'Nothing to convert automatically in this post.',
						'memorial-plugin'
					) }
				</p>
			) }
			{ Object.keys( preview.after ).length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %s: list of shortcodes. */
						__(
							'Will stay as shortcodes (inside other text): %s. Edit them in the post.',
							'memorial-plugin'
						),
						shortcodeList( preview.after )
					) }
				</Notice>
			) }
			<div className="memorial-plugin-convert__buttons">
				<Button variant="tertiary" onClick={ closeModal }>
					{ __( 'Cancel', 'memorial-plugin' ) }
				</Button>
				<Button
					variant="primary"
					disabled={ ! preview.changed }
					onClick={ () => {
						closeModal();
						onConvert( [ item ] );
					} }
				>
					{ __( 'Convert', 'memorial-plugin' ) }
				</Button>
			</div>
		</div>
	);
}

function App() {
	const [ items, setItems ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ view, setView ] = useState( {
		type: 'table',
		fields: [ 'type_label', 'shortcodes', 'state' ],
		titleField: 'title',
		perPage: 20,
		page: 1,
		sort: { field: 'title', direction: 'asc' },
		filters: [],
	} );

	const load = useCallback(
		() =>
			apiFetch( { path: API } )
				.then( ( data ) => setItems( data ) )
				.catch( ( e ) => {
					setItems( [] );
					setNotice( { status: 'error', text: e.message } );
				} ),
		[]
	);
	useEffect( () => {
		load();
	}, [ load ] );

	const convert = useCallback(
		async ( list ) => {
			setBusy( true );
			const ids = list.map( ( item ) => item.id );
			const done = {};
			try {
				for ( let i = 0; i < ids.length; i += BATCH ) {
					const part = await apiFetch( {
						path: API,
						method: 'POST',
						data: { ids: ids.slice( i, i + BATCH ) },
					} );
					Object.assign( done, part );
				}
				const ok = Object.values( done ).filter(
					( r ) => r === 'converted'
				).length;
				const failed = Object.entries( done ).filter(
					( [ , r ] ) => r !== 'converted' && r !== 'unchanged'
				);
				setNotice( {
					status: failed.length ? 'warning' : 'success',
					text:
						sprintf(
							/* translators: %d: number of posts. */
							_n(
								'%d post converted.',
								'%d posts converted.',
								ok,
								'memorial-plugin'
							),
							ok
						) +
						( failed.length
							? ' ' +
								failed
									.map(
										( [ id, r ] ) =>
											`#${ id }: ${ RESULT_TEXT[ r ] || r }`
									)
									.join( '; ' )
							: '' ),
				} );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			}
			await load();
			setBusy( false );
		},
		[ load ]
	);

	const rollback = useCallback(
		async ( item ) => {
			setBusy( true );
			try {
				const res = await apiFetch( {
					path: `${ API }/${ item.id }/rollback`,
					method: 'POST',
				} );
				setNotice( {
					status: res.result === 'restored' ? 'success' : 'warning',
					text:
						res.result === 'restored'
							? sprintf(
									/* translators: %s: post title. */
									__(
										'“%s” is back as it was before converting.',
										'memorial-plugin'
									),
									item.title
								)
							: __(
									'Could not roll back: the revision saved before converting is missing.',
									'memorial-plugin'
								),
				} );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			}
			await load();
			setBusy( false );
		},
		[ load ]
	);

	const fields = useMemo( () => {
		const types = [ ...new Set( ( items || [] ).map( ( i ) => i.type ) ) ];
		return [
			{
				id: 'title',
				label: __( 'Title', 'memorial-plugin' ),
				enableHiding: false,
				enableGlobalSearch: true,
				render: ( { item } ) => (
					<a href={ item.edit }>
						{ item.title || __( '(no title)', 'memorial-plugin' ) }
					</a>
				),
			},
			{
				id: 'type_label',
				label: __( 'Type', 'memorial-plugin' ),
				getValue: ( { item } ) => item.type,
				render: ( { item } ) => item.type_label,
				elements: types.map( ( type ) => ( {
					value: type,
					label: items.find( ( i ) => i.type === type ).type_label,
				} ) ),
				filterBy: { operators: [ 'isAny' ] },
			},
			{
				id: 'shortcodes',
				label: __( 'Shortcodes', 'memorial-plugin' ),
				getValue: ( { item } ) => item.total,
				render: ( { item } ) => shortcodeList( item.shortcodes ) || '—',
				enableSorting: true,
			},
			{
				id: 'state',
				label: __( 'State', 'memorial-plugin' ),
				getValue: ( { item } ) => stateOf( item ),
				elements: [
					{
						value: 'pending',
						label: __( 'Can be converted', 'memorial-plugin' ),
					},
					{
						value: 'converted',
						label: __( 'Converted', 'memorial-plugin' ),
					},
					{
						value: 'manual',
						label: __(
							'Only manual edits left',
							'memorial-plugin'
						),
					},
				],
				filterBy: { operators: [ 'isAny' ] },
			},
		];
	}, [ items ] );

	const actions = useMemo(
		() => [
			{
				id: 'preview',
				label: __( 'Preview and convert', 'memorial-plugin' ),
				isPrimary: true,
				isEligible: ( item ) => item.pending,
				modalHeader: __( 'Changes in the post', 'memorial-plugin' ),
				modalSize: 'large',
				RenderModal: ( { items: [ item ], closeModal } ) => (
					<Preview
						item={ item }
						closeModal={ closeModal }
						onConvert={ convert }
					/>
				),
			},
			{
				id: 'convert',
				label: __( 'Convert', 'memorial-plugin' ),
				supportsBulk: true,
				isEligible: ( item ) => item.pending,
				callback: ( list ) => convert( list ),
			},
			{
				id: 'rollback',
				label: __( 'Roll back', 'memorial-plugin' ),
				isEligible: ( item ) => item.converted > 0,
				callback: ( [ item ] ) => rollback( item ),
			},
		],
		[ convert, rollback ]
	);

	const { data, paginationInfo } = useMemo(
		() => filterSortAndPaginate( items || [], view, fields ),
		[ items, view, fields ]
	);

	return (
		<Page
			title={ __( 'Convert to blocks', 'memorial-plugin' ) }
			subTitle={ __(
				'Posts with [mem_…] shortcodes. Check the changes with “Preview”, then convert — one post or several at once. Before saving, the current text is kept as a revision, so “Roll back” returns it.',
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
							'No posts with shortcodes left.',
							'memorial-plugin'
						) }
					</p>
				}
			/>
		</Page>
	);
}

const root = document.getElementById( 'memorial-plugin-convert' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
