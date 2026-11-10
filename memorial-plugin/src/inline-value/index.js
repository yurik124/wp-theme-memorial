/**
 * “Site value” text format: a price, the number of models, years in business, the phone, company
 * details or a link to a document inside a paragraph, list or table. The page always shows the
 * current value (blocks/content/inline-values.php); here the value is inserted with its current text.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { registerFormatType, insert, create } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';

const NAME = 'memorial-plugin/value';
const VALUES = window.memorialPluginInlineValues || [];

function ValueModal( { value, onChange, onClose } ) {
	const [ key, setKey ] = useState( VALUES[ 0 ]?.key || '' );
	const [ param, setParam ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const def = VALUES.find( ( v ) => v.key === key );

	const add = () => {
		const atts = def?.param && param.trim() ? { [ def.param ]: param.trim() } : {};
		const query = [ [ 'key', key ] ]
			.concat(
				Object.entries( atts ).map( ( [ k, v ] ) => [ `atts[${ k }]`, v ] )
			)
			.map(
				( [ k, v ] ) =>
					`${ encodeURIComponent( k ) }=${ encodeURIComponent( v ) }`
			)
			.join( '&' );
		apiFetch( { path: `/memorial-plugin/v1/inline-value?${ query }` } )
			.then( ( res ) => {
				if ( ! res.html ) {
					setError(
						__( 'This value is empty — check the parameter.', 'memorial-plugin' )
					);
					return;
				}
				onChange( insert( value, create( { html: res.span } ) ) );
				onClose();
			} )
			.catch( ( e ) => setError( e.message ) );
	};

	return (
		<Modal title={ __( 'Insert a site value', 'memorial-plugin' ) } onRequestClose={ onClose }>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __( 'Value', 'memorial-plugin' ) }
				value={ key }
				options={ VALUES.map( ( v ) => ( { value: v.key, label: v.label } ) ) }
				onChange={ ( k ) => {
					setKey( k );
					setError( '' );
				} }
			/>
			{ def?.param && (
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ def.paramLabel }
					value={ param }
					onChange={ setParam }
				/>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<p>
				<Button variant="primary" onClick={ add }>
					{ __( 'Insert', 'memorial-plugin' ) }
				</Button>
			</p>
		</Modal>
	);
}

function Edit( { value, onChange, isActive } ) {
	const [ open, setOpen ] = useState( false );
	return (
		<>
			<RichTextToolbarButton
				icon="database"
				title={ __( 'Site value', 'memorial-plugin' ) }
				onClick={ () => setOpen( true ) }
				isActive={ isActive }
			/>
			{ open && (
				<ValueModal
					value={ value }
					onChange={ onChange }
					onClose={ () => setOpen( false ) }
				/>
			) }
		</>
	);
}

registerFormatType( NAME, {
	title: __( 'Site value', 'memorial-plugin' ),
	tagName: 'span',
	className: 'memorial-plugin-value',
	attributes: {
		value: 'data-value',
		atts: 'data-atts',
	},
	edit: Edit,
} );
