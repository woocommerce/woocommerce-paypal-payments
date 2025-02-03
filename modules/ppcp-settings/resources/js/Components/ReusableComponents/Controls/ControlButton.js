import { Button } from '@wordpress/components';

import { Action } from '../Elements';

const ControlButton = ( {
	type = 'secondary',
	isBusy,
	onClick,
	buttonLabel,
} ) => (
	<Action>
		<Button
			className="small-button"
			isBusy={ isBusy }
			variant={ type }
			onClick={ onClick }
		>
			{ buttonLabel }
		</Button>
	</Action>
);

export default ControlButton;
