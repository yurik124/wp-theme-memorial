/**
 * Settings screens of the plugin (Memorial → Contacts, Prices, Reviews…): DataForm built from the
 * field schema of the page (REST memorial-plugin/v1/settings/<option>, admin/settings-form.php).
 *
 * Each top-level field is a collapsible panel; inside it a DataForm with the fields of that group.
 * Field types of the schema get their controls from `@wordpress/components`; lists (repeater) are
 * rows with a DataForm each; images use the media library.
 */
import {
	createRoot,
	RawHTML,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
	Flex,
} from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';
import { DataForm } from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';

import RewioScope from './rewio-scope';
import './style.scss';

const SITE = window.memorialPluginSettings?.siteUrl || '/';

/* ---------- Controls for schema field types ---------- */

function Help( { text } ) {
	return text ? (
		<p className="memorial-plugin-settings__help">{ text }</p>
	) : null;
}

function ImageEdit( { def, value, onChange } ) {
	return (
		<div className="memorial-plugin-settings__image">
			<span
				className={
					'memorial-plugin-settings__label' +
					( def.hideLabel ? ' screen-reader-text' : '' )
				}
			>
				{ def.label }
			</span>
			<Flex justify="flex-start" align="center">
				{ value && (
					<img
						src={ value }
						alt=""
						className="memorial-plugin-settings__thumb"
					/>
				) }
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					onSelect={ ( media ) => onChange( media?.url || '' ) }
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
						onClick={ () => onChange( '' ) }
					>
						{ __( 'Remove', 'memorial-plugin' ) }
					</Button>
				) }
			</Flex>
			<Help text={ def.help } />
		</div>
	);
}

function OrderEdit( { def, value, onChange } ) {
	const keys = useMemo( () => {
		const all = ( def.options || [] ).map( ( o ) => o.value );
		const saved = ( Array.isArray( value ) ? value : [] ).filter( ( k ) =>
			all.includes( k )
		);
		return [ ...saved, ...all.filter( ( k ) => ! saved.includes( k ) ) ];
	}, [ def.options, value ] );
	const label = ( k ) => def.options.find( ( o ) => o.value === k )?.label;
	const move = ( i, d ) => {
		const next = [ ...keys ];
		[ next[ i ], next[ i + d ] ] = [ next[ i + d ], next[ i ] ];
		onChange( next );
	};
	return (
		<div>
			<span
				className={
					'memorial-plugin-settings__label' +
					( def.hideLabel ? ' screen-reader-text' : '' )
				}
			>
				{ def.label }
			</span>
			<Help text={ def.help } />
			<ol className="memorial-plugin-settings__order">
				{ keys.map( ( k, i ) => (
					<li key={ k }>
						<span>{ label( k ) }</span>
						<Button
							size="small"
							icon="arrow-up-alt2"
							label={ __( 'Up', 'memorial-plugin' ) }
							disabled={ i === 0 }
							onClick={ () => move( i, -1 ) }
						/>
						<Button
							size="small"
							icon="arrow-down-alt2"
							label={ __( 'Down', 'memorial-plugin' ) }
							disabled={ i === keys.length - 1 }
							onClick={ () => move( i, 1 ) }
						/>
					</li>
				) ) }
			</ol>
		</div>
	);
}

/**
 * Title of a folded row: the “collapse” field, otherwise the first filled text field.
 *
 * @param {Object} def Repeater description.
 * @param {Object} row Row values.
 * @return {string} Title.
 */
function rowTitle( def, row ) {
	if ( def.collapse && row?.[ def.collapse ] ) {
		return String( row[ def.collapse ] );
	}
	const first = ( def.fields || [] ).find(
		( f ) =>
			[ 'text', 'textarea', 'email', 'number' ].includes( f.type ) &&
			String( row?.[ f.id ] ?? '' ).trim()
	);
	return first ? String( row[ first.id ] ) : '';
}

/**
 * Short summary of a folded row: how many items its nested lists have.
 *
 * @param {Object} def Repeater description.
 * @param {Object} row Row values.
 * @return {string} Summary.
 */
function rowSummary( def, row ) {
	return ( def.fields || [] )
		.filter( ( f ) => f.type === 'repeater' && Array.isArray( row?.[ f.id ] ) )
		.map(
			( f ) =>
				f.label.split( ' — ' )[ 0 ].split( '. ' )[ 0 ] +
				': ' +
				row[ f.id ].length
		)
		.join( ' · ' );
}

