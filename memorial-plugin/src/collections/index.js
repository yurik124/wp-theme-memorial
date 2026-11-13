/**
 * Record lists (call-back requests, guest book reviews): DataViews for the list, DataForm in a modal
 * for editing and adding. Fields, labels and options come from REST
 * memorial-plugin/v1/collections/<name> (admin/collections.php).
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
	Modal,
	Notice,
	TextareaControl,
	Flex,
} from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';
import {
	DataViews,
	DataForm,
	filterSortAndPaginate,
} from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';
import BooleanEdit from '../shared/boolean-edit';
import { runAbility } from '../shared/abilities';
import './style.scss';

const API = '/memorial-plugin/v1/collections/';

function ImageEdit( { data, field, onChange } ) {
	const value = data[ field.id ];
	return (
		<div>
			<p>{ field.label }</p>
			<Flex justify="flex-start">
				{ value?.url && (
					<img src={ value.url } alt="" width="64" height="64" />
				) }
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					onSelect={ ( m ) =>
						onChange( {
							[ field.id ]: {
								id: m.id,
								url: m.sizes?.thumbnail?.url || m.url,
							},
						} )
					}
					render={ ( { open } ) => (
						<Button variant="secondary" onClick={ open }>
							{ value
								? __( 'Replace photo', 'memorial-plugin' )
								: __( 'Choose photo', 'memorial-plugin' ) }
						</Button>
					) }
				/>
				{ value && (
					<Button
						variant="link"
						isDestructive
						onClick={ () => onChange( { [ field.id ]: null } ) }
					>
						{ __( 'Remove', 'memorial-plugin' ) }
					</Button>
				) }
			</Flex>
		</div>
	);
}

function TextareaEdit( { data, field, onChange } ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ field.label }
			help={ field.description }
			rows={ 6 }
			value={ data[ field.id ] || '' }
			onChange={ ( v ) => onChange( { [ field.id ]: v } ) }
		/>
	);
}

/**
 * Server field description → DataViews / DataForm field.
 *
 * @param {Object} def Field from the server.
 * @return {Object} Field.
 */
function toField( def ) {
	const field = {
		id: def.id,
		label: def.label,
		description: def.help,
		placeholder: def.placeholder,
		readonly: !! def.readonly,
		enableGlobalSearch: [ 'text', 'textarea' ].includes( def.type ),
	};
	switch ( def.type ) {
		case 'boolean':
			return {
				...field,
				type: 'boolean',
				elements: [
					{ value: true, label: __( 'Yes', 'memorial-plugin' ) },
					{ value: false, label: __( 'No', 'memorial-plugin' ) },
				],
				filterBy: { operators: [ 'is' ] },
				render: ( { item } ) =>
					item[ def.id ] ? (
						<span className="memorial-plugin-badge">
							{ __( 'Yes', 'memorial-plugin' ) }
						</span>
					) : (
						<span className="memorial-plugin-badge is-muted">
							{ __( 'No', 'memorial-plugin' ) }
						</span>
					),
				Edit: BooleanEdit,
			};
		case 'select':
			return {
				...field,
				type: 'text',
				elements: def.options,
				filterBy: { operators: [ 'isAny' ] },
			};
		case 'integer':
			return { ...field, type: 'integer' };
		case 'date':
			return { ...field, type: 'date' };
		case 'datetime':
			return {
				...field,
				type: 'datetime',
				render: ( { item } ) =>
					item[ def.id ]
						? new Date( item[ def.id ] ).toLocaleString()
						: '',
			};
		case 'url':
			return {
				...field,
				type: 'text',
				render: ( { item } ) =>
					item[ def.id ] ? (
						<a
							href={ item[ def.id ] }
							target="_blank"
							rel="noreferrer"
						>
							{ decodeURI( new URL( item[ def.id ] ).pathname ) }
						</a>
					) : (
						'—'
					),
			};
		case 'image':
			return {
				...field,
				type: 'text',
				enableSorting: false,
				enableGlobalSearch: false,
				render: ( { item } ) =>
					item[ def.id ]?.url ? (
						<img
							src={ item[ def.id ].url }
							alt=""
							width="48"
							height="48"
							style={ { objectFit: 'cover', borderRadius: 4 } }
						/>
					) : null,
				Edit: ImageEdit,
			};
		case 'textarea':
			return {
				...field,
				type: 'text',
				isLong: true,
				render: ( { item } ) => {
					const t = String( item[ def.id ] || '' );
					return t.length > 120 ? t.slice( 0, 120 ) + '…' : t;
				},
				Edit: TextareaEdit,
			};
		default:
			return { ...field, type: 'text' };
	}
}

