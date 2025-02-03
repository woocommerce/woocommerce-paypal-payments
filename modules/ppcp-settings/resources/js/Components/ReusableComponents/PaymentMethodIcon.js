import { Icon } from '@wordpress/components';

import data from '../../utils/data';

const PaymentMethodIcon = ( { icons, type } ) => {
	const validIcon = Array.isArray( icons ) && icons.includes( type );

	if ( validIcon || icons === 'all' ) {
		return (
			<Icon
				icon={ data().getImage( 'icon-button-' + type + '.svg' ) }
				className="ppcp--method-icon"
			/>
		);
	}

	return null;
};

export default PaymentMethodIcon;
