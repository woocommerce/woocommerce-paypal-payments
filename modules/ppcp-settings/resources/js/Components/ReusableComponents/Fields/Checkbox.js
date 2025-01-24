import { CheckboxControl } from '@wordpress/components';
import classNames from 'classnames';

const Checkbox = ( {
	label,
	value,
	checked = null,
	disabled = null,
	onChange,
	changeCallback, // deprecated.
} ) => {
	const className = classNames( { 'ppcp--is-disabled': disabled } );

	const handleChange = ( isChecked ) => {
		if ( onChange ) {
			onChange( value, isChecked );
		} else if ( changeCallback ) {
			console.warn(
				'Deprecated prop, use "onChange" instead of "changeCallback"'
			);
			changeCallback( value, isChecked );
		}
	};

	return (
		<CheckboxControl
			label={ label }
			value={ value }
			checked={ checked }
			disabled={ disabled }
			onChange={ handleChange }
			className={ className }
		/>
	);
};

export default Checkbox;