function EditModal( { name, data, fields, onClose, onSaved } ) {
	const [ item, setItem ] = useState( data );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const editable = fields.filter( ( f ) => ! f.readonly );
	const fixed = fields.filter(
		( f ) =>
			f.readonly && item[ f.id ] !== '' && item[ f.id ] !== null && item[ f.id ] !== undefined
	);
	const save = async () => {
		setBusy( true );
		const values = {};
		editable.forEach( ( f ) => ( values[ f.id ] = item[ f.id ] ) );
		try {
			await apiFetch( {
				path: API + name + ( item.id ? '/' + item.id : '' ),
				method: item.id ? 'PUT' : 'POST',
				data: { values },
			} );
			onSaved();
			onClose();
		} catch ( e ) {
			setError( e.message );
		}
		setBusy( false );
	};
	return (
		<Modal
			title={
				item.id
					? __( 'Edit', 'memorial-plugin' )
					: __( 'Add', 'memorial-plugin' )
			}
			onRequestClose={ onClose }
			size="medium"
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ fixed.length > 0 && (
				<dl className="memorial-plugin-collection__fixed">
					{ fixed.map( ( f ) => (
						<div key={ f.id }>
							<dt>{ f.label }</dt>
							<dd>
								{ f.render && ! f.isLong
									? f.render( { item } )
									: item[ f.id ] }
							</dd>
						</div>
					) ) }
				</dl>
			) }
			<DataForm
				data={ item }
				fields={ editable }
				form={ {
					layout: { type: 'regular' },
					fields: editable.map( ( f ) => f.id ),
				} }
				onChange={ ( edits ) =>
					setItem( ( i ) => ( { ...i, ...edits } ) )
				}
			/>
			<Flex justify="flex-end">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'memorial-plugin' ) }
				</Button>
				<Button variant="primary" isBusy={ busy } onClick={ save }>
					{ __( 'Save', 'memorial-plugin' ) }
				</Button>
			</Flex>
		</Modal>
	);
}

