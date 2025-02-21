import { __ } from '@wordpress/i18n';
import PricingTitleBadge from '../../../../ReusableComponents/PricingTitleBadge';
import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const AlternativePaymentMethods = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __(
				'Alternative Payment Methods',
				'woocommerce-paypal-payments'
			) }
			imageBadge={ [
				// 'icon-button-sepa.svg', // Enable this when the SEPA-Gateway is ready.
				'icon-button-ideal.svg',
				'icon-button-blik.svg',
				'icon-button-bancontact.svg',
			] }
			textBadge={ <PricingTitleBadge item="apm" /> }
			description={ __(
				'Seamless payments for customers across the globe using their preferred payment methods.',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default AlternativePaymentMethods;
