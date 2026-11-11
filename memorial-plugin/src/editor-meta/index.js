/**
 * Sidebar panels of the block editor for products, works and pages (instead of the classic meta boxes).
 * The meta is registered for REST in admin/editor-meta.php and is saved together with the post.
 */
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import Photos from '../shared/photos';
import './style.scss';

const settings = window.memorialPluginEditorMeta || {};

/**
 * Meta of the current post: [meta, set(key, value)].
 *
 * @param {string} postType Post type.
 * @return {Array} Meta and setter.
 */
function useMeta( postType ) {
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
	const set = ( key, value ) => setMeta( { ...meta, [ key ]: value } );
	return [ meta || {}, set ];
}

function Money( { label, value, onChange, help } ) {
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			type="number"
			label={ label }
			help={ help }
			min={ 0 }
			step={ 1 }
			value={ value || '' }
			onChange={ ( v ) => onChange( parseInt( v, 10 ) || 0 ) }
		/>
	);
}

function ProductPanel() {
	const [ meta, set ] = useMeta( 'mem_product' );
	return (
		<>
			<PluginDocumentSettingPanel
				name="memorial-plugin-product-price"
				title={ __( 'Price', 'memorial-plugin' ) }
			>
				<Flex direction="column" gap={ 4 }>
					<Money
						label={ __( 'Price, ₽', 'memorial-plugin' ) }
						help={ __(
							'0 — “price on request”.',
							'memorial-plugin'
						) }
						value={ meta._mem_price }
						onChange={ ( v ) => set( '_mem_price', v ) }
					/>
					<Money
						label={ __( 'Old price, ₽', 'memorial-plugin' ) }
						value={ meta._mem_price_old }
						onChange={ ( v ) => set( '_mem_price_old', v ) }
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Unit (optional)', 'memorial-plugin' ) }
						placeholder={ __(
							'for example: per metre',
							'memorial-plugin'
						) }
						value={ meta._mem_unit || '' }
						onChange={ ( v ) => set( '_mem_unit', v ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show “from”', 'memorial-plugin' ) }
						checked={ meta._mem_price_from === '1' }
						onChange={ ( v ) =>
							set( '_mem_price_from', v ? '1' : '' )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Made to order', 'memorial-plugin' ) }
						checked={ meta._mem_on_order === '1' }
						onChange={ ( v ) =>
							set( '_mem_on_order', v ? '1' : '' )
						}
					/>
				</Flex>
			</PluginDocumentSettingPanel>
			<PluginDocumentSettingPanel
				name="memorial-plugin-product-card"
				title={ __( 'Card', 'memorial-plugin' ) }
			>
				<Flex direction="column" gap={ 4 }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Line under the title',
							'memorial-plugin'
						) }
						help={ __(
							'A short line in the card: size, material, colour.',
							'memorial-plugin'
						) }
						placeholder={ __(
							'for example: height 90 cm · 700 × 2050 mm',
							'memorial-plugin'
						) }
						value={ meta._mem_sub || '' }
						onChange={ ( v ) => set( '_mem_sub', v ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Specifications', 'memorial-plugin' ) }
						help={ __(
							'One per line, “Name: value”.',
							'memorial-plugin'
						) }
						placeholder={
							'Ширина × высота: 700 × 2050 мм\nПрофиль: 40 × 20 мм'
						}
						rows={ 4 }
						value={ meta._mem_specs || '' }
						onChange={ ( v ) => set( '_mem_specs', v ) }
					/>
					<Photos
						label={ __(
							'More photos (after the featured image)',
							'memorial-plugin'
						) }
						value={ meta._mem_gallery || '' }
						onChange={ ( v ) => set( '_mem_gallery', v ) }
					/>
				</Flex>
			</PluginDocumentSettingPanel>
		</>
	);
}

function Rows( { rows, onChange } ) {
	const list = Array.isArray( rows ) ? rows : [];
	const total = list.reduce(
		( s, r ) => s + ( parseInt( r[ 1 ], 10 ) || 0 ),
		0
	);
	const update = ( i, k, v ) =>
		onChange(
			list.map( ( r, j ) =>
				j === i ? ( k ? [ r[ 0 ], v ] : [ v, r[ 1 ] ] ) : r
			)
		);
	return (
		<fieldset className="memorial-plugin-rows">
			<legend>
				{ __( 'What was done and the price', 'memorial-plugin' ) }
			</legend>
			{ list.map( ( r, i ) => (
				<Flex key={ i } align="flex-end">
					<FlexBlock>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ sprintf(
								/* translators: %d: row number. */
								__( 'Work %d', 'memorial-plugin' ),
								i + 1
							) }
							value={ r[ 0 ] }
							onChange={ ( v ) => update( i, 0, v ) }
						/>
					</FlexBlock>
					<FlexItem>
						<Money
							label={ __( '₽', 'memorial-plugin' ) }
							value={ r[ 1 ] }
							onChange={ ( v ) => update( i, 1, v ) }
						/>
					</FlexItem>
					<FlexItem>
						<Button
							icon="no-alt"
							label={ __( 'Remove row', 'memorial-plugin' ) }
							onClick={ () =>
								onChange( list.filter( ( x, j ) => j !== i ) )
							}
						/>
					</FlexItem>
				</Flex>
			) ) }
			<Flex>
				<Button
					variant="secondary"
					onClick={ () => onChange( [ ...list, [ '', 0 ] ] ) }
				>
					{ __( 'Add row', 'memorial-plugin' ) }
				</Button>
				<span>
					{ sprintf(
						/* translators: %s: total price. */
						__( 'Total: %s ₽', 'memorial-plugin' ),
						total.toLocaleString( 'ru-RU' )
					) }
				</span>
			</Flex>
		</fieldset>
	);
}

function WorkPanel() {
	const [ meta, set ] = useMeta( 'mem_work' );
	return (
		<PluginDocumentSettingPanel
			name="memorial-plugin-work"
			title={ __( 'Photos, size and prices', 'memorial-plugin' ) }
		>
			<Flex direction="column" gap={ 4 }>
				<Photos
					label={ __(
						'Photos — the first one is the main one',
						'memorial-plugin'
					) }
					value={ meta._mem_photos || '' }
					onChange={ ( v ) => set( '_mem_photos', v ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Plot size', 'memorial-plugin' ) }
					placeholder={ __(
						'for example: 2.5 × 2 m',
						'memorial-plugin'
					) }
					value={ meta._mem_size || '' }
					onChange={ ( v ) => set( '_mem_size', v ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="number"
					label={ __( 'Year', 'memorial-plugin' ) }
					help={ __(
						'Tells the visitor that the prices are as of that year. Empty — the year is not shown.',
						'memorial-plugin'
					) }
					min={ 1990 }
					max={ settings.maxYear }
					value={ meta._mem_year || '' }
					onChange={ ( v ) =>
						set( '_mem_year', parseInt( v, 10 ) || 0 )
					}
				/>
				<Rows
					rows={ meta._mem_rows }
					onChange={ ( v ) => set( '_mem_rows', v ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show in examples', 'memorial-plugin' ) }
					help={ __(
						'In the “Examples of our works” cards on other pages.',
						'memorial-plugin'
					) }
					checked={ meta._mem_example === '1' }
					onChange={ ( v ) => set( '_mem_example', v ? '1' : '' ) }
				/>
			</Flex>
		</PluginDocumentSettingPanel>
	);
}

function PagePanel() {
	const [ meta, set ] = useMeta( 'page' );
	return (
		<>
			<PluginDocumentSettingPanel
				name="memorial-plugin-page-products"
				title={ __( 'Products on the page', 'memorial-plugin' ) }
			>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Catalog category', 'memorial-plugin' ) }
					value={ meta._mem_products || '' }
					options={ [
						{
							value: '',
							label: __( '— do not show —', 'memorial-plugin' ),
						},
						{
							value: 'all',
							label: __(
								'All categories (tiles)',
								'memorial-plugin'
							),
						},
						...( settings.cats || [] ),
					] }
					onChange={ ( v ) => set( '_mem_products', v ) }
					help={ __(
						'The products appear below the page text. To keep part of the text above them, put a “More” block in the text.',
						'memorial-plugin'
					) }
				/>
			</PluginDocumentSettingPanel>
			{ settings.guide && (
				<PluginDocumentSettingPanel
					name="memorial-plugin-page-guide"
					title={ __( 'Good to know', 'memorial-plugin' ) }
				>
					<Flex direction="column" gap={ 4 }>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label={ __(
								'Question the page answers',
								'memorial-plugin'
							) }
							placeholder={ __(
								'For example: How to get the funeral allowance?',
								'memorial-plugin'
							) }
							value={ meta._mem_q || '' }
							onChange={ ( v ) => set( '_mem_q', v ) }
						/>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __(
								'Short answer — 1–2 sentences',
								'memorial-plugin'
							) }
							help={ __(
								'Shown on the page card in “Good to know” and in search. Empty — the page title and first paragraph.',
								'memorial-plugin'
							) }
							rows={ 3 }
							value={ meta._mem_qa || '' }
							onChange={ ( v ) => set( '_mem_qa', v ) }
						/>
					</Flex>
				</PluginDocumentSettingPanel>
			) }
		</>
	);
}

function Panels() {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	if ( postType === 'mem_product' ) {
		return <ProductPanel />;
	}
	if ( postType === 'mem_work' ) {
		return <WorkPanel />;
	}
	if ( postType === 'page' ) {
		return <PagePanel />;
	}
	return null;
}

registerPlugin( 'memorial-plugin-editor-meta', { render: Panels } );
