/**
 * Page “Questions and answers” → sidebar “Question sections”: the order of the sections, the button text,
 * the heading, the anchor and the link text of each one (meta _mem_faq_sections, content/faq.php).
 * The sections come from the saved page: a section added to the page appears here after saving.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Flex,
	FlexBlock,
	Modal,
	Notice,
	TextControl,
} from '@wordpress/components';
import Hint from '../shared/hint';

/**
 * Saved settings → { order, items }.
 *
 * @param {string} raw JSON.
 * @return {Object} Settings.
 */
function parse( raw ) {
	try {
		const v = JSON.parse( raw || '{}' );
		return {
			order: Array.isArray( v.order ) ? v.order : [],
			items: v.items && typeof v.items === 'object' ? v.items : {},
		};
	} catch ( e ) {
		return { order: [], items: {} };
	}
}

/**
 * An anchor as the site keeps it: latin letters, digits and hyphens.
 *
 * @param {string} v Text.
 * @return {string} Anchor.
 */
const cleanAnchor = ( v ) =>
	v
		.toLowerCase()
		.replace( /[^a-z0-9_-]+/g, '-' )
		.replace( /^-+/, '' )
		.slice( 0, 80 );

// One section: the fields, ↑ ↓.
function Row( { s, item, set, up, down, dupAnchor } ) {
	const own = s.kind === 'own';
	const field = ( key, label, opts = {} ) => (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			placeholder={ s.defaults[ key ] }
			value={ item[ key ] || '' }
			onChange={ ( v ) =>
				set( key, key === 'anchor' ? cleanAnchor( v ) : v )
			}
			{ ...opts }
		/>
	);
	const label = item.label || s.defaults.label;
	return (
		<div className="memorial-plugin-faq-sections__row">
			<Flex align="center" gap={ 2 }>
				<FlexBlock>
					<strong>{ label }</strong>{ ' ' }
					<span className="memorial-plugin-faq-sections__meta">
						{ own
							? sprintf(
									/* translators: %d: number of questions. */
									__(
										'own section on this page, questions: %d',
										'memorial-plugin'
									),
									s.count
								)
							: sprintf(
									/* translators: 1: page title, 2: number of questions. */
									__(
										'page “%1$s”, questions: %2$d',
										'memorial-plugin'
									),
									s.page,
									s.count
								) }
					</span>
				</FlexBlock>
				<Button
					icon="arrow-up-alt2"
					label={ __( 'Move up', 'memorial-plugin' ) }
					onClick={ up }
					disabled={ ! up }
					size="compact"
				/>
				<Button
					icon="arrow-down-alt2"
					label={ __( 'Move down', 'memorial-plugin' ) }
					onClick={ down }
					disabled={ ! down }
					size="compact"
				/>
			</Flex>
			<div className="memorial-plugin-faq-sections__fields">
				{ field( 'label', __( 'Button', 'memorial-plugin' ), {
					help:
						label.length > 28
							? __(
									'Long — a short word or two reads easier.',
									'memorial-plugin'
								)
							: '',
				} ) }
				{ own ? (
					<p className="memorial-plugin-faq-sections__note">
						{ __(
							'The heading and the anchor are those of the heading of the section on this page (Advanced → HTML anchor).',
							'memorial-plugin'
						) }
					</p>
				) : (
					<>
						{ field( 'title', __( 'Heading', 'memorial-plugin' ) ) }
						{ field( 'anchor', __( 'Anchor', 'memorial-plugin' ), {
							help: dupAnchor
								? __(
										'This anchor is already used — the site will add a number.',
										'memorial-plugin'
									)
								: '',
						} ) }
						{ field(
							'more',
							__( 'Link to the page', 'memorial-plugin' )
						) }
					</>
				) }
			</div>
		</div>
	);
}

export default function FaqSections( { postId, value, onChange } ) {
	const [ open, setOpen ] = useState( false );
	const [ sections, setSections ] = useState( null );
	const [ error, setError ] = useState( '' );
	const settings = parse( value );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		apiFetch( {
			path: `/memorial-plugin/v1/faq/sections?hub=${ postId }`,
		} )
			.then( setSections )
			.catch( ( e ) => setError( e.message ) );
	}, [ open, postId ] );

	const save = ( next ) => onChange( JSON.stringify( next ) );
	const ordered = sections
		? [
				...settings.order
					.map( ( k ) => sections.find( ( s ) => s.key === k ) )
					.filter( Boolean ),
				...sections.filter(
					( s ) => ! settings.order.includes( s.key )
				),
			]
		: [];
	const move = ( i, d ) => {
		const keys = ordered.map( ( s ) => s.key );
		[ keys[ i ], keys[ i + d ] ] = [ keys[ i + d ], keys[ i ] ];
		save( { ...settings, order: keys } );
	};
	const setField = ( key, f, v ) => {
		const item = { ...( settings.items[ key ] || {} ), [ f ]: v };
		if ( ! v ) {
			delete item[ f ];
		}
		save( { ...settings, items: { ...settings.items, [ key ]: item } } );
	};
	const anchorOf = ( s ) =>
		s.kind === 'own'
			? s.defaults.anchor
			: settings.items[ s.key ]?.anchor || s.defaults.anchor;

	return (
		<>
			<p>
				{ __(
					'Button, heading, anchor, link text and the order of the sections.',
					'memorial-plugin'
				) }{ ' ' }
				<Hint
					text={ __(
						'Your own section: on this page add a Heading (H2) and right under it “Details” blocks — a question and its answer each. The section is shown among the others, is found by the search and is marked up for search engines (FAQPage). Sections of other pages show the first paragraph of an answer and “Read more”.',
						'memorial-plugin'
					) }
				/>
			</p>
			<Button variant="secondary" onClick={ () => setOpen( true ) }>
				{ __( 'Set up the sections', 'memorial-plugin' ) }
			</Button>
			{ open && (
				<Modal
					title={ __( 'Question sections', 'memorial-plugin' ) }
					onRequestClose={ () => setOpen( false ) }
					size="medium"
					className="memorial-plugin-faq-sections"
				>
					<Flex direction="column" gap={ 4 } justify="flex-start">
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Changes are kept with the page: press “Save” of the page. A section just added to the page appears here after saving. Empty fields — the gray text.',
								'memorial-plugin'
							) }
						</Notice>
						{ error && (
							<Notice status="error" isDismissible={ false }>
								{ error }
							</Notice>
						) }
						{ ! sections && ! error && (
							<p>{ __( 'Loading…', 'memorial-plugin' ) }</p>
						) }
						{ ordered.map( ( s, i ) => (
							<Row
								key={ s.key }
								s={ s }
								item={ settings.items[ s.key ] || {} }
								set={ ( f, v ) => setField( s.key, f, v ) }
								up={ i > 0 ? () => move( i, -1 ) : null }
								down={
									i < ordered.length - 1
										? () => move( i, 1 )
										: null
								}
								dupAnchor={
									ordered.filter(
										( o ) => anchorOf( o ) === anchorOf( s )
									).length > 1
								}
							/>
						) ) }
						<Flex justify="flex-end">
							<Button
								variant="primary"
								onClick={ () => setOpen( false ) }
							>
								{ __( 'Done', 'memorial-plugin' ) }
							</Button>
						</Flex>
					</Flex>
				</Modal>
			) }
		</>
	);
}
