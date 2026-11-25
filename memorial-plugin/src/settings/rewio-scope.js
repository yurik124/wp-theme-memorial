/**
 * Rewio: which branches (objects) and review sites (sources) to show — checkboxes from the
 * Rewio catalog. Stored as in the classic form: objects "1,2", per "1:3.4;2:5", sources "" and tabs.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, CheckboxControl, Notice, Flex } from '@wordpress/components';

const ids = ( s ) =>
	String( s || '' )
		.split( /[\s,;]+/ )
		.map( ( x ) => parseInt( x, 10 ) )
		.filter( Boolean );

function parsePer( s ) {
	const out = {};
	String( s || '' )
		.split( ';' )
		.forEach( ( part ) => {
			const m = part.match( /^\s*(\d+)\s*:\s*([\d.]*)\s*$/ );
			if ( m ) {
				out[ m[ 1 ] ] = m[ 2 ]
					.split( '.' )
					.map( ( x ) => parseInt( x, 10 ) )
					.filter( Boolean );
			}
		} );
	return out;
}

const formatPer = ( per ) =>
	Object.entries( per )
		.map( ( [ id, list ] ) => `${ id }:${ list.join( '.' ) }` )
		.join( ';' );

// “Update the list of branches”: the list again from Rewio (POST /memorial-plugin/v1/rewio/catalog).
function RefreshButton( { busy, onClick } ) {
	return (
		<Button
			variant="secondary"
			isBusy={ busy }
			disabled={ busy }
			onClick={ onClick }
			__next40pxDefaultSize
		>
			{ busy
				? __( 'Updating the list…', 'memorial-plugin' )
				: __( 'Update the list of branches', 'memorial-plugin' ) }
		</Button>
	);
}

export default function RewioScope( { def, value, onChange } ) {
	const val = value || {};
	const [ catalog, setCatalog ] = useState( def.catalog || null );
	const [ error, setError ] = useState( def.error || '' );
	const [ busy, setBusy ] = useState( false );
	const [ done, setDone ] = useState( false );
	const refresh = async () => {
		setBusy( true );
		setDone( false );
		try {
			const res = await apiFetch( {
				path: '/memorial-plugin/v1/rewio/catalog',
				method: 'POST',
			} );
			setCatalog( res );
			setError( '' );
			setDone( true );
		} catch ( e ) {
			setError( e.message );
		}
		setBusy( false );
	};
	if ( ! catalog ) {
		return (
			<Flex direction="column" gap={ 3 } align="flex-start">
				<Notice status="warning" isDismissible={ false }>
					{ error }
				</Notice>
				{ def.refresh && (
					<RefreshButton busy={ busy } onClick={ refresh } />
				) }
			</Flex>
		);
	}
	const { objects, sources, time } = catalog;
	const selected = ids( val.objects );
	const per = parsePer( val.per );
	const global = ids( val.sources );
	const onFor = ( ob ) => {
		const own =
			per[ ob.id ] ||
			( global.length
				? ob.sources.filter( ( s ) => global.includes( s ) )
				: ob.sources );
		return own.length ? own : ob.sources;
	};
	const update = ( patch ) => onChange( { ...val, ...patch } );

	return (
		<Flex
			direction="column"
			gap={ 3 }
			className="memorial-plugin-settings__rewio"
		>
			<p className="memorial-plugin-settings__help">{ def.help }</p>
			{ objects.map( ( ob ) => (
				<div key={ ob.id } className="memorial-plugin-settings__row">
					<CheckboxControl
						__nextHasNoMarginBottom
						label={
							ob.name +
							( ob.count !== null ? ` (${ ob.count })` : '' ) +
							( ob.demo
								? ' — ' +
									__( 'Rewio example', 'memorial-plugin' )
								: '' )
						}
						checked={ selected.includes( ob.id ) }
						onChange={ ( on ) =>
							update( {
								objects: ( on
									? [ ...selected, ob.id ]
									: selected.filter( ( x ) => x !== ob.id )
								).join( ',' ),
							} )
						}
					/>
					<div className="memorial-plugin-settings__sources">
						{ ob.sources.map( ( sid ) => (
							<CheckboxControl
								key={ sid }
								__nextHasNoMarginBottom
								label={ sources[ sid ] || String( sid ) }
								checked={ onFor( ob ).includes( sid ) }
								onChange={ ( on ) => {
									const list = on
										? [ ...onFor( ob ), sid ]
										: onFor( ob ).filter(
												( x ) => x !== sid
											);
									update( {
										per: formatPer( {
											...per,
											[ ob.id ]: list,
										} ),
									} );
								} }
							/>
						) ) }
					</div>
				</div>
			) ) }
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ __( 'Each branch on its own tab', 'memorial-plugin' ) }
				checked={ !! Number( val.tabs ) }
				onChange={ ( on ) => update( { tabs: on ? 1 : 0 } ) }
			/>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ done && (
				<Notice status="success" onRemove={ () => setDone( false ) }>
					{ __(
						'The list of branches is updated. Tick the branches and click “Save”.',
						'memorial-plugin'
					) }
				</Notice>
			) }
			<Flex gap={ 3 } align="center" justify="flex-start" wrap>
				{ def.refresh && (
					<RefreshButton busy={ busy } onClick={ refresh } />
				) }
				<p className="memorial-plugin-settings__help">
					{ __( 'Branch list from Rewio:', 'memorial-plugin' ) }{ ' ' }
					{ time }
				</p>
			</Flex>
		</Flex>
	);
}
