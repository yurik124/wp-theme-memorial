/**
 * Block settings of the plugin blocks in the editor.
 *
 * 1. “What to show” choosers. The blocks are registered in PHP only and WordPress generates plain text
 *    fields for their attributes; an attribute with a chooser (blocks/block-meta.php,
 *    memorial_plugin_block_attributes()) gets a real list instead: the address of the top bar, a catalog
 *    section, a product, a page or several pages.
 * 2. A block that cannot find its data on this page by itself (section cards of a page without
 *    sub-sections, a product card outside the product template…) shows the chooser in place of an empty
 *    preview; after a choice the preview comes back, and the choice can be changed in the settings.
 * 3. Any plugin block can be folded into one line in the editor (toolbar button “Fold”), so long
 *    blocks — packages, catalogs — do not get in the way. It changes nothing on the site.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	BlockControls,
	BlockIcon,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	FormTokenField,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { getBlockType } from '@wordpress/blocks';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import './style.scss';

const DATA = window.memorialPluginBlockControls || {};
const PICKERS = DATA.pickers || {};
const TEXTS = DATA.texts || {};
const ADDRESSES = DATA.addresses || [];
// A block that shows nothing until this setting is filled in: its fields open in place of the block.
const REQUIRED = { 'memorial-plugin/stat': 'number' };
const PREFIX = 'memorial-plugin/';
// Blocks that look for their data on the page when nothing is chosen.
const SOURCE = [
	'memorial-plugin/cards',
	'memorial-plugin/catalog',
	'memorial-plugin/category-list',
	'memorial-plugin/category-photos',
	'memorial-plugin/catalog-archive',
	'memorial-plugin/product',
];
const sourceCache = {};

const ids = ( value ) =>
	String( value || '' )
		.split( ',' )
		.map( ( v ) => v.trim() )
		.filter( Boolean );

const titleOf = ( record ) =>
	decodeEntities( record?.title?.rendered || record?.name || '' ) ||
	sprintf(
		/* translators: %d: post ID. */
		__( '#%d (no title)', 'memorial-plugin' ),
		record?.id || 0
	);

/**
 * Posts of a type for a chooser: the found ones plus the chosen ones.
 *
 * @param {string}   postType Post type.
 * @param {string}   search   Search text.
 * @param {number[]} chosen   Chosen IDs.
 * @return {Object} { found, chosen, loading }.
 */
function usePosts( postType, search, chosen ) {
	return useSelect(
		( select ) => {
			const core = select( coreStore );
			const fields = { _fields: 'id,title,parent', context: 'view' };
			const queryFound = {
				// Without a search — all pages (the REST limit is 100), so the list is not cut at the first twenty.
				per_page: search ? 30 : 100,
				search: search || undefined,
				orderby: search ? 'relevance' : 'title',
				order: 'asc',
				status: 'publish',
				...fields,
			};
			const queryChosen = chosen.length
				? {
						include: chosen,
						per_page: chosen.length,
						status: 'publish',
						...fields,
					}
				: null;
			return {
				found:
					core.getEntityRecords( 'postType', postType, queryFound ) ||
					[],
				chosen: queryChosen
					? core.getEntityRecords(
							'postType',
							postType,
							queryChosen
						) || []
					: [],
				loading: ! core.hasFinishedResolution( 'getEntityRecords', [
					'postType',
					postType,
					queryFound,
				] ),
			};
		},
		[ postType, search, chosen.join( ',' ) ]
	);
}

/** Catalog sections, in the tree order, with the name indented by the level. */
function useSections() {
	return useSelect( ( select ) => {
		const terms =
			select( coreStore ).getEntityRecords( 'taxonomy', 'mem_cat', {
				per_page: 100,
				hide_empty: false,
				_fields: 'id,name,slug,parent,count',
				context: 'view',
			} ) || [];
		const out = [];
		const walk = ( parent, depth ) =>
			terms
				.filter( ( t ) => t.parent === parent )
				.forEach( ( t ) => {
					out.push( {
						id: t.id,
						slug: t.slug,
						name: decodeEntities( t.name ),
						label: '— '.repeat( depth ) + decodeEntities( t.name ),
					} );
					walk( t.id, depth + 1 );
				} );
		walk( 0, 0 );
		// Sections whose parent is not in the list (should not happen) — at the end.
		terms
			.filter( ( t ) => ! out.some( ( o ) => o.id === t.id ) )
			.forEach( ( t ) =>
				out.push( {
					id: t.id,
					slug: t.slug,
					name: decodeEntities( t.name ),
					label: decodeEntities( t.name ),
				} )
			);
		return out;
	}, [] );
}

