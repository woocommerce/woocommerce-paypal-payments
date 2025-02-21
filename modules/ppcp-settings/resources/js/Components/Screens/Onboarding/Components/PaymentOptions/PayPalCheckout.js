import { __ } from '@wordpress/i18n';

import PricingTitleBadge from '../../../../ReusableComponents/PricingTitleBadge';
import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const PayPalCheckout = ( {
	learnMore = 'https://www.paypal.com/us/business/accept-payments/checkout',
} ) => {
	return (
		<BadgeBox
			title={ __( 'PayPal Checkout', 'woocommerce-paypal-payments' ) }
			textBadge={ <PricingTitleBadge item="checkout" /> }
			description={ __(
				'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximise conversion',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default PayPalCheckout;
