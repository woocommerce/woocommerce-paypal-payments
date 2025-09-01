import { RadioControl } from '@wordpress/components';

const RadioGroup = ( { options, selected, onChange } ) => {
	return (
		<RadioControl
			options={ options }
			onChange={ onChange }
			selected={ selected }
		/>
	);
};

export default RadioGroup;
