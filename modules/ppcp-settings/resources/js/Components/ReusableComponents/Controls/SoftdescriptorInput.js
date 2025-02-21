import ControlTextInput from './ControlTextInput';

const SoftDescriptorInput = ( { value, onChange, placeholder } ) => {
	const handleChange = ( newValue ) => {
		if ( newValue.length <= 22 ) {
			onChange( newValue );
		}
	};

	return (
		<ControlTextInput
			value={ value }
			onChange={ handleChange }
			placeholder={ placeholder }
		/>
	);
};

export default SoftDescriptorInput;
