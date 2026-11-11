/**
 * Common part of the catalog lists: loading, DataViews, the quick edit form, notices, reordering.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Flex, Modal, Notice } from '@wordpress/components';
import {
	DataViews,
	DataForm,
	filterSortAndPaginate,
} from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';

/**
 * Data of a list screen: GET once, then items are replaced by what the server returns after each change.
 *
 * @param {string} path REST path.
 * @return {Object} { data, setData, error, reload, merge }.
 */
export function useListData( path ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( '' );
	const reload = useCallback(
		() =>
			apiFetch( { path } )
				.then( setData )
				.catch( ( e ) => setError( e.message ) ),
		[ path ]
	);
	useEffect( () => {
		reload();
	}, [ reload ] );
	const merge = useCallback( ( res ) => {
		setData( ( d ) => {
			const byId = new Map( d.items.map( ( i ) => [ i.id, i ] ) );
			( res.items || [] ).forEach( ( i ) => byId.set( i.id, i ) );
			( res.trashed || [] ).forEach( ( id ) => byId.delete( id ) );
			const next = { ...d, items: [ ...byId.values() ] };
			if ( res.kinds ) {
				next.kinds = res.kinds;
			}
			return next;
		} );
	}, [] );
	return { data, setData, error, reload, merge };
}

/**
 * Items in a new order: the item moves one place up (-1) or down (1) among the others of the same group.
 *
 * @param {Object[]} items All items of the group, sorted by order.
 * @param {number}   id    Item to move.
 * @param {number}   dir   -1 or 1.
 * @return {number[]|null} IDs in the new order or null when it cannot move.
 */
export function moved( items, id, dir ) {
	const ids = items.map( ( i ) => i.id );
	const i = ids.indexOf( id );
	const j = i + dir;
	if ( i < 0 || j < 0 || j >= ids.length ) {
		return null;
	}
	[ ids[ i ], ids[ j ] ] = [ ids[ j ], ids[ i ] ];
	return ids;
}

/**
 * Sort by the site order (as the visitor sees the list), then by ID.
 *
 * @param {Object[]} items Items.
 * @return {Object[]} Sorted copy.
 */
export function bySiteOrder( items ) {
	return [ ...items ].sort( ( a, b ) => a.order - b.order || a.id - b.id );
}

/**
 * Quick edit form in a modal.
 *
 * @param {Object}   props
 * @param {string}   props.title   Modal title.
 * @param {Object}   props.item    Item.
 * @param {Object[]} props.fields  Editable fields.
 * @param {() => void} props.onSave  Gets the changed values; returns a promise.
 * @param {() => void} props.onClose Close.
 * @param {Element}        props.extra   Something to show above the form (links).
 */
export function EditModal( { title, item, fields, onSave, onClose, extra } ) {
	const [ edits, setEdits ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const data = useMemo( () => ( { ...item, ...edits } ), [ item, edits ] );
	const save = async () => {
		setBusy( true );
		try {
			await onSave( edits );
			onClose();
		} catch ( e ) {
			setError( e.message );
			setBusy( false );
		}
	};
	return (
		<Modal title={ title } onRequestClose={ onClose } size="large">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ extra }
			<DataForm
				data={ data }
				fields={ fields }
				form={ {
					layout: { type: 'regular' },
					fields: fields.map( ( f ) => f.id ),
				} }
				onChange={ ( e ) => setEdits( ( x ) => ( { ...x, ...e } ) ) }
			/>
			<Flex
				justify="flex-end"
				className="memorial-plugin-catalog__buttons"
			>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'memorial-plugin' ) }
				</Button>
				<Button
					variant="primary"
					isBusy={ busy }
					disabled={ busy || ! Object.keys( edits ).length }
					onClick={ save }
				>
					{ __( 'Save', 'memorial-plugin' ) }
				</Button>
			</Flex>
		</Modal>
	);
}

/**
 * Footer of an action modal: Cancel + the main button.
 *
 * @param {Object}   props
 * @param {string}   props.label         Main button.
 * @param {boolean}  props.busy          Waiting for the server.
 * @param {boolean}  props.disabled      Main button disabled.
 * @param {() => void} props.onCancel      Cancel.
 * @param {() => void} props.onOk          Main button.
 * @param {boolean}  props.isDestructive Red main button.
 */
export function ModalButtons( {
	label,
	busy,
	disabled,
	onCancel,
	onOk,
	isDestructive,
} ) {
	return (
		<Flex justify="flex-end" className="memorial-plugin-catalog__buttons">
			<Button variant="tertiary" onClick={ onCancel }>
				{ __( 'Cancel', 'memorial-plugin' ) }
			</Button>
			<Button
				variant="primary"
				isDestructive={ isDestructive }
				isBusy={ busy }
				disabled={ busy || disabled }
				onClick={ onOk }
			>
				{ label }
			</Button>
		</Flex>
	);
}

/**
 * A list page: title, buttons, notices, optional tabs and the DataViews table.
 *
 * @param {Object}   props
 * @param {string}   props.title     Page title.
 * @param {string}   props.subTitle  Text under the title.
 * @param {Element}        props.actions   Buttons at the top.
 * @param {Element}        props.tabs      Tabs above the list.
 * @param {Object}   props.notice    {status, text} or null.
 * @param {() => void} props.onNotice  Clear the notice.
 * @param {Object[]} props.items     Items.
 * @param {Object[]} props.fields    Fields.
 * @param {Object[]} props.actionsOf Item actions.
 * @param {Object}   props.view      View.
 * @param {() => void} props.setView   Change the view.
 * @param {() => void} props.prefilter Filters that DataViews cannot do itself (array fields): (items, view) → items.
 * @param {boolean}  props.loading   Loading.
 */
export function ListPage( {
	title,
	subTitle,
	actions,
	tabs,
	notice,
	onNotice,
	items,
	fields,
	actionsOf,
	view,
	setView,
	prefilter,
	loading,
} ) {
	const { data, paginationInfo } = useMemo( () => {
		let list = items || [];
		let v = view;
		if ( prefilter ) {
			[ list, v ] = prefilter( list, view );
		}
		return filterSortAndPaginate( list, v, fields );
	}, [ items, view, fields, prefilter ] );
	return (
		<Page title={ title } subTitle={ subTitle } actions={ actions }>
			<div className="memorial-plugin-catalog">
				{ notice && (
					<Notice status={ notice.status } onRemove={ onNotice }>
						{ notice.text }
					</Notice>
				) }
				{ tabs }
				<DataViews
					data={ data }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					actions={ actionsOf }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {}, grid: {}, list: {} } }
					getItemId={ ( item ) => String( item.id ) }
					isLoading={ loading }
				/>
			</div>
		</Page>
	);
}

/**
 * Filter of an array field (categories, kinds): DataViews compares one value, so these filters are applied
 * here and removed from the view passed on.
 *
 * @param {string[]} ids Array field IDs.
 * @return {() => void} prefilter.
 */
export function arrayFilters( ids ) {
	return ( items, view ) => {
		let list = items;
		const rest = [];
		( view.filters || [] ).forEach( ( f ) => {
			if ( ! ids.includes( f.field ) || ! f.value?.length ) {
				rest.push( f );
				return;
			}
			const want = f.value.map( Number );
			list = list.filter( ( item ) => {
				const has = ( item[ f.field ] || [] ).some( ( v ) =>
					want.includes( v )
				);
				return f.operator === 'isNone' ? ! has : has;
			} );
		} );
		return [ list, { ...view, filters: rest } ];
	};
}
