import { Select } from '../Fields';
import { Action } from '../Elements';

const ControlSelect = ( {
	options,
	value,
	onChange,
	placeholder,
	isMulti = false,
} ) => (
	<Action>
		<Select
			isMulti={ isMulti }
			options={ options }
			value={ value }
			placeholder={ placeholder }
			onChange={ onChange }
		/>
	</Action>
);

export default ControlSelect;
