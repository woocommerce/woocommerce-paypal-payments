import { __ } from '@wordpress/i18n';

import PricingTitleBadge from '../../../../ReusableComponents/PricingTitleBadge';
import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const PayPalCheckout = ( {
	learnMore = 'https://www.paypal.com/us/business/accept-payments/checkout',
	description,
} ) => {
	const title = __( 'PayPal Checkout', 'woocommerce-paypal-payments' );

	return (
		<BadgeBox
			title={ title }
			textBadge={ <PricingTitleBadge item="checkout" /> }
			description={ description }
			learnMoreLink={ learnMore }
		/>
	);
};

export default PayPalCheckout;
