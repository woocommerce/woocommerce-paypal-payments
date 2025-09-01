import { useCallback } from '@wordpress/element';

const RadioButton = ( {
	id,
	name,
	value,
	currentValue,
	checked = null, // alternative to currentValue.
	onChange,
	handleRdbState, // deprecated
} ) => {
	const handleChange = useCallback( () => {
		if ( onChange ) {
			onChange( value );
		} else if ( handleRdbState ) {
			console.warn(
				'Deprecated prop, use "onChange" instead of "handleRdbState"'
			);
			handleRdbState( value );
		}
	}, [ handleRdbState, onChange, value ] );

	const radioProps = {
		className: 'ppcp-r__radio-value',
		type: 'radio',
		onChange: handleChange,
		checked: null === checked ? value === currentValue : checked,
		id,
		name,
		value,
	};

	return (
		<div className="ppcp-r__radio">
			{ /* todo: Can we remove the wrapper div? */ }
			<input { ...radioProps } />
			<span className="ppcp-r__radio-presentation"></span>
		</div>
	);
};

export default RadioButton;