function RepeaterEdit( { def, value, onChange } ) {
	const rows = Array.isArray( value ) ? value : [];
	// Rows that have content are folded at first; new rows are open.
	const [ open, setOpen ] = useState( () => new Set() );
	const isOpen = ( i ) => def.inline || open.has( i );
	const toggle = ( i ) => {
		const next = new Set( open );
		if ( next.has( i ) ) {
			next.delete( i );
		} else {
			next.add( i );
		}
		setOpen( next );
	};
	const set = ( i, row ) =>
		onChange( rows.map( ( r, j ) => ( j === i ? row : r ) ) );
	const move = ( i, d ) => {
		const next = [ ...rows ];
		[ next[ i ], next[ i + d ] ] = [ next[ i + d ], next[ i ] ];
		const was = new Set( open );
		const a = was.has( i );
		const b = was.has( i + d );
		was.delete( i );
		was.delete( i + d );
		if ( a ) {
			was.add( i + d );
		}
		if ( b ) {
			was.add( i );
		}
		setOpen( was );
		onChange( next );
	};
	return (
		<div
			className={
				'memorial-plugin-settings__repeater' +
				( def.inline ? ' is-inline' : '' )
			}
		>
			<span
				className={
					'memorial-plugin-settings__label' +
					( def.hideLabel ? ' screen-reader-text' : '' )
				}
			>
				{ def.label }
			</span>
			<Help text={ def.help } />
			{ ! def.inline && rows.length > 1 && (
				<div className="memorial-plugin-settings__fold-all">
					<Button
						size="small"
						variant="tertiary"
						onClick={ () => setOpen( new Set() ) }
					>
						{ __( 'Fold all', 'memorial-plugin' ) }
					</Button>
					<Button
						size="small"
						variant="tertiary"
						onClick={ () =>
							setOpen( new Set( rows.map( ( r, i ) => i ) ) )
						}
					>
						{ __( 'Unfold all', 'memorial-plugin' ) }
					</Button>
				</div>
			) }
			{ rows.map( ( row, i ) => {
				const title = rowTitle( def, row );
				const shown = isOpen( i );
				return (
					<div
						className={
							'memorial-plugin-settings__row' +
							( shown ? '' : ' is-folded' )
						}
						key={ i }
					>
						{ ! def.inline && (
							<span
								className="memorial-plugin-settings__num"
								aria-hidden="true"
							>
								{ i + 1 }
							</span>
						) }
						<div className="memorial-plugin-settings__row-main">
							{ ! def.inline && (
								<button
									type="button"
									className="memorial-plugin-settings__row-head"
									aria-expanded={ shown }
									onClick={ () => toggle( i ) }
								>
									<strong className="memorial-plugin-settings__row-title">
										{ title ||
											sprintf(
												/* translators: %d: row number. */
												__( 'Row %d', 'memorial-plugin' ),
												i + 1
											) }
									</strong>
									{ ! shown && rowSummary( def, row ) && (
										<span className="memorial-plugin-settings__row-sum">
											{ rowSummary( def, row ) }
										</span>
									) }
									<span
										className={
											'dashicons dashicons-arrow-' +
											( shown ? 'up' : 'down' ) +
											'-alt2'
										}
										aria-hidden="true"
									/>
								</button>
							) }
							{ shown && (
								<div className="memorial-plugin-settings__fields">
									<FieldsForm
										defs={ def.fields || [] }
										data={ row || {} }
										onChange={ ( edits ) =>
											set( i, { ...row, ...edits } )
										}
									/>
								</div>
							) }
						</div>
						<div className="memorial-plugin-settings__row-actions">
							<Button
								size="small"
								icon="arrow-up-alt2"
								label={ sprintf(
									/* translators: %d: row number. */
									__( 'Move row %d up', 'memorial-plugin' ),
									i + 1
								) }
								disabled={ i === 0 }
								onClick={ () => move( i, -1 ) }
							/>
							<Button
								size="small"
								icon="arrow-down-alt2"
								label={ sprintf(
									/* translators: %d: row number. */
									__( 'Move row %d down', 'memorial-plugin' ),
									i + 1
								) }
								disabled={ i === rows.length - 1 }
								onClick={ () => move( i, 1 ) }
							/>
							<Button
								size="small"
								icon="trash"
								isDestructive
								label={ sprintf(
									/* translators: %d: row number. */
									__( 'Remove row %d', 'memorial-plugin' ),
									i + 1
								) }
								onClick={ () =>
									onChange( rows.filter( ( r, j ) => j !== i ) )
								}
							/>
						</div>
					</div>
				);
			} ) }
			<Button
				variant="secondary"
				onClick={ () => {
					setOpen( new Set( [ ...open, rows.length ] ) );
					onChange( [ ...rows, {} ] );
				} }
			>
				{ '+ ' + ( def.add || __( 'Add', 'memorial-plugin' ) ) }
			</Button>
		</div>
	);
}

