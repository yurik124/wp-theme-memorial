/**
 * Command Palette (Ctrl/Cmd + K): the plugin’s screens and main actions. Loaded on every admin page and
 * in the editors (admin/commands.php); the commands a user may not use are not registered for them.
 */
import { dispatch, select, subscribe } from '@wordpress/data';
import { store as commandsStore } from '@wordpress/commands';
import { __, sprintf } from '@wordpress/i18n';
import {
	category,
	commentAuthorAvatar,
	download as downloadIcon,
	gallery,
	inbox,
	list,
	pages,
	people,
	search,
	settings,
	starFilled,
	tag,
	blockDefault,
	chartBar,
	mobile,
} from '@wordpress/icons';
import { runAbility, download } from '../shared/abilities';
import toast from '../shared/toast';
import './style.scss';

const data = window.memorialPluginCommands || { screens: [], can: {} };

const ICONS = {
	contacts: mobile,
	prices: tag,
	reviews: starFilled,
	analytics: chartBar,
	features: settings,
	leads: inbox,
	bookrev: commentAuthorAvatar,
	products: category,
	types: list,
	works: gallery,
	'work-types': list,
	search,
	services: pages,
	default: people,
};

const notice = ( status, text ) => toast( text, status );

const go = ( url ) => () => {
	document.location.href = url;
};

const commands = data.screens.map( ( s ) => ( {
	name: 'memorial-plugin/open-' + s.id,
	label: s.label,
	searchLabel: s.keywords ? s.label + ' ' + s.keywords : s.label,
	icon: ICONS[ s.icon ] || ICONS.default,
	callback: ( { close } ) => {
		close();
		go( s.url )();
	},
} ) );

if ( data.can.export ) {
	commands.push( {
		name: 'memorial-plugin/export-catalog',
		label: __( 'Export the catalog to CSV', 'memorial-plugin' ),
		searchLabel: __(
			'Export the catalog to CSV products prices Excel',
			'memorial-plugin'
		),
		icon: downloadIcon,
		callback: async ( { close } ) => {
			close();
			try {
				const res = await runAbility(
					'memorial-plugin/export-catalog'
				);
				download( res.filename, res.csv );
				notice(
					'success',
					sprintf(
						/* translators: %d: number of products. */
						__(
							'Catalog exported: %d products.',
							'memorial-plugin'
						),
						res.count
					)
				);
			} catch ( e ) {
				notice( 'error', e.message );
			}
		},
	} );
}

if ( data.can.leads ) {
	commands.push( {
		name: 'memorial-plugin/new-leads',
		label: __( 'Who to call back', 'memorial-plugin' ),
		searchLabel: __(
			'Who to call back new call back requests leads',
			'memorial-plugin'
		),
		icon: mobile,
		callback: async ( { close } ) => {
			close();
			try {
				const list2 = await runAbility( 'memorial-plugin/list-leads', {
					status: 'new',
					limit: 50,
				} );
				notice(
					list2.length ? 'warning' : 'success',
					list2.length
						? sprintf(
								/* translators: %d: number of requests. */
								__(
									'Not called back yet: %d. Opening the list…',
									'memorial-plugin'
								),
								list2.length
							)
						: __(
								'Everyone has been called back.',
								'memorial-plugin'
							)
				);
				if ( list2.length && data.leadsUrl ) {
					window.setTimeout( go( data.leadsUrl ), 800 );
				}
			} catch ( e ) {
				notice( 'error', e.message );
			}
		},
	} );
}

commands.forEach( ( c ) => dispatch( commandsStore ).registerCommand( c ) );

// In the post editor: convert the shortcodes of the current post to blocks (when it has them).
if ( data.can.convert ) {
	let registered = false;
	const unsubscribe = subscribe( () => {
		const editor = select( 'core/editor' );
		const id = editor?.getCurrentPostId?.();
		if ( ! id || registered ) {
			return;
		}
		registered = true;
		unsubscribe();
		const content = editor.getEditedPostContent?.() || '';
		if ( ! /\[mem_[a-z_]+/.test( content ) ) {
			return;
		}
		dispatch( commandsStore ).registerCommand( {
			name: 'memorial-plugin/convert-this',
			label: __(
				'Convert the old codes of this page to blocks',
				'memorial-plugin'
			),
			icon: blockDefault,
			callback: async ( { close } ) => {
				close();
				if ( editor.isEditedPostDirty() ) {
					notice(
						'warning',
						__( 'Save the page first.', 'memorial-plugin' )
					);
					return;
				}
				try {
					const [ res ] = await runAbility(
						'memorial-plugin/convert-content',
						{
							ids: [ id ],
						}
					);
					if ( ! res?.ok ) {
						throw new Error(
							res?.error ||
								__( 'Could not convert.', 'memorial-plugin' )
						);
					}
					notice(
						'success',
						__(
							'Converted. Reloading the editor…',
							'memorial-plugin'
						)
					);
					window.setTimeout( () => document.location.reload(), 800 );
				} catch ( e ) {
					notice( 'error', e.message );
				}
			},
		} );
	} );
}
