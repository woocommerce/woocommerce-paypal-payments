import { __ } from '@wordpress/i18n';

import BadgeBox from '../../../../ReusableComponents/BadgeBox';
import PricingTitleBadge from '../../../../ReusableComponents/PricingTitleBadge';

const PayLater = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __( 'Pay Later', 'woocommerce-paypal-payments' ) }
			imageBadge={ [ 'icon-payment-method-paypal-small.svg' ] }
			textBadge={ <PricingTitleBadge item="plater" /> }
			description={ __(
				'Offer installment payment options and get paid upfront.',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default PayLater;