function App( { name } ) {
	const [ meta, setMeta ] = useState( null );
	const [ items, setItems ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ view, setView ] = useState( null );

	const load = useCallback(
		() =>
			apiFetch( { path: API + name } )
				.then( ( res ) => {
					setMeta( res );
					setItems( res.items );
					setView(
						( v ) =>
							v || {
								type: 'table',
								// Fields marked hidden are only in the edit window (and in “View options”).
								fields: res.fields
									.filter(
										( f ) =>
											! f.hidden &&
											f.id !== res.fields[ 0 ].id
									)
									.map( ( f ) => f.id ),
								titleField: res.fields[ 0 ].id,
								perPage: 25,
								page: 1,
								sort: res.sort || {
									field: res.fields[ 0 ].id,
									direction: 'asc',
								},
								filters: [],
							}
					);
				} )
				.catch( ( e ) =>
					setNotice( { status: 'error', text: e.message } )
				),
		[ name ]
	);
	useEffect( () => {
		load();
	}, [ load ] );

	const fields = useMemo(
		() => ( meta ? meta.fields.map( toField ) : [] ),
		[ meta ]
	);

	const remove = useCallback(
		async ( list ) => {
			for ( const item of list ) {
				await apiFetch( {
					path: API + name + '/' + item.id,
					method: 'DELETE',
				} );
			}
			setNotice( {
				status: 'success',
				text: sprintf(
					/* translators: %d: number of records. */
					_n(
						'%d record moved to the trash.',
						'%d records moved to the trash.',
						list.length,
						'memorial-plugin'
					),
					list.length
				),
			} );
			load();
		},
		[ name, load ]
	);

	// Hide / show reviews of the list: ability memorial-plugin/set-review-hidden (reviews/rewio-archive.php).
	const setHidden = useCallback(
		async ( list, hidden ) => {
			try {
				for ( const item of list ) {
					await runAbility( 'memorial-plugin/set-review-hidden', {
						id: item.id,
						hidden,
					} );
				}
				setNotice( {
					status: 'success',
					text: sprintf(
						/* translators: %d: number of reviews. */
						hidden
							? _n(
									'%d review hidden on the site.',
									'%d reviews hidden on the site.',
									list.length,
									'memorial-plugin'
							  )
							: _n(
									'%d review shown on the site.',
									'%d reviews shown on the site.',
									list.length,
									'memorial-plugin'
							  ),
						list.length
					),
				} );
				load();
			} catch ( e ) {
				setNotice( { status: 'error', text: e.message } );
			}
		},
		[ load ]
	);

	const actions = useMemo( () => {
		const out = [
			{
				id: 'edit',
				label: __( 'Edit', 'memorial-plugin' ),
				isPrimary: true,
				icon: 'edit',
				callback: ( [ item ] ) => setEditing( item ),
			},
		];
		if ( meta?.trash !== false ) {
			out.push( {
				id: 'trash',
				label: __( 'Move to trash', 'memorial-plugin' ),
				isDestructive: true,
				supportsBulk: true,
				callback: ( list ) => remove( list ),
			} );
		}
		if ( name === 'rewio' ) {
			out.push(
				{
					id: 'hide',
					label: __( 'Hide on the site', 'memorial-plugin' ),
					icon: 'hidden',
					supportsBulk: true,
					isEligible: ( item ) => item.status !== 'draft',
					callback: ( list ) => setHidden( list, true ),
				},
				{
					id: 'show',
					label: __( 'Show on the site', 'memorial-plugin' ),
					icon: 'visibility',
					supportsBulk: true,
					isEligible: ( item ) => item.status === 'draft',
					callback: ( list ) => setHidden( list, false ),
				}
			);
		}
		if ( name === 'leads' ) {
			// Ability memorial-plugin/set-lead-done (admin/abilities.php), also in the Command Palette.
			out.splice( 1, 0, {
				id: 'done',
				label: __( 'Mark as called back', 'memorial-plugin' ),
				supportsBulk: true,
				isEligible: ( item ) => ! item.done,
				callback: async ( list ) => {
					try {
						for ( const item of list ) {
							await runAbility( 'memorial-plugin/set-lead-done', {
								id: item.id,
								done: true,
							} );
						}
						load();
					} catch ( e ) {
						setNotice( { status: 'error', text: e.message } );
					}
				},
			} );
			out.unshift( {
				id: 'call',
				label: __( 'Call', 'memorial-plugin' ),
				icon: 'phone',
				callback: ( [ item ] ) =>
					( window.location.href =
						'tel:' +
						String( item.phone ).replace( /[^\d+]/g, '' ) ),
			} );
		}
		return out;
	}, [ name, remove, load, meta, setHidden ] );

	// One switch of the whole list (“Show reviews deleted on the review site”).
	const toggle = async ( value ) => {
		try {
			const res = await apiFetch( {
				path: API + name + '/toggle',
				method: 'POST',
				data: { value },
			} );
			setMeta( ( m ) => ( {
				...m,
				toggle: { ...m.toggle, value: !! res.value },
			} ) );
			setNotice( {
				status: 'success',
				text: __( 'Saved.', 'memorial-plugin' ),
			} );
		} catch ( e ) {
			setNotice( { status: 'error', text: e.message } );
		}
	};

	const { data, paginationInfo } = useMemo(
		() =>
			view
				? filterSortAndPaginate( items || [], view, fields )
				: {
						data: [],
						paginationInfo: { totalItems: 0, totalPages: 0 },
					},
		[ items, view, fields ]
	);

	if ( ! meta || ! view ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : null;
	}

	return (
		<Page
			title={ meta.title }
			subTitle={ meta.intro }
			actions={
				meta.create ? (
					<Button
						variant="primary"
						onClick={ () => setEditing( {} ) }
					>
						{ __( 'Add', 'memorial-plugin' ) }
					</Button>
				) : null
			}
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			{ meta.toggle && (
				<div className="memorial-plugin-collection__toggle">
					<CheckboxControl
						__nextHasNoMarginBottom
						label={ meta.toggle.label }
						checked={ !! meta.toggle.value }
						disabled={ ! meta.toggle.can }
						onChange={ toggle }
					/>
				</div>
			) }
			<DataViews
				data={ data }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				actions={ actions }
				paginationInfo={ paginationInfo }
				defaultLayouts={ { table: {}, list: {}, grid: {} } }
				getItemId={ ( item ) => String( item.id ) }
				isLoading={ items === null }
			/>
			{ editing && (
				<EditModal
					name={ name }
					data={ editing }
					fields={ fields }
					onClose={ () => setEditing( null ) }
					onSaved={ () => {
						setNotice( {
							status: 'success',
							text: __( 'Saved.', 'memorial-plugin' ),
						} );
						load();
					} }
				/>
			) }
		</Page>
	);
}

const root = document.getElementById( 'memorial-plugin-collection' );
if ( root ) {
	createRoot( root ).render( <App name={ root.dataset.name } /> );
}
