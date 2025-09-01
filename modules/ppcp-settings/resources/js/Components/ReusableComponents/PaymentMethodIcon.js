import { Icon } from '@wordpress/components';
import data from '../../utils/data';

const PaymentMethodIcon = ( { type } ) => (
	<Icon
		icon={ data().getImage( `icon-button-${ type }.svg` ) }
		className="ppcp--method-icon"
	/>
);

export default PaymentMethodIcon;
