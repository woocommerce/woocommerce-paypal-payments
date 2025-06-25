import { ToggleControl } from '@wordpress/components';
import { Action, Description } from '../Elements';

const ControlToggleButton = ( {
	id = '',
	label,
	description,
	value,
	onChange,
	disabled = false,
} ) => (
	<Action id={ id }>
		<ToggleControl
			className="ppcp--control-toggle"
			__nextHasNoMarginBottom
			checked={ value }
			onChange={ onChange }
			label={ label }
			help={
				description ? <Description>{ description }</Description> : null
			}
			disabled={ disabled }
		/>
	</Action>
);

export default ControlToggleButton;
