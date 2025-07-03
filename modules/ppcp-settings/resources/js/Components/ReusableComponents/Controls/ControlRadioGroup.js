import { Action } from '../Elements';
import { RadioGroup } from '../Fields';

const ControlRadioGroup = ( { options, value, onChange } ) => (
	<Action>
		<RadioGroup
			options={ options }
			selected={ value }
			onChange={ onChange }
		/>
	</Action>
);

export default ControlRadioGroup;
