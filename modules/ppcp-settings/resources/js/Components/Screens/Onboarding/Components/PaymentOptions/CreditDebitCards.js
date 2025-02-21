import { __ } from '@wordpress/i18n';

import PricingTitleBadge from '../../../../ReusableComponents/PricingTitleBadge';
import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const CreditDebitCards = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __(
				'Credit and Debit Cards',
				'woocommerce-paypal-payments'
			) }
			imageBadge={ [
				'icon-button-visa.svg',
				'icon-button-mastercard.svg',
				'icon-button-amex.svg',
				'icon-button-discover.svg',
			] }
			textBadge={ <PricingTitleBadge item="standardCardFields" /> }
			description={ __(
				'Process major credit and debit cards through PayPal’s card fields.',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default CreditDebitCards;
