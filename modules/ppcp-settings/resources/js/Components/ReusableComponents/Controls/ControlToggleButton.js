import { ToggleControl } from '@wordpress/components';
import { Action, Description } from '../Elements';

const ControlToggleButton = ( { label, description, value, onChange } ) => (
	<Action>
		<ToggleControl
			className="ppcp--control-toggle"
			__nextHasNoMarginBottom={ true }
			checked={ value }
			onChange={ onChange }
			label={ label }
			help={
				description ? <Description>{ description }</Description> : null
			}
		/>
	</Action>
);

export default ControlToggleButton;
