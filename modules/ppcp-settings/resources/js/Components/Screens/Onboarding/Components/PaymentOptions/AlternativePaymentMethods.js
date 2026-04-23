import { __ } from '@wordpress/i18n';
import { useWooSettings } from '@ppcp-settings/data/common/hooks';
import PricingTitleBadge from '@ppcp-settings/Components/ReusableComponents/PricingTitleBadge';
import BadgeBox from '@ppcp-settings/Components/ReusableComponents/BadgeBox';

const AlternativePaymentMethods = ( { learnMore = '' } ) => {
	const { storeCountry } = useWooSettings();

	// Determine which icons to display based on the country code.
	const imageBadges = [
		// 'icon-button-sepa.svg', // Enable this when the SEPA-Gateway is ready.
		'icon-button-ideal.svg',
		'icon-button-blik.svg',
		'icon-button-bancontact.svg',
		...( storeCountry === 'MX' ? [ 'icon-button-oxxo.svg' ] : [] ),
	];

	return (
		<BadgeBox
			title={ __(
				'Alternative Payment Methods',
				'woocommerce-paypal-payments'
			) }
			imageBadge={ imageBadges }
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