// One post (page or product) with search. Value — post ID (number) or a page path left from earlier.
function PostPicker( { postType, label, empty, value, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const id = /^\d+$/.test( String( value || '' ) ) ? Number( value ) : 0;
	const { found, chosen, loading } = usePosts(
		postType,
		search,
		id ? [ id ] : []
	);
	const options = useMemo( () => {
		const all = [ ...chosen, ...found ].filter(
			( r, i, list ) => list.findIndex( ( x ) => x.id === r.id ) === i
		);
		const list = all.map( ( r ) => ( {
			value: String( r.id ),
			label: titleOf( r ),
		} ) );
		if ( value && ! id ) {
			list.unshift( { value: String( value ), label: String( value ) } );
		}
		return list;
	}, [ found, chosen, value, id ] );
	const current = value
		? options.find( ( o ) => o.value === String( value ) )?.label ||
			String( value )
		: '';
	return (
		<div className="memorial-plugin-post-picker">
			<ComboboxControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				help={
					empty
						? sprintf(
								/* translators: %s: what an empty choice means. */
								__( 'Not chosen — %s.', 'memorial-plugin' ),
								empty
							)
						: undefined
				}
				value={ value ? String( value ) : null }
				options={ options }
				isLoading={ loading }
				onFilterValueChange={ setSearch }
				onChange={ ( v ) => onChange( v || '' ) }
			/>
			{ !! value && (
				<p className="memorial-plugin-post-picker__chosen">
					<span>{ current }</span>
					<Button
						variant="link"
						isDestructive
						onClick={ () => onChange( '' ) }
					>
						{ __( 'Remove', 'memorial-plugin' ) }
					</Button>
				</p>
			) }
		</div>
	);
}

// Several pages in a chosen order. Value — IDs, comma separated (old page paths are kept as they are).
function PagesPicker( { label, value, onChange } ) {
	const list = ids( value );
	const numeric = list.filter( ( v ) => /^\d+$/.test( v ) ).map( Number );
	const { chosen } = usePosts( 'page', '', numeric );
	const name = ( v ) => {
		const r = chosen.find( ( x ) => String( x.id ) === v );
		return r ? titleOf( r ) : v;
	};
	const set = ( next ) => onChange( next.join( ',' ) );
	const move = ( i, d ) => {
		const next = [ ...list ];
		[ next[ i ], next[ i + d ] ] = [ next[ i + d ], next[ i ] ];
		set( next );
	};
	return (
		<div className="memorial-plugin-pages-picker">
			<p className="memorial-plugin-pages-picker__label">{ label }</p>
			{ list.length > 0 && (
				<ol>
					{ list.map( ( v, i ) => (
						<li key={ v }>
							<span>{ name( v ) }</span>
							<Button
								size="small"
								icon="arrow-up-alt2"
								label={ __( 'Move up', 'memorial-plugin' ) }
								disabled={ i === 0 }
								onClick={ () => move( i, -1 ) }
							/>
							<Button
								size="small"
								icon="arrow-down-alt2"
								label={ __( 'Move down', 'memorial-plugin' ) }
								disabled={ i === list.length - 1 }
								onClick={ () => move( i, 1 ) }
							/>
							<Button
								size="small"
								variant="link"
								isDestructive
								onClick={ () =>
									set( list.filter( ( x ) => x !== v ) )
								}
							>
								{ __( 'Remove', 'memorial-plugin' ) }
							</Button>
						</li>
					) ) }
				</ol>
			) }
			<PostPicker
				postType="page"
				label={ __( 'Add a page', 'memorial-plugin' ) }
				value=""
				onChange={ ( v ) =>
					v && ! list.includes( v ) && set( [ ...list, v ] )
				}
			/>
		</div>
	);
}

// One catalog section. Value — slug (attribute “cat”) or term ID (attribute “term”).
function SectionPicker( { label, empty, value, byId, onChange } ) {
	const sections = useSections();
	const current = sections.find( ( s ) =>
		byId
			? s.id === Number( value )
			: s.slug === value ||
				s.name.toLowerCase() === String( value || '' ).toLowerCase() ||
				String( s.id ) === String( value )
	);
	const options = sections.map( ( s ) => ( {
		value: byId ? String( s.id ) : s.slug,
		label: s.label,
	} ) );
	if ( value && ! current && ! byId ) {
		options.unshift( { value: String( value ), label: String( value ) } );
	}
	let selected = null;
	if ( current ) {
		selected = byId ? String( current.id ) : current.slug;
	} else if ( value && ! byId ) {
		selected = String( value );
	}
	// A plain list: the choice is changed by choosing another line (no typing, no clearing first).
	return (
		<SelectControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			value={ selected || '' }
			options={ [
				{
					value: '',
					label: empty
						? sprintf(
								/* translators: %s: what an empty choice means. */
								__( '— %s —', 'memorial-plugin' ),
								empty
							)
						: __( '— not chosen —', 'memorial-plugin' ),
				},
				...options,
			] }
			onChange={ ( v ) => {
				if ( byId ) {
					onChange( v ? Number( v ) : 0 );
				} else {
					onChange( v || '' );
				}
			} }
		/>
	);
}

// Several catalog sections. Value — slugs, comma separated.
function SectionsPicker( { label, value, onChange } ) {
	const sections = useSections();
	const nameOf = ( v ) =>
		sections.find(
			( s ) =>
				s.slug === v ||
				s.name.toLowerCase() === String( v ).toLowerCase()
		)?.name || v;
	return (
		<FormTokenField
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			value={ ids( value ).map( nameOf ) }
			suggestions={ sections.map( ( s ) => s.name ) }
			__experimentalExpandOnFocus
			onChange={ ( tokens ) =>
				onChange(
					tokens
						.map( ( t ) => {
							const name = typeof t === 'string' ? t : t.value;
							return (
								sections.find( ( s ) => s.name === name )
									?.slug || name
							);
						} )
						.join( ',' )
				)
			}
		/>
	);
}

// The chooser of one attribute.
function Picker( { picker, value, onChange } ) {
	switch ( picker.kind ) {
		case 'address':
			return (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ picker.label }
					value={ String( value || 0 ) }
					options={
						ADDRESSES.length
							? ADDRESSES.map( ( a ) => ( {
									value: String( a.value ),
									label: a.label,
								} ) )
							: [
									{
										value: '0',
										label: __(
											'No addresses in Memorial → Contacts',
											'memorial-plugin'
										),
									},
								]
					}
					onChange={ ( v ) => onChange( Number( v ) || 0 ) }
				/>
			);
		case 'cat':
			return (
				<SectionPicker
					{ ...picker }
					value={ value }
					onChange={ onChange }
				/>
			);
		case 'cat-id':
			return (
				<SectionPicker
					{ ...picker }
					byId
					value={ value }
					onChange={ onChange }
				/>
			);
		case 'cats':
			return (
				<SectionsPicker
					{ ...picker }
					value={ value }
					onChange={ onChange }
				/>
			);
		case 'product':
			return (
				<PostPicker
					{ ...picker }
					postType="mem_product"
					value={ value || '' }
					onChange={ ( v ) => onChange( Number( v ) || 0 ) }
				/>
			);
		case 'page':
			return (
				<PostPicker
					{ ...picker }
					postType="page"
					value={ value || '' }
					onChange={ onChange }
				/>
			);
		case 'pages':
			return (
				<PagesPicker
					{ ...picker }
					value={ value }
					onChange={ onChange }
				/>
			);
	}
	return null;
}

