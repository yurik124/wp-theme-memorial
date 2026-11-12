/**
 * Shared texts in the editor: the Block Bindings source memorial-plugin/settings (blocks/bindings.php).
 *
 * A paragraph, heading or button bound to a field of «Memorial → …» shows the current text of the setting.
 * An editor may change it in place: the change is kept here until the page is saved, then written to the
 * setting (REST memorial-plugin/v1/texts) right before the page itself — so it takes effect on every page
 * that shows this text. If the setting was changed elsewhere meanwhile, the server refuses (409) and the
 * page is not saved, so nothing is overwritten silently.
 */
import {
	createReduxStore,
	register,
	select as dataSelect,
	dispatch as dataDispatch,
	useSelect,
} from '@wordpress/data';
import {
	registerBlockBindingsSource,
	registerBlockVariation,
} from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { ComboboxControl, Notice, PanelBody } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

const DATA = window.memorialPluginBindings || {};
const SOURCE = DATA.source || 'memorial-plugin/settings';
const STORE = 'memorial-plugin/texts';

// Plain text of the setting ↔ the HTML of a rich text.
const toHtml = ( text ) =>
	String( text ?? '' )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /\n/g, '<br>' );
const toText = ( html ) => {
	const box = document.createElement( 'div' );
	box.innerHTML = String( html ?? '' ).replace( /<br\s*\/?>/gi, '\n' );
	return box.textContent || '';
};

/* ---------- Store: values of the keys, edits not saved yet, versions of the settings ---------- */

const store = createReduxStore( STORE, {
	reducer( state = { values: {}, edits: {}, versions: {} }, action ) {
		switch ( action.type ) {
			case 'RECEIVE':
				return {
					...state,
					values: { ...state.values, ...action.values },
					versions: { ...state.versions, ...action.versions },
				};
			case 'EDIT':
				return {
					...state,
					edits: { ...state.edits, [ action.key ]: action.value },
				};
			case 'SAVED': {
				const edits = { ...state.edits };
				action.keys.forEach( ( k ) => delete edits[ k ] );
				return { ...state, edits };
			}
		}
		return state;
	},
	actions: {
		receive: ( values, versions ) => ( {
			type: 'RECEIVE',
			values,
			versions,
		} ),
		edit: ( key, value ) => ( { type: 'EDIT', key, value } ),
		saved: ( keys ) => ( { type: 'SAVED', keys } ),
	},
	selectors: {
		getValue: ( state, key ) =>
			key in state.edits ? state.edits[ key ] : state.values[ key ],
		isLoaded: ( state, key ) => key in state.values,
		getEdits: ( state ) => state.edits,
		getVersions: ( state ) => state.versions,
	},
	resolvers: {
		getValue:
			( key ) =>
			async ( { dispatch } ) => {
				const res = await load( key );
				dispatch.receive( res.values || {}, res.versions || {} );
			},
	},
} );
register( store );

// Keys asked within a moment go to the server in one request.
let queue = [];
let timer = 0;
function load( key ) {
	return new Promise( ( resolve, reject ) => {
		queue.push( { key, resolve, reject } );
		if ( ! timer ) {
			timer = setTimeout( () => {
				const items = queue;
				queue = [];
				timer = 0;
				apiFetch( {
					path: addQueryArgs( '/memorial-plugin/v1/texts', {
						keys: [ ...new Set( items.map( ( i ) => i.key ) ) ],
					} ),
				} ).then(
					( res ) => items.forEach( ( i ) => i.resolve( res ) ),
					( e ) => items.forEach( ( i ) => i.reject( e ) )
				);
			}, 20 );
		}
	} );
}

/* ---------- The source ---------- */

registerBlockBindingsSource( {
	name: SOURCE,
	label: __( 'Memorial settings', 'memorial-plugin' ),
	getValues( { select, bindings } ) {
		const out = {};
		Object.entries( bindings ).forEach( ( [ attr, binding ] ) => {
			const key = binding?.args?.key;
			const texts = select( STORE );
			const value = texts.getValue( key );
			if ( ! texts.isLoaded( key ) ) {
				out[ attr ] = '…';
			} else if ( value === null || value === undefined ) {
				out[ attr ] = __(
					'(this text is no longer in the settings)',
					'memorial-plugin'
				);
			} else if ( binding?.args?.as === 'tel' ) {
				out[ attr ] = 'tel:' + String( value ).replace( /[^\d+]/g, '' );
			} else if ( binding?.args?.as === 'mailto' ) {
				out[ attr ] = 'mailto:' + value;
			} else {
				out[ attr ] = attr === 'url' ? value : toHtml( value );
			}
		} );
		return out;
	},
	setValues( { dispatch, bindings } ) {
		let changed = false;
		Object.entries( bindings ).forEach( ( [ attr, binding ] ) => {
			const key = binding?.args?.key;
			// A link made from the value (tel:, mailto:) follows the text, it is not edited by itself.
			if ( key && ! binding.args.as && binding.newValue !== undefined ) {
				dispatch( STORE ).edit(
					key,
					attr === 'url'
						? binding.newValue
						: toText( binding.newValue )
				);
				changed = true;
			}
		} );
		// The page gets a change too, so “Save” becomes active; the server ignores this field.
		if ( changed ) {
			dispatch( 'core/editor' ).editPost( {
				memorial_plugin_texts: Date.now(),
			} );
		}
	},
	canUserEditValue: () => !! DATA.canEdit,
	getFieldsList: () =>
		( DATA.fields || [] ).map( ( f ) => ( {
			label: f.label,
			type: 'string',
			args: { key: f.key },
		} ) ),
} );

