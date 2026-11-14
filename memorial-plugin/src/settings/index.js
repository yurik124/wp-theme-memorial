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
	TabPanel,
	TextareaControl,
	TextControl,
	Flex,
} from '@wordpress/components';
import { MediaUpload } from '@wordpress/media-utils';
import { DataForm } from '@wordpress/dataviews/wp';
import { Page } from '@wordpress/admin-ui';

import Hint from '../shared/hint';
import RewioScope from './rewio-scope';
import './style.scss';

const SITE = window.memorialPluginSettings?.siteUrl || '/';

/* ---------- Controls for schema field types ---------- */

// An explanation longer than this goes behind a round “?” button instead of standing next to the field.
const HINT_FROM = 70;

// A short explanation stays under the title; a long one hides behind “?”.
function Help( { text } ) {
	if ( ! text ) {
		return null;
	}
	if ( String( text ).length > HINT_FROM ) {
		return (
			<p className="memorial-plugin-settings__help is-hint">
				<Hint
					text={ text }
					label={ __( 'How it works', 'memorial-plugin' ) }
				/>
			</p>
		);
	}
	return <p className="memorial-plugin-settings__help">{ text }</p>;
}

/**
 * Label and explanation of a field: a long label is cut at “ — ”, “: ” or “ (” and its tail, together
 * with a long help text, goes behind “?”; a short help stays under the field.
 *
 * @param {Object}  def    Field description.
 * @param {boolean} always Every explanation behind “?” (a field in a row of fields).
 * @return {{label: *, help: string|undefined, name: string}} What to pass to the control.
 */
function labelOf( def, always = false ) {
	const full = String( def.label ?? '' );
	let name = full;
	let tail = '';
	// Only a long label is cut: a short “Name — variant” stays whole, the variant tells the fields apart.
	if ( full.length > 40 ) {
		const dash = full.indexOf( ' — ' );
		const mark = full.slice( 9 ).search( /:\s|[ \u00a0]\(/ );
		if ( dash > 0 && dash <= 40 ) {
			name = full.slice( 0, dash );
			tail = full.slice( dash + 3 );
		} else if ( mark >= 0 ) {
			name = full.slice( 0, mark + 9 );
			tail = full
				.slice( mark + 9 )
				.replace( /^:\s/, '' )
				.trim();
		} else if ( dash > 0 ) {
			name = full.slice( 0, dash );
			tail = full.slice( dash + 3 );
		}
	}
	const help = String( def.help ?? '' );
	// In a row of fields any text under one field pushes it out of line, so there it always goes behind “?”.
	const longHelp = always || help.length > HINT_FROM;
	const hint = [ tail, longHelp || tail ? help : '' ]
		.filter( Boolean )
		.map( ( t ) => t.charAt( 0 ).toUpperCase() + t.slice( 1 ) )
		.join( '. ' )
		.replace( /\.\. /g, '. ' );
	return {
		name,
		label: hint ? (
			<span className="memorial-plugin-settings__label-row">
				{ name }
				<Hint text={ hint } />
			</span>
		) : (
			name
		),
		help: hint ? undefined : help || undefined,
	};
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
				{ labelOf( def ).label }
			</span>
			<Help text={ labelOf( def ).help } />
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

// Sum of the “sum” field over the rows of a list, and the way a price is shown.
const sumOf = ( def, rows ) =>
	( Array.isArray( rows ) ? rows : [] ).reduce(
		( total, row ) => total + ( parseFloat( row?.[ def.sum ] ) || 0 ),
		0
	);
const money = ( n ) => new Intl.NumberFormat( 'ru-RU' ).format( n ) + ' ₽';

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
	// A nested list with prices: its total first, it is what the owner looks for.
	const totals = ( def.fields || [] )
		.filter( ( f ) => f.type === 'repeater' && f.sum )
		.map(
			( f ) =>
				__( 'Total', 'memorial-plugin' ) +
				': ' +
				money( sumOf( f, row?.[ f.id ] ) )
		);
	// Fields named by the schema (“summary”): a select shows its option, a checkbox its label.
	const named = ( def.summary || [] )
		.map( ( id ) => ( def.fields || [] ).find( ( f ) => f.id === id ) )
		.filter( ( f ) => f && isShown( f, row ) )
		.map( ( f ) => {
			const v = row?.[ f.id ];
			if ( f.type === 'select' ) {
				return ( f.options || [] ).find(
					( o ) => o.value === String( v ?? '' )
				)?.label;
			}
			if ( f.type === 'checkbox' ) {
				return Number( v ) ? f.short || f.label : '';
			}
			return String( v ?? '' ).trim();
		} )
		.filter( Boolean );
	const lists = ( def.fields || [] )
		.filter(
			( f ) => f.type === 'repeater' && Array.isArray( row?.[ f.id ] )
		)
		.map(
			( f ) =>
				f.label.split( ' — ' )[ 0 ].split( '. ' )[ 0 ] +
				': ' +
				row[ f.id ].length
		);
	const token = tokenOf( def, row );
	return [
		...totals,
		...named,
		...lists,
		...( token ? [ token ] : [] ),
	].join( ' · ' );
}

/**
 * What to write in a text to put the value of this row in (schema “token”), for example {price:lenta}.
 *
 * @param {Object} def Repeater description.
 * @param {Object} row Row values.
 * @return {string} The token or ''.
 */
function tokenOf( def, row ) {
	const v = String( row?.[ def.token?.field ] ?? '' ).trim();
	return def.token && v ? def.token.fmt.replace( '%s', v ) : '';
}

/**
 * Does a field show for these values (schema “show_if”: another field has one of the values)?
 *
 * @param {Object} def  Field description.
 * @param {Object} data Values of the row or group.
 * @return {boolean} Shown.
 */
function isShown( def, data ) {
	if ( ! def.showIf ) {
		return true;
	}
	const v = String( data?.[ def.showIf.field ] ?? '' );
	return ( def.showIf.in || [] ).includes( v );
}

// A new row of a list: the fields that have a default value (“Show on the site” is on) get it.
function newRow( def ) {
	const row = {};
	( def.fields || [] ).forEach( ( f ) => {
		if ( f.default !== undefined ) {
			row[ f.id ] = f.default;
		}
	} );
	return row;
}

/**
 * Where a link leads: a page of the site from the list, or any address typed by hand.
 * The value is the page ID (digits) or the address.
 *
 * @param {Object}   props
 * @param {Object}   props.common   Label, help and sizes of the control.
 * @param {Object}   props.def      Field description (options — the pages).
 * @param {string}   props.value    Page ID or address.
 * @param {Function} props.onChange Receives the new value.
 */
function LinkEdit( { common, def, value, onChange } ) {
	const v = String( value ?? '' );
	const isPage = /^\d+$/.test( v );
	const [ own, setOwn ] = useState( v !== '' && ! isPage );
	const custom = own || ( v !== '' && ! isPage );
	return (
		<div className="memorial-plugin-settings__link">
			<SelectControl
				{ ...common }
				value={ custom ? '__url' : v }
				options={ [
					{
						value: '',
						label: __( '— no link —', 'memorial-plugin' ),
					},
					...( def.options || [] ),
					{
						value: '__url',
						label: __( 'Another address…', 'memorial-plugin' ),
					},
				] }
				onChange={ ( next ) => {
					setOwn( next === '__url' );
					onChange( next === '__url' ? '' : next );
				} }
			/>
			{ custom && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Address', 'memorial-plugin' ) }
					hideLabelFromVision
					type="url"
					placeholder="https://"
					value={ isPage ? '' : v }
					onChange={ onChange }
				/>
			) }
		</div>
	);
}

