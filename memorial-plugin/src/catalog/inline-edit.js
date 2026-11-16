/**
 * Catalog → Products: a value in the table is edited in place — a click (or Enter on it) turns
 * a text or a number into a field in the cell; a choice (categories, “On the site”, a list
 * specification) opens a small form by the value with the same control as the “Edit” window.
 * Enter or ✓ saves; Esc or a click outside the field cancels. A field with `toggle`
 * (“On the site”) is a checkbox in the table: a click saves at once. Saving goes the same way
 * as the “Edit” window.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Popover,
	TextControl,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';

/**
 * Is the field edited as plain text in the cell (not a choice and without its own control)?
 *
 * @param {Object} field Field.
 * @return {boolean} Yes or no.
 */
const isPlain = ( field ) =>
	! field.elements && ! field.Edit && field.type !== 'boolean';

// Keeps the click and the keys in the cell: the table does not select the row.
const keep = ( e ) => e.stopPropagation();

// A checkbox in the table (“On the site”): a click saves at once.
function Toggle( { item, field, onSave } ) {
	const { on, off } = field.toggle;
	const [ checked, setChecked ] = useState( item[ field.id ] === on );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	useEffect(
		() => setChecked( item[ field.id ] === on ),
		[ item, field, on ]
	);
	const change = async ( next ) => {
		setChecked( next );
		setError( '' );
		setBusy( true );
		try {
			await onSave( item, { [ field.id ]: next ? on : off } );
		} catch ( err ) {
			setChecked( ! next );
			setError( err.message );
		} finally {
			setBusy( false );
		}
	};
	return (
		// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions -- only keeps the click in the cell.
		<div className="memorial-plugin-inline__toggle" onClick={ keep }>
			<CheckboxControl
				__nextHasNoMarginBottom
				aria-label={ sprintf(
					/* translators: %s: product title. */
					__( 'Shown on the site: %s', 'memorial-plugin' ),
					item.title
				) }
				checked={ checked }
				disabled={ busy }
				onChange={ change }
			/>
			{ error && (
				<span className="memorial-plugin-inline__error" role="alert">
					{ error }
				</span>
			) }
		</div>
	);
}