// All choosers of a block; the value is cast to the type of the attribute.
function Pickers( { name, attributes, setAttributes } ) {
	const pickers = PICKERS[ name ] || {};
	const type = getBlockType( name )?.attributes || {};
	return Object.entries( pickers ).map( ( [ attr, picker ] ) => (
		<Picker
			key={ attr }
			picker={ picker }
			value={ attributes[ attr ] }
			onChange={ ( v ) => {
				let next = v;
				if ( type[ attr ]?.type === 'integer' ) {
					next = Number( v ) || 0;
				} else if ( type[ attr ]?.type === 'string' ) {
					next = v === null || v === undefined ? '' : String( v );
				}
				setAttributes( { [ attr ]: next } );
			} }
		/>
	) );
}

// Plain settings of a block (texts, numbers, switches) as fields — the same values as in the sidebar.
function Texts( { name, attributes, setAttributes } ) {
	return Object.entries( TEXTS[ name ] || {} ).map( ( [ attr, def ] ) => {
		const value = attributes[ attr ];
		if ( def.type === 'boolean' ) {
			return (
				<ToggleControl
					key={ attr }
					__nextHasNoMarginBottom
					label={ def.label }
					checked={ !! value }
					onChange={ ( v ) => setAttributes( { [ attr ]: v } ) }
				/>
			);
		}
		if ( def.options ) {
			return (
				<SelectControl
					key={ attr }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ def.label }
					value={ String( value ?? '' ) }
					options={ def.options.map( ( o ) => ( {
						value: o,
						label: o === '' ? '—' : o,
					} ) ) }
					onChange={ ( v ) => setAttributes( { [ attr ]: v } ) }
				/>
			);
		}
		const numeric = def.type !== 'string';
		return (
			<TextControl
				key={ attr }
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ def.label }
				type={ numeric ? 'number' : 'text' }
				value={ value ?? '' }
				onChange={ ( v ) =>
					setAttributes( {
						[ attr ]: numeric ? Number( v ) || 0 : v,
					} )
				}
			/>
		);
	} );
}

