/**
 * Memorial → What people search for on the site: queries of the “Questions and answers” and “Good to know”
 * search (content/search-stats.php). Only the query text and counters are stored.
 */
import { useCallback, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Modal, Notice } from '@wordpress/components';
import { useListData, ModalButtons, ListPage } from './list-screen';

const API = '/memorial-plugin/v1/search-stats';
const WHERE = () => [
	{ value: 'f', label: __( 'Questions and answers', 'memorial-plugin' ) },
	{ value: 'g', label: __( 'Good to know', 'memorial-plugin' ) },
];

export default function SearchStats() {
	const { data, error, setData } = useListData( API );
	const [ notice, setNotice ] = useState( null );
	const [ clearing, setClearing ] = useState( false );
	const [ view, setView ] = useState( {
		type: 'table',
		titleField: 'query',
		fields: [ 'count', 'found', 'where', 'last' ],
		sort: { field: 'count', direction: 'desc' },
		perPage: 50,
		page: 1,
		// First what found nothing: it shows which answers the site lacks.
		filters: [ { field: 'nothing', operator: 'is', value: true } ],
		search: '',
	} );

	const remove = useCallback(
		async ( queries ) => {
			try {
				const res = await apiFetch( {
					path: API,
					method: 'DELETE',
					data: { queries },
				} );
				setData( ( d ) => ( { ...d, items: res.items } ) );
				setNotice( {
					status: 'success',
					text: __( 'Done.', 'memorial-plugin' ),
				} );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			}
		},
		[ setData ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'query',
				label: __( 'Query', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableHiding: false,
				render: ( { item } ) =>
					data?.faqUrl ? (
						<a
							href={
								data.faqUrl +
								( data.faqUrl.includes( '?' ) ? '&' : '?' ) +
								'q=' +
								encodeURIComponent( item.query )
							}
							target="_blank"
							rel="noreferrer"
						>
							{ item.query }
						</a>
					) : (
						item.query
					),
			},
			{
				id: 'count',
				label: __( 'Times', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
			},
			{
				id: 'found',
				label: __( 'Found', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
				render: ( { item } ) =>
					item.found ? (
						sprintf(
							/* translators: %d: number of answers. */
							_n(
								'%d answer',
								'%d answers',
								item.found,
								'memorial-plugin'
							),
							item.found
						)
					) : (
						<strong className="memorial-plugin-catalog__nothing">
							{ __( 'nothing', 'memorial-plugin' ) }
						</strong>
					),
			},
			{
				id: 'nothing',
				label: __( 'Nothing found', 'memorial-plugin' ),
				type: 'boolean',
				enableHiding: false,
				getValue: ( { item } ) => item.found === 0,
				elements: [
					{ value: true, label: __( 'Yes', 'memorial-plugin' ) },
					{ value: false, label: __( 'No', 'memorial-plugin' ) },
				],
				filterBy: { operators: [ 'is' ], isPrimary: true },
			},
			{
				id: 'where',
				label: __( 'Where', 'memorial-plugin' ),
				type: 'text',
				enableSorting: false,
				filterBy: false,
				render: ( { item } ) =>
					WHERE()
						.filter( ( w ) => item.where.includes( w.value ) )
						.map( ( w ) => w.label )
						.join( ', ' ),
			},
			{
				id: 'last',
				label: __( 'Last time', 'memorial-plugin' ),
				type: 'datetime',
				filterBy: false,
				render: ( { item } ) =>
					new Date( item.last ).toLocaleDateString(),
			},
		],
		[ data ]
	);

	const actions = useMemo(
		() =>
			data?.canClear
				? [
						{
							id: 'delete',
							label: __(
								'Remove from the list',
								'memorial-plugin'
							),
							isDestructive: true,
							supportsBulk: true,
							callback: ( list ) =>
								remove( list.map( ( i ) => i.query ) ),
						},
					]
				: [],
		[ data, remove ]
	);

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}

	const total = ( data?.items || [] ).reduce( ( s, i ) => s + i.count, 0 );
	return (
		<>
			<ListPage
				title={ __(
					'What people search for on the site',
					'memorial-plugin'
				) }
				subTitle={ sprintf(
					/* translators: 1: number of searches, 2: number of different queries. */
					__(
						'Searches on the “Questions and answers” and “Good to know” pages: %1$d, different queries: %2$d. Only the query and counters are kept — nothing about visitors; phone numbers, emails and links are not saved. A query that found nothing tells which answer to add: put the question into a “Details” block in the “Frequent questions” section of a page.',
						'memorial-plugin'
					),
					total,
					data?.items.length || 0
				) }
				actions={
					data?.canClear && data.items.length ? (
						<Button
							variant="secondary"
							isDestructive
							onClick={ () => setClearing( true ) }
						>
							{ __( 'Clear the statistics', 'memorial-plugin' ) }
						</Button>
					) : null
				}
				notice={ notice }
				onNotice={ () => setNotice( null ) }
				items={ data?.items }
				fields={ fields }
				actionsOf={ actions }
				view={ view }
				setView={ setView }
				loading={ ! data }
			/>
			{ clearing && (
				<Modal
					title={ __( 'Clear the statistics', 'memorial-plugin' ) }
					onRequestClose={ () => setClearing( false ) }
				>
					<Flex direction="column" gap={ 4 }>
						<p>
							{ __(
								'Delete all search statistics? “Frequent searches” under the search field will show the default words until new statistics come in.',
								'memorial-plugin'
							) }
						</p>
						<ModalButtons
							label={ __( 'Clear', 'memorial-plugin' ) }
							isDestructive
							onCancel={ () => setClearing( false ) }
							onOk={ async () => {
								await remove( [] );
								setClearing( false );
							} }
						/>
					</Flex>
				</Modal>
			) }
		</>
	);
}