function GroupEdit( { def, value, onChange } ) {
	const [ shown, setShown ] = useState( true );
	return (
		<fieldset className="memorial-plugin-settings__group">
			<legend
				className={ def.hideLabel ? 'screen-reader-text' : undefined }
			>
				<button
					type="button"
					className="memorial-plugin-settings__group-head"
					aria-expanded={ shown }
					onClick={ () => setShown( ! shown ) }
				>
					{ def.label }
					<span
						className={
							'dashicons dashicons-arrow-' +
							( shown ? 'up' : 'down' ) +
							'-alt2'
						}
						aria-hidden="true"
					/>
				</button>
			</legend>
			{ shown && (
				<>
					<Help text={ def.help } />
					<FieldsForm
						defs={ def.fields || [] }
						data={ value || {} }
						onChange={ ( edits ) =>
							onChange( { ...( value || {} ), ...edits } )
						}
					/>
				</>
			) }
		</fieldset>
	);
}

function ControlFor( { def, value, onChange } ) {
	const common = {
		__next40pxDefaultSize: true,
		__nextHasNoMarginBottom: true,
		label: def.label,
		hideLabelFromVision: !! def.hideLabel,
		help: def.help,
	};
	switch ( def.type ) {
		case 'group':
			return (
				<GroupEdit def={ def } value={ value } onChange={ onChange } />
			);
		case 'repeater':
			return (
				<RepeaterEdit
					def={ def }
					value={ value }
					onChange={ onChange }
				/>
			);
		case 'rewio-scope':
			return (
				<RewioScope def={ def } value={ value } onChange={ onChange } />
			);
		case 'image':
			return (
				<ImageEdit def={ def } value={ value } onChange={ onChange } />
			);
		case 'order':
			return (
				<OrderEdit def={ def } value={ value } onChange={ onChange } />
			);
		case 'checkbox': {
			// “Name — what it does”: the name in bold, the explanation under it in normal text.
			const [ name, ...rest ] = String( def.label ).split( ' — ' );
			const about = rest.join( ' — ' );
			return (
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ name }
					help={
						[ about, def.help ].filter( Boolean ).join( ' ' ) ||
						undefined
					}
					checked={ !! Number( value ) }
					onChange={ ( v ) => onChange( v ? 1 : 0 ) }
				/>
			);
		}
		case 'select':
		case 'page':
			return (
				<SelectControl
					{ ...common }
					value={ String( value ?? '' ) }
					options={ [
						...( def.type === 'page'
							? [
									{
										value: '',
										label: __(
											'— not selected —',
											'memorial-plugin'
										),
									},
								]
							: [] ),
						...( def.options || [] ),
					] }
					onChange={ onChange }
				/>
			);
		case 'textarea':
		case 'code':
		case 'map':
			return (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ def.label }
					hideLabelFromVision={ !! def.hideLabel }
					help={ def.help }
					placeholder={ def.placeholder }
					rows={ def.rows || ( def.type === 'map' ? 2 : 3 ) }
					className={
						def.type === 'code'
							? 'memorial-plugin-settings__code'
							: ''
					}
					value={ value ?? '' }
					onChange={ onChange }
				/>
			);
		default:
			return (
				<TextControl
					{ ...common }
					type={
						{
							number: 'number',
							email: 'email',
							color: 'color',
							secret: 'password',
						}[ def.type ] || 'text'
					}
					autoComplete={ def.type === 'secret' ? 'off' : undefined }
					placeholder={ def.placeholder }
					value={ value ?? '' }
					onChange={ onChange }
				/>
			);
	}
}

/**
 * DataForm for a list of schema fields; every field renders its own control.
 *
 * @param {Object}                  props
 * @param {Array}                   props.defs     Field descriptions from the server.
 * @param {Object}                  props.data     Values of these fields.
 * @param {(edits: Object) => void} props.onChange Receives changed values ({ id: value }).
 */
function FieldsForm( { defs, data, onChange } ) {
	const fields = useMemo(
		() =>
			defs.map( ( def ) => ( {
				id: def.id,
				label: def.label,
				type: 'text',
				Edit: ( { data: item, onChange: change } ) => (
					<ControlFor
						def={ def }
						value={ item[ def.id ] }
						onChange={ ( v ) => change( { [ def.id ]: v } ) }
					/>
				),
			} ) ),
		[ defs ]
	);
	const form = useMemo(
		() => ( {
			layout: { type: 'regular', labelPosition: 'none' },
			fields: defs.map( ( def ) => def.id ),
		} ),
		[ defs ]
	);
	return (
		<DataForm
			data={ data }
			fields={ fields }
			form={ form }
			onChange={ onChange }
		/>
	);
}