// Does the block find its data on this page (memorial-plugin/v1/block-source)?
function useSource( name, attributes ) {
	const { postId, postType } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor?.getCurrentPostId?.() || 0,
			postType: editor?.getCurrentPostType?.() || '',
		};
	}, [] );
	const pickers = PICKERS[ name ] || {};
	const chosen = Object.keys( pickers ).some( ( attr ) => {
		const v = attributes[ attr ];
		return v !== undefined && v !== null && v !== '' && v !== 0;
	} );
	const applies =
		SOURCE.includes( name ) &&
		! chosen &&
		!! postId &&
		! [ 'wp_template', 'wp_template_part', 'wp_block' ].includes(
			postType
		);
	const key = name + ':' + postId;
	const [ source, setSource ] = useState( sourceCache[ key ] );
	useEffect( () => {
		if ( ! applies || sourceCache[ key ] ) {
			return;
		}
		apiFetch( {
			path: addQueryArgs( '/memorial-plugin/v1/block-source', {
				block: name,
				post: postId,
			} ),
		} )
			.then( ( r ) => {
				sourceCache[ key ] = r;
				setSource( r );
			} )
			.catch( () => {
				sourceCache[ key ] = { found: true, items: [] };
				setSource( sourceCache[ key ] );
			} );
	}, [ applies, key, name, postId ] );
	return { applies, chosen, source: applies ? source : null };
}

// Text under the choosers: what the block shows now when nothing is chosen.
function AutoNote( { source } ) {
	if ( ! source?.items?.length ) {
		return null;
	}
	return (
		<p className="memorial-plugin-auto-note">
			{ sprintf(
				/* translators: %s: list of sections or a product. */
				__( 'Found on this page: %s.', 'memorial-plugin' ),
				source.items.join( ', ' )
			) }
		</p>
	);
}

// In place of the block: the chooser (nothing found) or the folded line.
function Stand( { name, children, className } ) {
	const props = useBlockProps( {
		className: 'memorial-plugin-stand ' + ( className || '' ),
	} );
	const type = getBlockType( name );
	return (
		<div { ...props }>
			<Placeholder
				icon={ <BlockIcon icon={ type?.icon?.src } /> }
				label={ type?.title }
				className="memorial-plugin-stand__in"
			>
				{ children }
			</Placeholder>
		</div>
	);
}

const withPluginControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { name, attributes, setAttributes, isSelected } = props;
		const [ choosing, setChoosing ] = useState( false );
		const own = name.startsWith( PREFIX );
		const pickers = own ? PICKERS[ name ] : null;
		const texts = own ? TEXTS[ name ] : null;
		const required = own ? REQUIRED[ name ] : '';
		const missing = !! required && ! attributes?.[ required ];
		// The fields stay open while the first value is being typed.
		useEffect( () => {
			if ( missing ) {
				setChoosing( true );
			}
		}, [ missing ] );
		const { applies, source } = useSource(
			own ? name : '',
			attributes || {}
		);
		if ( ! own ) {
			return <BlockEdit { ...props } />;
		}
		const folded = !! attributes.editorCollapsed;
		const fold = (
			<BlockControls group="other">
				<ToolbarGroup>
					<ToolbarButton
						icon={ folded ? 'arrow-down-alt2' : 'arrow-up-alt2' }
						label={
							folded
								? __( 'Unfold the block', 'memorial-plugin' )
								: __(
										'Fold the block (only in the editor)',
										'memorial-plugin'
									)
						}
						onClick={ () =>
							setAttributes( { editorCollapsed: ! folded } )
						}
					/>
					{ ( pickers || texts ) && (
						<ToolbarButton
							icon="admin-settings"
							label={ __(
								'Set up the block: texts and what it shows',
								'memorial-plugin'
							) }
							isPressed={ choosing }
							onClick={ () => setChoosing( ! choosing ) }
						/>
					) }
				</ToolbarGroup>
			</BlockControls>
		);
		const panel = pickers && (
			<InspectorControls>
				<PanelBody
					title={ __( 'What to show', 'memorial-plugin' ) }
					initialOpen
				>
					<Pickers { ...props } />
					<AutoNote source={ source } />
				</PanelBody>
			</InspectorControls>
		);
		let body;
		if ( folded ) {
			body = (
				<Stand name={ name } className="is-folded">
					<Button
						variant="secondary"
						onClick={ () =>
							setAttributes( { editorCollapsed: false } )
						}
					>
						{ __( 'Unfold', 'memorial-plugin' ) }
					</Button>
				</Stand>
			);
		} else if ( ( choosing || missing ) && ( pickers || texts ) ) {
			body = (
				<Stand name={ name } className="is-choosing">
					<div className="memorial-plugin-stand__pickers">
						<Texts { ...props } />
						<Pickers { ...props } />
						<AutoNote source={ source } />
						<div>
							<Button
								variant="primary"
								disabled={ missing }
								onClick={ () => setChoosing( false ) }
							>
								{ __( 'Done', 'memorial-plugin' ) }
							</Button>
						</div>
					</div>
				</Stand>
			);
		} else if ( applies && ! source ) {
			body = (
				<Stand name={ name }>
					<Spinner />
				</Stand>
			);
		} else if ( applies && source && ! source.found ) {
			body = (
				<Stand name={ name } className="is-choosing">
					<p className="memorial-plugin-stand__text">
						{ __(
							'The block found nothing to show on this page by itself. Choose what it should show — later the choice is changed with the ⚙ button on the block toolbar or in the block settings on the right (“What to show”).',
							'memorial-plugin'
						) }
					</p>
					<div className="memorial-plugin-stand__pickers">
						<Pickers { ...props } />
					</div>
				</Stand>
			);
		} else {
			body = <BlockEdit { ...props } />;
		}
		return (
			<>
				{ fold }
				{ isSelected !== false && panel }
				{ body }
			</>
		);
	},
	'withMemorialPluginControls'
);

addFilter(
	'editor.BlockEdit',
	'memorial-plugin/block-controls',
	withPluginControls
);

/*
 * A safety net for the server filter (memorial_plugin_block_no_auto_controls): attributes with a chooser
 * and the fold flag never get a generated text field.
 */
addFilter(
	'blocks.registerBlockType',
	'memorial-plugin/block-controls/no-auto-fields',
	( settings, name ) => {
		if ( ! name.startsWith( PREFIX ) || ! settings.attributes ) {
			return settings;
		}
		const skip = [
			...Object.keys( PICKERS[ name ] || {} ),
			'editorCollapsed',
		];
		const attributes = { ...settings.attributes };
		skip.forEach( ( attr ) => {
			if ( attributes[ attr ]?.autoGenerateControl ) {
				attributes[ attr ] = {
					...attributes[ attr ],
					autoGenerateControl: false,
				};
			}
		} );
		return { ...settings, attributes };
	}
);
