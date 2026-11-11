/**
 * Yes/no field of DataForm as a plain checkbox (clear to read, one click, a real checkbox for assistive tech).
 */
import { CheckboxControl } from '@wordpress/components';

export default function BooleanEdit( { data, field, onChange } ) {
	return (
		<CheckboxControl
			__nextHasNoMarginBottom
			label={ field.label }
			help={ field.description }
			checked={ !! data[ field.id ] }
			onChange={ ( v ) => onChange( { [ field.id ]: v } ) }
		/>
	);
}
