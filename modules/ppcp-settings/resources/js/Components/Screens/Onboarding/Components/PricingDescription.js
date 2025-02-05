import { __, sprintf } from '@wordpress/i18n';

import { countryPriceInfo } from '../../../../utils/countryPriceInfo';
import { learnMoreLinks } from '../../../../utils/countryInfoLinks';
import { CommonHooks } from '../../../../data';

const PricingDescription = () => {
	const { storeCountry } = CommonHooks.useWooSettings();

	if ( ! countryPriceInfo[ storeCountry ] ) {
		return null;
	}

	const lastDate = 'October 25th, 2024'; // TODO -- needs to be the last plugin update date.
	const countryLinks = learnMoreLinks[ storeCountry ] || learnMoreLinks.US;

	const label = sprintf(
		// translators: %1$s: Pricing date, %2$s Link to PayPal price-details page.
		__(
			'Prices based on domestic transactions as of %1$s. <a target="_blank" href="%2$s">Click here</a> for full pricing details.',
			'woocommerce-paypal-payments'
		),
		lastDate,
		countryLinks.PaymentDetails
	);

	return (
		<p
			className="ppcp-r-optional-payment-methods__description"
			data-country={ storeCountry }
		>
			<sup>1</sup>
			<span dangerouslySetInnerHTML={ { __html: label } } />
		</p>
	);
};

export default PricingDescription;
