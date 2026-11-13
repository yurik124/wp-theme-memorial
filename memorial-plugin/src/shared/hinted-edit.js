/**
 * A short field of DataForm whose explanation is behind the round “?” next to its label, not under it:
 * fields standing in one row stay level (text under one of them would push it down).
 */
import { SelectControl, TextControl } from '@wordpress/components';
import Hint from './hint';

export default function HintedEdit( { data, field, onChange } ) {
	const label = (
		<span className="memorial-plugin-hinted">
			{ field.label }
			<Hint text={ field.description } />
		</span>
	);
	const value = data[ field.id ];
	if ( field.elements ) {
		return (
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				value={ String( value ?? '' ) }
				options={ field.elements.map( ( e ) => ( {
					value: String( e.value ),
					label: e.label,
				} ) ) }
				onChange={ ( v ) => onChange( { [ field.id ]: v } ) }
			/>
		);
	}
	const numeric = field.type === 'integer';
	return (
		<TextControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			type={ numeric ? 'number' : 'text' }
			placeholder={ field.placeholder }
			value={ value ?? '' }
			onChange={ ( v ) =>
				onChange( {
					[ field.id ]: numeric && v !== '' ? Number( v ) : v,
				} )
			}
		/>
	);
}
