/**
 * Our works → Kinds of works: add, rename, reorder, delete.
 */
import { useCallback, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Modal, Notice, TextControl } from '@wordpress/components';
import { useListData, moved, ModalButtons, ListPage } from './list-screen';

const API = '/memorial-plugin/v1/catalog/work-types';

function NameForm( { value = '', label, onCancel, onOk } ) {
	const [ name, setName ] = useState( value );
	const [ busy, setBusy ] = useState( false );
	return (
		<Flex direction="column" gap={ 4 }>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Name', 'memorial-plugin' ) }
				value={ name }
				onChange={ setName }
			/>
			<ModalButtons
				label={ label }
				busy={ busy }
				disabled={ ! name.trim() || name === value }
				onCancel={ onCancel }
				onOk={ async () => {
					setBusy( true );
					await onOk( name.trim() );
				} }
			/>
		</Flex>
	);
}

export default function WorkTypes() {
	// The list of works returns the kinds too.
	const { data, error, setData } = useListData( '/memorial-plugin/v1/catalog/works' );
	const [ notice, setNotice ] = useState( null );
	const [ adding, setAdding ] = useState( false );
	const [ view, setView ] = useState( {
		type: 'table',
		titleField: 'name',
		fields: [ 'count' ],
		sort: { field: 'order', direction: 'asc' },
		perPage: 50,
		page: 1,
		filters: [],
		search: '',
	} );

	const change = useCallback(
		async ( body ) => {
			try {
				const res = await apiFetch( { path: API, method: 'POST', data: body } );
				setData( ( d ) => ( { ...d, kinds: res.kinds } ) );
				setNotice( { status: 'success', text: __( 'Saved.', 'memorial-plugin' ) } );
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			}
		},
		[ setData ]
	);

	const items = useMemo(
		() => ( data?.kinds || [] ).map( ( k, i ) => ( { ...k, order: i + 1 } ) ),
		[ data ]
	);

	const fields = useMemo(
		() => [
			{
				id: 'name',
				label: __( 'Name', 'memorial-plugin' ),
				type: 'text',
				enableGlobalSearch: true,
				enableHiding: false,
			},
			{
				id: 'count',
				label: __( 'Works', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
			},
			{
				id: 'order',
				label: __( 'Order', 'memorial-plugin' ),
				type: 'integer',
				filterBy: false,
			},
		],
		[]
	);

	const actions = useMemo( () => {
		const move = ( dir ) => ( [ item ] ) => {
			const ids = moved( items, item.id, dir );
			if ( ids ) {
				change( { op: 'order', ids } );
			}
		};
		return [
			{
				id: 'rename',
				label: __( 'Rename', 'memorial-plugin' ),
				isPrimary: true,
				icon: 'edit',
				RenderModal: ( { items: [ item ], closeModal } ) => (
					<NameForm
						value={ item.name }
						label={ __( 'Rename', 'memorial-plugin' ) }
						onCancel={ closeModal }
						onOk={ async ( name ) => {
							await change( { op: 'rename', id: item.id, name } );
							closeModal();
						} }
					/>
				),
			},
			{ id: 'up', label: __( 'Move up', 'memorial-plugin' ), callback: move( -1 ) },
			{ id: 'down', label: __( 'Move down', 'memorial-plugin' ), callback: move( 1 ) },
			{
				id: 'delete',
				label: __( 'Delete', 'memorial-plugin' ),
				isDestructive: true,
				RenderModal: ( { items: [ item ], closeModal } ) => (
					<Flex direction="column" gap={ 4 }>
						<p>
							{ item.count
								? sprintf(
										/* translators: 1: kind name, 2: number of works. */
										_n(
											'Delete “%1$s”? %2$d work stays, without this kind.',
											'Delete “%1$s”? %2$d works stay, without this kind.',
											item.count,
											'memorial-plugin'
										),
										item.name,
										item.count
								  )
								: sprintf(
										/* translators: %s: kind name. */
										__( 'Delete “%s”?', 'memorial-plugin' ),
										item.name
								  ) }
						</p>
						<ModalButtons
							label={ __( 'Delete', 'memorial-plugin' ) }
							isDestructive
							onCancel={ closeModal }
							onOk={ async () => {
								await change( { op: 'delete', id: item.id } );
								closeModal();
							} }
						/>
					</Flex>
				),
			},
		];
	}, [ items, change ] );

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
				title={ __( 'Kinds of works', 'memorial-plugin' ) }
				subTitle={ __(
					'The buttons above the list of works on the site go in this order.',
					'memorial-plugin'
				) }
				actions={
					<Button variant="primary" onClick={ () => setAdding( true ) }>
						{ __( 'Add kind', 'memorial-plugin' ) }
					</Button>
				}
				notice={ notice }
				onNotice={ () => setNotice( null ) }
				items={ items }
				fields={ fields }
				actionsOf={ actions }
				view={ view }
				setView={ setView }
				loading={ ! data }
			/>
			{ adding && (
				<Modal
					title={ __( 'New kind of work', 'memorial-plugin' ) }
					onRequestClose={ () => setAdding( false ) }
				>
					<NameForm
						label={ __( 'Add', 'memorial-plugin' ) }
						onCancel={ () => setAdding( false ) }
						onOk={ async ( name ) => {
							await change( { op: 'add', name } );
							setAdding( false );
						} }
					/>
				</Modal>
			) }
		</>
	);
}
