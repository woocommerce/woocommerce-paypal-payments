import { __ } from '@wordpress/i18n';

import { CommonHooks, OnboardingHooks } from '../../../../data';
import { OptionSelector } from '../../../ReusableComponents/Fields';
import PricingDescription from '../Components/PricingDescription';
import OnboardingHeader from '../Components/OnboardingHeader';
import PaymentFlow from '../Components/PaymentFlow';

const StepPaymentMethods = ( {} ) => {
	const { optionalMethods, setOptionalMethods } =
		OnboardingHooks.useOptionalPaymentMethods();

	return (
		<div className="ppcp-r-page-optional-payment-methods">
			<OnboardingHeader title={ <PaymentStepTitle /> } />
			<div className="ppcp-r-inner-container">
				<OptionSelector
					multiSelect={ false }
					options={ methodChoices }
					onChange={ setOptionalMethods }
					value={ optionalMethods }
				/>

				<PricingDescription />
			</div>
		</div>
	);
};

export default StepPaymentMethods;

const PaymentStepTitle = () => {
	const { storeCountry } = CommonHooks.useWooSettings();

	if ( 'US' === storeCountry ) {
		return __(
			'Add Expanded Checkout for More Ways to Pay',
			'woocommerce-paypal-payments'
		);
	}

	return __(
		'Add optional payment methods to your Checkout',
		'woocommerce-paypal-payments'
	);
};

const OptionalMethodDescription = () => {
	const { storeCountry, storeCurrency } = CommonHooks.useWooSettings();

	return (
		<PaymentFlow
			onlyOptional={ true }
			useAcdc={ true }
			isFastlane={ true }
			isPayLater={ true }
			storeCountry={ storeCountry }
			storeCurrency={ storeCurrency }
		/>
	);
};

const methodChoices = [
	{
		value: true,
		title: __(
			'Available with additional application',
			'woocommerce-paypal-payments'
		),
		description: <OptionalMethodDescription />,
	},
	{
		title: __(
			'No thanks, I prefer to use a different provider for processing credit cards, digital wallets, and local payment methods',
			'woocommerce-paypal-payments'
		),
		value: false,
	},
];