/* ---------- Saving: the texts go to the settings right before the page ---------- */

addFilter(
	'editor.preSavePost',
	'memorial-plugin/bindings/save-texts',
	async ( edits, options ) => {
		const clean = { ...edits };
		delete clean.memorial_plugin_texts;
		if ( options?.isAutosave || options?.isPreview ) {
			return clean;
		}
		const pending = dataSelect( STORE ).getEdits();
		const keys = Object.keys( pending );
		if ( ! keys.length ) {
			return clean;
		}
		try {
			const res = await apiFetch( {
				path: '/memorial-plugin/v1/texts',
				method: 'POST',
				data: {
					values: pending,
					versions: dataSelect( STORE ).getVersions(),
				},
			} );
			dataDispatch( STORE ).receive(
				res.values || {},
				res.versions || {}
			);
			dataDispatch( STORE ).saved( keys );
		} catch ( e ) {
			// The page is not saved either: the reason is shown in the editor's notice.
			throw new Error(
				e?.message ||
					__( 'The shared texts were not saved.', 'memorial-plugin' )
			);
		}
		return clean;
	}
);

/* ---------- “Shared text” panel: take the text of a block from the settings, or keep its own ---------- */

// The text attribute of the blocks that can show a shared text.
const TEXT_ATTR = {
	'core/paragraph': 'content',
	'core/heading': 'content',
	'core/button': 'text',
};
const FIELD_OPTIONS = ( DATA.fields || [] ).map( ( f ) => ( {
	value: f.key,
	label: f.label,
} ) );

const withSharedText = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { name, attributes, setAttributes } = props;
		const attr = TEXT_ATTR[ name ];
		const metadata = attributes?.metadata || {};
		const bindings = metadata.bindings || {};
		const own = attr && bindings[ attr ]?.source === SOURCE;
		const key = own ? bindings[ attr ].args?.key : '';
		const { unsaved, current } = useSelect(
			( select ) => ( {
				unsaved: !! key && key in select( STORE ).getEdits(),
				current: key ? select( STORE ).getValue( key ) : undefined,
			} ),
			[ key ]
		);
		if ( ! attr ) {
			return <BlockEdit { ...props } />;
		}
		const choose = ( next ) => {
			const rest = { ...bindings };
			delete rest[ attr ];
			if ( rest.url?.source === SOURCE ) {
				delete rest.url;
			}
			if ( ! next ) {
				// Back to the block's own text: what was shown stays as its text.
				const keep =
					typeof current === 'string' ? { [ attr ]: toHtml( current ) } : {};
				const meta = { ...metadata, bindings: rest };
				if ( ! Object.keys( rest ).length ) {
					delete meta.bindings;
				}
				setAttributes( { ...keep, metadata: meta } );
				return;
			}
			rest[ attr ] = { source: SOURCE, args: { key: next } };
			// A button with a phone number calls it.
			if ( name === 'core/button' && /\.num$/.test( next ) ) {
				rest.url = { source: SOURCE, args: { key: next, as: 'tel' } };
			}
			setAttributes( { metadata: { ...metadata, bindings: rest } } );
		};
		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Shared text', 'memorial-plugin' ) }
						initialOpen={ !! own }
					>
						<ComboboxControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Take the text from the Memorial settings',
								'memorial-plugin'
							) }
							help={ __(
								'Not chosen — the block has its own text, only on this page.',
								'memorial-plugin'
							) }
							value={ key || null }
							options={ FIELD_OPTIONS }
							onChange={ ( v ) => choose( v || '' ) }
						/>
						{ own && (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'This text is stored once in the Memorial settings: a change here changes it on every page that shows it, after the page is saved.',
									'memorial-plugin'
								) }
							</Notice>
						) }
						{ own && ! DATA.canEdit && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Only an editor or an administrator can change it.',
									'memorial-plugin'
								) }
							</Notice>
						) }
						{ unsaved && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Changed — it goes to the settings when you save the page.',
									'memorial-plugin'
								) }
							</Notice>
						) }
					</PanelBody>
				</InspectorControls>
			</>
		);
	},
	'withMemorialPluginSharedText'
);

addFilter(
	'editor.BlockEdit',
	'memorial-plugin/bindings/shared-text',
	withSharedText
);

/* ---------- Ready blocks: texts of the contacts as paragraphs ---------- */

const bind = ( key ) => ( {
	metadata: { bindings: { content: { source: SOURCE, args: { key } } } },
} );
[
	[
		'phone',
		__( 'Main phone (shared text)', 'memorial-plugin' ),
		'mem_contacts:phones.@first.num',
		'phone',
	],
	[
		'address',
		__( 'Main address (shared text)', 'memorial-plugin' ),
		'mem_contacts:addrs.@first.addr',
		'location',
	],
	[
		'hours',
		__( 'Opening hours (shared text)', 'memorial-plugin' ),
		'mem_contacts:addrs.@first.hours',
		'clock',
	],
].forEach( ( [ slug, title, key, icon ] ) =>
	registerBlockVariation( 'core/paragraph', {
		name: 'memorial-plugin/' + slug,
		title,
		description: __(
			'A paragraph whose text is kept in Memorial → Contacts: edit it here or there — it changes everywhere.',
			'memorial-plugin'
		),
		icon,
		category: 'memorial-contacts',
		keywords: [ 'memorial', __( 'contacts', 'memorial-plugin' ) ],
		scope: [ 'inserter' ],
		attributes: bind( key ),
		isActive: ( attrs ) =>
			attrs?.metadata?.bindings?.content?.args?.key === key,
	} )
);
