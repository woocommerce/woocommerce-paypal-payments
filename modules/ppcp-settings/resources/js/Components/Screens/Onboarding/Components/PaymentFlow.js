import { __ } from '@wordpress/i18n';

import BadgeBox from '../../../ReusableComponents/BadgeBox';
import PaymentMethodsGroup from './PaymentMethodsGroup';
import { PayPalCheckout } from './PaymentOptions';
import { usePaymentConfig } from '../hooks/usePaymentConfig';

/**
 * Displays the payment method details, tailored to the defined merchant.
 *
 * @param {Object}  props
 * @param {string}  props.storeCountry The merchant's store country. 2-character ISO code.
 * @param {boolean} props.useAcdc      Whether to include advanced card payments. When false, only BCDC items are included.
 * @param {boolean} props.isFastlane   Whether Fastlane should be included.
 * @param {boolean} props.ownBrandOnly Whether to show only PayPal's own payment methods.
 * @param {boolean} props.onlyOptional Whether to only return the "right column", which includes the optional opt-in payment methods. When true, the "core" payment methods are not included.
 * @return {JSX.Element} The payment options component.
 * @class
 */
const PaymentFlow = ( {
	useAcdc,
	isFastlane,
	storeCountry,
	ownBrandOnly,
	onlyOptional = false,
} ) => {
	const {
		includedMethods,
		optionalMethods,
		optionalTitle,
		optionalDescription,
		learnMoreConfig,
		paypalCheckoutDescription,
	} = usePaymentConfig( storeCountry, useAcdc, isFastlane, ownBrandOnly );

	// When only opt-in methods are requested, without core-payment details, return early.
	if ( onlyOptional ) {
		return (
			<OptionalMethodsSection
				methods={ optionalMethods }
				learnMoreConfig={ learnMoreConfig }
			/>
		);
	}
	const description =
		useAcdc && 'MX' !== storeCountry ? optionalDescription : '';
	return (
		<div className="ppcp-r-welcome-docs__wrapper">
			<DefaultMethodsSection
				methods={ includedMethods }
				learnMoreConfig={ learnMoreConfig }
				paypalCheckoutDescription={ paypalCheckoutDescription }
			/>

			<OptionalMethodsSection
				title={ optionalTitle }
				description={ description }
				methods={ optionalMethods }
				learnMoreConfig={ learnMoreConfig }
			/>
		</div>
	);
};

export default PaymentFlow;

const DefaultMethodsSection = ( {
	methods,
	learnMoreConfig,
	paypalCheckoutDescription,
} ) => {
	return (
		<div className="ppcp-r-welcome-docs__col">
			<PayPalCheckout
				learnMore={ learnMoreConfig.PayPalCheckout }
				description={ paypalCheckoutDescription }
			/>
			<BadgeBox
				title={ __(
					'Included in PayPal Checkout',
					'woocommerce-paypal-payments'
				) }
			/>
			<PaymentMethodsGroup
				methods={ methods }
				learnMoreConfig={ learnMoreConfig }
			/>
		</div>
	);
};

const OptionalMethodsSection = ( {
	title = '',
	description = '',
	methods,
	learnMoreConfig,
} ) => {
	if ( ! methods.length ) {
		return null;
	}

	return (
		<div className="ppcp-r-welcome-docs__col">
			{ title && (
				<BadgeBox
					title={ title }
					description={ description }
					learnMoreLink={ learnMoreConfig.OptionalMethods }
				/>
			) }
			<PaymentMethodsGroup
				methods={ methods }
				learnMoreConfig={ learnMoreConfig }
			/>
		</div>
	);
};
