import { __ } from '@wordpress/i18n';
import PricingTitleBadge from '../../../../ReusableComponents/PricingTitleBadge';
import BadgeBox from '../../../../ReusableComponents/BadgeBox';

const DigitalWallets = ( { learnMore = '' } ) => {
	return (
		<BadgeBox
			title={ __( 'Digital Wallets', 'woocommerce-paypal-payments' ) }
			imageBadge={ [
				'icon-button-apple-pay.svg',
				'icon-button-google-pay.svg',
			] }
			textBadge={ <PricingTitleBadge item="dw" /> }
			description={ __(
				'Accept Apple Pay on eligible devices and Google Pay through mobile and web.',
				'woocommerce-paypal-payments'
			) }
			learnMoreLink={ learnMore }
		/>
	);
};

export default DigitalWallets;
