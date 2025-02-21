import { __ } from '@wordpress/i18n';

import BadgeBox from '../../../ReusableComponents/BadgeBox';
import PaymentMethodsGroup from './PaymentMethodsGroup';
import { PayPalCheckout } from './PaymentOptions';
import { usePaymentConfig } from '../hooks/usePaymentConfig';

const PaymentFlow = ( {
	useAcdc,
	isFastlane,
	isPayLater,
	storeCountry,
	onlyOptional = false,
} ) => {
	const {
		includedMethods,
		optionalMethods,
		optionalTitle,
		optionalDescription,
		learnMoreConfig,
	} = usePaymentConfig( storeCountry, isPayLater, useAcdc, isFastlane );

	if ( onlyOptional ) {
		return (
			<OptionalMethodsSection
				methods={ optionalMethods }
				learnMoreConfig={ learnMoreConfig }
			/>
		);
	}

	return (
		<div className="ppcp-r-welcome-docs__wrapper">
			<DefaultMethodsSection
				methods={ includedMethods }
				learnMoreConfig={ learnMoreConfig }
			/>

			<OptionalMethodsSection
				title={ optionalTitle }
				description={ optionalDescription }
				methods={ optionalMethods }
				learnMoreConfig={ learnMoreConfig }
			/>
		</div>
	);
};

export default PaymentFlow;

const DefaultMethodsSection = ( { methods, learnMoreConfig } ) => {
	return (
		<div className="ppcp-r-welcome-docs__col">
			<PayPalCheckout learnMore={ learnMoreConfig.PayPalCheckout } />
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