/* ---------- Page ---------- */

function App( { option } ) {
	const [ page, setPage ] = useState( null );
	const [ value, setValue ] = useState( {} );
	const [ dirty, setDirty ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const extraRef = useRef( null );

	useEffect( () => {
		apiFetch( { path: `/memorial-plugin/v1/settings/${ option }` } )
			.then( ( res ) => {
				setPage( res );
				setValue( res.value || {} );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', text: e.message } )
			);
	}, [ option ] );

	// Panels printed by PHP (tabs, Rewio status with its scripts) go under the page header.
	useEffect( () => {
		const extra = document.getElementById(
			'memorial-plugin-settings-extra'
		);
		if ( page && extra && extraRef.current ) {
			extraRef.current.appendChild( extra );
			extra.hidden = false;
		}
	}, [ page ] );

	// Leaving with unsaved changes — ask first.
	useEffect( () => {
		const warn = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	const save = useCallback( async () => {
		setBusy( true );
		try {
			const res = await apiFetch( {
				path: `/memorial-plugin/v1/settings/${ option }`,
				method: 'POST',
				data: { value },
			} );
			setPage( res );
			setValue( res.value || {} );
			setDirty( false );
			setNotice( {
				status: 'success',
				text: __(
					'Saved. The changes are already on the site.',
					'memorial-plugin'
				),
			} );
		} catch ( e ) {
			setNotice( { status: 'error', text: e.message } );
		}
		setBusy( false );
	}, [ option, value ] );

	if ( ! page ) {
		return notice ? (
			<Notice status={ notice.status } isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const actions = (
		<Flex>
			<Button variant="secondary" href={ SITE } target="_blank">
				{ __( 'Open site', 'memorial-plugin' ) }
			</Button>
			<Button
				variant="primary"
				isBusy={ busy }
				disabled={ busy || ! dirty }
				onClick={ save }
			>
				{ __( 'Save', 'memorial-plugin' ) }
			</Button>
		</Flex>
	);

	return (
		<Page title={ page.title } actions={ actions }>
			<Flex
				direction="column"
				align="stretch"
				gap={ 4 }
				className="memorial-plugin-settings"
			>
				{ page.intro && (
					// The intro may hold lists and paragraphs, so it is not the page subtitle (a <p>).
					<RawHTML className="memorial-plugin-settings__intro">
						{ page.intro }
					</RawHTML>
				) }
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
					>
						{ notice.text }
					</Notice>
				) }
				<div ref={ extraRef } />
				{ page.fields.map( ( def ) => (
					<Card
						key={ def.id }
						className="memorial-plugin-settings__card"
					>
						<CardHeader>
							<h2>{ def.label || def.id }</h2>
						</CardHeader>
						<CardBody>
							{ def.type === 'group' ? (
								<>
									<Help text={ def.help } />
									<FieldsForm
										defs={ def.fields || [] }
										data={ value[ def.id ] || {} }
										onChange={ ( edits ) => {
											setValue( ( v ) => ( {
												...v,
												[ def.id ]: {
													...( v[ def.id ] || {} ),
													...edits,
												},
											} ) );
											setDirty( true );
										} }
									/>
								</>
							) : (
								<FieldsForm
									defs={ [ { ...def, hideLabel: true } ] }
									data={ value }
									onChange={ ( edits ) => {
										setValue( ( v ) => ( {
											...v,
											...edits,
										} ) );
										setDirty( true );
									} }
								/>
							) }
						</CardBody>
					</Card>
				) ) }
				<div
					className={
						'memorial-plugin-settings__bar' +
						( dirty ? ' is-dirty' : '' )
					}
				>
					<span
						className="memorial-plugin-settings__state"
						role="status"
					>
						{ dirty
							? __(
									'There are unsaved changes',
									'memorial-plugin'
								)
							: __( 'All changes are saved', 'memorial-plugin' ) }
					</span>
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy || ! dirty }
						onClick={ save }
					>
						{ __( 'Save', 'memorial-plugin' ) }
					</Button>
				</div>
			</Flex>
		</Page>
	);
}

const root = document.getElementById( 'memorial-plugin-settings' );
if ( root ) {
	createRoot( root ).render( <App option={ root.dataset.option } /> );
}
