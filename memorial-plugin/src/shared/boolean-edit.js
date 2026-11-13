/**
 * Yes/no field of DataForm as a plain checkbox (clear to read, one click, a real checkbox for assistive tech).
 * A long explanation goes behind the round “?” next to the name; a short one stays under it.
 */
import { CheckboxControl } from '@wordpress/components';
import Hint from './hint';

const HINT_FROM = 70;

export default function BooleanEdit( { data, field, onChange } ) {
	const long = String( field.description || '' ).length > HINT_FROM;
	return (
		<CheckboxControl
			__nextHasNoMarginBottom
			label={
				long ? (
					<span className="memorial-plugin-hinted">
						{ field.label }
						<Hint text={ field.description } />
					</span>
				) : (
					field.label
				)
			}
			help={ long ? undefined : field.description }
			checked={ !! data[ field.id ] }
			onChange={ ( v ) => onChange( { [ field.id ]: v } ) }
		/>
	);
}