/**
 * «Настройки → Разрешения»: rows — screens and rights, columns — roles, a checkbox in every cell.
 *
 * @param {Object}   props
 * @param {Object}   props.def      Field: items (key, label), roles (id, label).
 * @param {Object}   props.value    { key: { role: 0|1 } }.
 * @param {Function} props.onChange Receives the whole table.
 */
function PermsEdit( { def, value, onChange } ) {
	const v = value && typeof value === 'object' ? value : {};
	const roles = def.roles || [];
	const set = ( key, role, on ) =>
		onChange( {
			...v,
			[ key ]: { ...( v[ key ] || {} ), [ role ]: on ? 1 : 0 },
		} );
	return (
		<table className="memorial-plugin-settings__perms">
			<thead>
				<tr>
					<th scope="col">{ __( 'What', 'memorial-plugin' ) }</th>
					{ roles.map( ( r ) => (
						<th scope="col" key={ r.id }>
							{ r.label }
						</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				{ ( def.items || [] ).map( ( row ) => (
					<tr key={ row.key }>
						<th scope="row">{ row.label }</th>
						{ roles.map( ( r ) => (
							<td key={ r.id }>
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ row.label + ' — ' + r.label }
									className="memorial-plugin-settings__perm"
									checked={
										!! Number( v[ row.key ]?.[ r.id ] )
									}
									onChange={ ( on ) =>
										set( row.key, r.id, on )
									}
								/>
							</td>
						) ) }
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

// Which rows are open, kept outside the component: DataForm may mount a list again on every change,
// and the open rows must stay open (adding a row or changing a field used to fold them all).
const OPEN_ROWS = new Map();

function RepeaterEdit( { def, value, onChange } ) {
	const rows = Array.isArray( value ) ? value : [];
	// Rows that have content are folded at first; new rows are open.
	const openKey = def.id + '|' + def.label;
	const [ open, setOpenState ] = useState(
		() => OPEN_ROWS.get( openKey ) || new Set()
	);
	const setOpen = ( next ) => {
		OPEN_ROWS.set( openKey, next );
		setOpenState( next );
	};
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
				{ labelOf( def ).label }
			</span>
			<Help text={ labelOf( def ).help } />
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
									<span
										className={
											'memorial-plugin-settings__row-fold dashicons dashicons-arrow-' +
											( shown ? 'down' : 'right' ) +
											'-alt2'
										}
										aria-hidden="true"
									/>
									<strong className="memorial-plugin-settings__row-title">
										{ title ||
											sprintf(
												/* translators: %d: row number. */
												__(
													'Row %d',
													'memorial-plugin'
												),
												i + 1
											) }
									</strong>
									{ ! shown && rowSummary( def, row ) && (
										<span className="memorial-plugin-settings__row-sum">
											{ rowSummary( def, row ) }
										</span>
									) }
									<span className="memorial-plugin-settings__row-toggle">
										{ shown
											? __( 'Fold', 'memorial-plugin' )
											: __(
													'Unfold',
													'memorial-plugin'
												) }
									</span>
								</button>
							) }
							{ shown && tokenOf( def, row ) && (
								<p className="memorial-plugin-settings__token">
									{ __(
										'In a text field or a block write:',
										'memorial-plugin'
									) + ' ' }
									<code>{ tokenOf( def, row ) }</code>
								</p>
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
									onChange(
										rows.filter( ( r, j ) => j !== i )
									)
								}
							/>
						</div>
					</div>
				);
			} ) }
			<div className="memorial-plugin-settings__list-foot">
				<Button
					variant="secondary"
					onClick={ () => {
						setOpen( new Set( [ ...open, rows.length ] ) );
						onChange( [ ...rows, newRow( def ) ] );
					} }
				>
					{ '+ ' + ( def.add || __( 'Add', 'memorial-plugin' ) ) }
				</Button>
				{ def.sum && (
					<p
						className="memorial-plugin-settings__total"
						aria-live="polite"
					>
						{ __( 'Total', 'memorial-plugin' ) + ': ' }
						<strong>{ money( sumOf( def, rows ) ) }</strong>
					</p>
				) }
			</div>
		</div>
	);
}

function SecretEdit( { common, def, value, onChange } ) {
	const [ visible, setVisible ] = useState( false );
	return (
		<div className="memorial-plugin-settings__secret">
			<TextControl
				{ ...common }
				type={ visible ? 'text' : 'password' }
				autoComplete="off"
				placeholder={ def.placeholder }
				value={ value ?? '' }
				onChange={ onChange }
			/>
			<Button
				size="compact"
				icon={ visible ? 'hidden' : 'visibility' }
				label={
					visible
						? __( 'Hide the key', 'memorial-plugin' )
						: __( 'Show the key', 'memorial-plugin' )
				}
				onClick={ () => setVisible( ! visible ) }
			/>
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

/**
 * An action of a screen (“Send a test email”): POST to def.path with the values of its group, also unsaved;
 * the answer is shown under the button.
 *
 * @param {Object} props
 * @param {Object} props.def Field: label, path, help.
 * @param {Object} props.row Values of the group.
 */
function ActionEdit( { def, row } ) {
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );
	const run = async () => {
		setBusy( true );
		setResult( null );
		try {
			const res = await apiFetch( {
				path: def.path,
				method: 'POST',
				data: { values: row || {} },
			} );
			setResult( {
				status: res.ok ? 'success' : 'error',
				text: res.message,
			} );
		} catch ( e ) {
			setResult( { status: 'error', text: e.message } );
		}
		setBusy( false );
	};
	return (
		<div className="memorial-plugin-settings__action">
			<Flex justify="flex-start" align="center" gap={ 2 }>
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ run }
				>
					{ def.label }
				</Button>
				{ def.help && <Hint text={ def.help } /> }
			</Flex>
			{ result && (
				<Notice status={ result.status } isDismissible={ false }>
					{ result.text }
				</Notice>
			) }
		</div>
	);
}

function ControlFor( { def, value, onChange, row } ) {
	const text = labelOf(
		def,
		! [ 'textarea', 'code', 'map' ].includes( def.type )
	);
	const common = {
		__next40pxDefaultSize: true,
		__nextHasNoMarginBottom: true,
		label: def.hideLabel ? text.name : text.label,
		hideLabelFromVision: !! def.hideLabel,
		help: text.help,
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
		case 'checkbox':
			// “Name — what it does”: the name next to the box, the explanation behind “?”.
			return (
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ text.label }
					help={ text.help }
					checked={ !! Number( value ) }
					onChange={ ( v ) => onChange( v ? 1 : 0 ) }
				/>
			);
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
		case 'button':
			return <ActionEdit def={ def } row={ row } />;
		case 'perms':
			return (
				<PermsEdit def={ def } value={ value } onChange={ onChange } />
			);
		case 'link':
			return (
				<LinkEdit
					common={ common }
					def={ def }
					value={ value }
					onChange={ onChange }
				/>
			);
		case 'textarea':
		case 'code':
		case 'map':
			return (
				<TextareaControl
					__nextHasNoMarginBottom
					label={ common.label }
					hideLabelFromVision={ !! def.hideLabel }
					help={ common.help }
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
		case 'secret':
			return (
				<SecretEdit
					common={ common }
					def={ def }
					value={ value }
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
function FieldsForm( { defs: all, data, onChange } ) {
	const visible = all.filter( ( def ) => isShown( def, data ) );
	const ids = visible.map( ( def ) => def.id ).join( ',' );
	// eslint-disable-next-line react-hooks/exhaustive-deps -- the visible fields change only with their ids.
	const defs = useMemo( () => visible, [ all, ids ] );
	const fields = useMemo(
		() =>
			defs.map( ( def ) => ( {
				id: def.id,
				label: def.label,
				type: 'text',
				Edit: ( { data: item, onChange: change } ) => (
					<ControlFor
						def={ def }
						row={ item }
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

// The intro of a screen: a short one is shown as it is, a long instruction is folded under one line.
function Intro( { html } ) {
	const body = (
		<RawHTML className="memorial-plugin-settings__intro">{ html }</RawHTML>
	);
	if ( String( html ).replace( /<[^>]+>/g, '' ).length < 260 ) {
		return body;
	}
	return (
		<details className="memorial-plugin-settings__more">
			<summary>
				{ __( 'How this screen works', 'memorial-plugin' ) }
			</summary>
			{ body }
		</details>
	);
}

/**
 * Cards of a screen; top-level fields with “tab” stand on tabs of their own («Настройки»: Функции, Разрешения).
 *
 * @param {Array}    defs Top-level fields.
 * @param {Function} card Renders one card.
 * @return {*} Cards or tabs.
 */
function withTabs( defs, card ) {
	const tabbed = defs.filter( ( d ) => d.tab );
	if ( ! tabbed.length ) {
		return defs.map( card );
	}
	// Fields with the same tab title stand together on one tab («Почта»: codes, two emails).
	const titles = [ ...new Set( tabbed.map( ( d ) => d.tab ) ) ];
	return (
		<>
			{ defs.filter( ( d ) => ! d.tab ).map( card ) }
			<TabPanel
				className="memorial-plugin-settings__tabs"
				tabs={ titles.map( ( t ) => ( {
					name: tabbed.find( ( d ) => d.tab === t ).id,
					title: t,
				} ) ) }
			>
				{ ( tab ) => {
					const title = tabbed.find( ( d ) => d.id === tab.name ).tab;
					return tabbed.filter( ( d ) => d.tab === title ).map( card );
				} }
			</TabPanel>
		</>
	);
}

/**
 * A folded hint of a screen (“Codes for the emails”): the label opens the text.
 *
 * @param {Object} props
 * @param {Object} props.def Field: label, help (HTML).
 * @return {*} Element.
 */
function Note( { def } ) {
	return (
		<details className="memorial-plugin-settings__more memorial-plugin-settings__note">
			<summary>{ def.label }</summary>
			<RawHTML>{ def.help }</RawHTML>
		</details>
	);
}

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
				data: { value, version: page?.version },
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
	}, [ option, value, page ] );

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
					<Intro html={ page.intro } />
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
				{ withTabs( page.fields, ( def ) =>
					def.type === 'note' ? (
						<Note key={ def.id } def={ def } />
					) : (
					<Card
						key={ def.id }
						className="memorial-plugin-settings__card"
					>
						<CardHeader>
							<h2 className="memorial-plugin-settings__card-title">
								{ def.label || def.id }
								{ String( def.help ?? '' ).length >
									HINT_FROM && <Hint text={ def.help } /> }
							</h2>
						</CardHeader>
						<CardBody>
							{ def.type === 'group' ? (
								<>
									{ String( def.help ?? '' ).length <=
										HINT_FROM && (
										<Help text={ def.help } />
									) }
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
									defs={ [
										{
											...def,
											hideLabel: true,
											// A long explanation is behind “?” next to the card title.
											help:
												String( def.help ?? '' )
													.length > HINT_FROM
													? ''
													: def.help,
										},
									] }
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
					)
				) }
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
