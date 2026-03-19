import { __ } from '@wordpress/i18n';
import PricingTitleBadge from '@ppcp-settings/Components/ReusableComponents/PricingTitleBadge';
import BadgeBox from '@ppcp-settings/Components/ReusableComponents/BadgeBox';

const Fastlane = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __( '', 'woocommerce-paypal-payments' ) }
			imageBadge={ [ 'icon-payment-method-fastlane-small.svg' ] }
			textBadge={ <PricingTitleBadge item="axo" /> }
			description={ __(
				"Speed up guest checkout with Fastlane. Link a customer's email address to their payment details.",
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default Fastlane;
