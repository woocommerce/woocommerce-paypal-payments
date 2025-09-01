import { __ } from '@wordpress/i18n';

import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const PayWithPayPal = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __( 'Pay with PayPal', 'woocommerce-paypal-payments' ) }
			imageBadge={ [ 'icon-button-paypal.svg' ] }
			description={ __(
				'Our brand recognition helps give customers the confidence to buy.',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default PayWithPayPal;
