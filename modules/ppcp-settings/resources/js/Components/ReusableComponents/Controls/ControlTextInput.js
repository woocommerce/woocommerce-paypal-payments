import { TextControl } from '@wordpress/components';

import { Action, Description } from '../Elements';

const ControlTextInput = ( {
	value,
	description,
	onChange,
	placeholder = '',
} ) => (
	<Action>
		<TextControl
			__nextHasNoMarginBottom={ true }
			className="ppcp-r-vertical-text-control"
			placeholder={ placeholder }
			value={ value }
			onChange={ onChange }
		/>
		<Description>{ description }</Description>
	</Action>
);

export default ControlTextInput;
