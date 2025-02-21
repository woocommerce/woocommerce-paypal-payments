import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import { CommonHooks, OnboardingHooks } from '../../../../data';
import { OptionSelector } from '../../../ReusableComponents/Fields';
import PricingDescription from '../Components/PricingDescription';
import OnboardingHeader from '../Components/OnboardingHeader';
import PaymentFlow from '../Components/PaymentFlow';

const StepPaymentMethods = () => {
	const { optionalMethods, setOptionalMethods } =
		OnboardingHooks.useOptionalPaymentMethods();
	const { isCasualSeller } = OnboardingHooks.useBusiness();

	const optionalMethodTitle = useMemo( () => {
		// The BCDC flow does not show a title.
		if ( isCasualSeller ) {
			return null;
		}

		return __(
			'Available with additional application',
			'woocommerce-paypal-payments'
		);
	}, [ isCasualSeller ] );

	const methodChoices = [
		{
			value: true,
			title: optionalMethodTitle,
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
	const { isCasualSeller } = OnboardingHooks.useBusiness();

	/**
	 * Casual sellers = Personal accounts. Those accounts have no ACDC-capabilities, but should get
	 *   the choice for BCDC-payments.
	 */
	return (
		<PaymentFlow
			onlyOptional={ true }
			useAcdc={ ! isCasualSeller }
			isFastlane={ true }
			isPayLater={ true }
			storeCountry={ storeCountry }
			storeCurrency={ storeCurrency }
		/>
	);
};
