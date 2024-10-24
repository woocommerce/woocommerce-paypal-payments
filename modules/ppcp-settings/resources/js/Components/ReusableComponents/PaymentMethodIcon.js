import data from '../../utils/data';

const PaymentMethodIcon = ( props ) => {
	if (
		( Array.isArray( props.icons ) &&
			props.icons.includes( props.type ) ) ||
		props.icons === 'all'
	) {
		return data().getImage( 'icon-button-' + props.type + '.svg' );
	}

	return <></>;
};

export default PaymentMethodIcon;
