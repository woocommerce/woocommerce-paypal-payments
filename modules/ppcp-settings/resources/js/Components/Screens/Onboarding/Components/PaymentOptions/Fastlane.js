import { __ } from '@wordpress/i18n';
import PricingTitleBadge from '../../../../ReusableComponents/PricingTitleBadge';
import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const Fastlane = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __( '', 'woocommerce-paypal-payments' ) }
			imageBadge={ [ 'icon-payment-method-fastlane-small.svg' ] }
			textBadge={
				<PricingTitleBadge item="fast country currency=storeCurrency=storeCountrylane" />
			}
			description={ __(
				"Speed up guest checkout with Fastlane. Link a customer's email address to their payment details.",
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default Fastlane;