// The value of a cell and its editing.
function Cell( { item, field, shown, onSave } ) {
	const [ editing, setEditing ] = useState( false );
	const [ value, setValue ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const anchor = useRef();
	const form = useRef();
	const plain = isPlain( field );

	const start = ( e ) => {
		e.stopPropagation();
		setValue( item[ field.id ] ?? '' );
		setError( '' );
		setEditing( true );
	};
	const cancel = () => {
		setEditing( false );
		setError( '' );
		window.requestAnimationFrame( () => anchor.current?.focus() );
	};
	const save = async ( e ) => {
		e?.preventDefault();
		e?.stopPropagation();
		let v = value;
		if ( field.type === 'integer' ) {
			v = parseInt( String( v ).replace( /\D/g, '' ), 10 ) || 0;
		}
		if ( v === ( item[ field.id ] ?? '' ) ) {
			cancel();
			return;
		}
		setBusy( true );
		try {
			await onSave( item, { [ field.id ]: v } );
			setEditing( false );
			window.requestAnimationFrame( () => anchor.current?.focus() );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setBusy( false );
		}
	};
	// A click or the focus outside the field cancels the change.
	useEffect( () => {
		if ( ! editing || ! plain ) {
			return;
		}
		const outside = ( e ) => {
			if ( form.current && ! form.current.contains( e.target ) ) {
				setEditing( false );
				setError( '' );
			}
		};
		const doc = form.current?.ownerDocument || window.document;
		doc.addEventListener( 'pointerdown', outside, true );
		doc.addEventListener( 'focusin', outside, true );
		return () => {
			doc.removeEventListener( 'pointerdown', outside, true );
			doc.removeEventListener( 'focusin', outside, true );
		};
	}, [ editing, plain ] );
	const onKey = ( e ) => {
		e.stopPropagation();
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			cancel();
		}
	};
	const buttons = (
		<Button
			type="submit"
			icon="yes"
			size="small"
			label={ __( 'Save', 'memorial-plugin' ) }
			isBusy={ busy }
			disabled={ busy }
		/>
	);

	if ( editing && plain ) {
		return (
			// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions -- the form only keeps the clicks in the cell.
			<form
				ref={ form }
				className="memorial-plugin-inline__form"
				onSubmit={ save }
				onClick={ keep }
				onKeyDown={ onKey }
			>
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					className="memorial-plugin-inline__input"
					hideLabelFromVision
					label={ field.label }
					// eslint-disable-next-line jsx-a11y/no-autofocus -- the field opens on the user's click.
					autoFocus
					inputMode={ field.type === 'integer' ? 'numeric' : 'text' }
					value={ String( value ?? '' ) }
					onChange={ setValue }
					disabled={ busy }
				/>
				{ buttons }
				{ error && (
					<span
						className="memorial-plugin-inline__error"
						role="alert"
					>
						{ error }
					</span>
				) }
			</form>
		);
	}

	return (
		<>
			<button
				type="button"
				ref={ anchor }
				className="memorial-plugin-inline__value"
				onClick={ start }
				onKeyDown={ keep }
				aria-label={ sprintf(
					/* translators: %s: column, e.g. Price. */
					__( 'Change: %s', 'memorial-plugin' ),
					field.label
				) }
				aria-expanded={ editing }
			>
				{ shown }
			</button>
			{ editing && (
				<Popover
					anchor={ anchor.current }
					placement="bottom-start"
					onClose={ cancel }
					focusOnMount="firstElement"
					className="memorial-plugin-inline__pop"
				>
					{ /* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions -- keeps the keys in the form. */ }
					<form
						className="memorial-plugin-inline__popform"
						onSubmit={ save }
						onKeyDown={ onKey }
						onClick={ keep }
					>
						<DataForm
							data={ { ...item, [ field.id ]: value } }
							fields={ [ field ] }
							form={ {
								layout: { type: 'regular' },
								fields: [ field.id ],
							} }
							onChange={ ( e ) =>
								e[ field.id ] !== undefined &&
								setValue( e[ field.id ] )
							}
						/>
						{ error && (
							<p
								className="memorial-plugin-inline__error"
								role="alert"
							>
								{ error }
							</p>
						) }
						<div className="memorial-plugin-inline__popbtns">
							<Button
								variant="tertiary"
								onClick={ cancel }
								disabled={ busy }
							>
								{ __( 'Cancel', 'memorial-plugin' ) }
							</Button>
							<Button
								type="submit"
								variant="primary"
								isBusy={ busy }
								disabled={ busy }
							>
								{ __( 'Save', 'memorial-plugin' ) }
							</Button>
						</div>
					</form>
				</Popover>
			) }
		</>
	);
}

/**
 * Fields of the table with their values edited in place.
 *
 * @param {Object[]} fields   Fields.
 * @param {Function} editable ( field ) => is it edited in the table.
 * @param {Function} onSave   ( item, edits ) => Promise — as the “Edit” window saves.
 * @return {Object[]} Fields.
 */
export default function inlineFields( fields, editable, onSave ) {
	return fields.map( ( field ) => {
		if ( ! editable( field ) ) {
			return field;
		}
		const render = field.render;
		if ( field.toggle ) {
			return {
				...field,
				render: ( props ) => (
					<Toggle
						item={ props.item }
						field={ field }
						onSave={ onSave }
					/>
				),
			};
		}
		return {
			...field,
			render: ( props ) => {
				const shown = render
					? render( props )
					: ( props.item[ field.id ] ?? '' );
				return (
					<Cell
						item={ props.item }
						field={ field }
						shown={ shown === '' || shown === null ? '—' : shown }
						onSave={ onSave }
					/>
				);
			},
		};
	} );
}
