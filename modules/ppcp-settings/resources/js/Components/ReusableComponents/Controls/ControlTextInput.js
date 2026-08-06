import { TextControl } from '@wordpress/components';
import classNames from 'classnames';

import { Action, Description } from '../Elements';

const ControlTextInput = ( {
	value,
	description,
	onChange,
	onBlur,
	placeholder = '',
	error = '',
} ) => (
	<Action>
		<TextControl
			__nextHasNoMarginBottom
			className={ classNames( 'ppcp-r-vertical-text-control', {
				'ppcp--has-error': !! error,
			} ) }
			placeholder={ placeholder }
			value={ value }
			onChange={ onChange }
			onBlur={ onBlur }
			aria-invalid={ !! error }
		/>
		{ error && <p className="ppcp-r-control-error">{ error }</p> }
		<Description>{ description }</Description>
	</Action>
);

export default ControlTextInput;
