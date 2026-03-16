import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import { CommonHooks, OnboardingHooks } from '@ppcp-settings/data';
import { OptionSelector } from '@ppcp-settings/Components/ReusableComponents/Fields';
import PricingDescription from '../Components/PricingDescription';
import OnboardingHeader from '../Components/OnboardingHeader';
import PaymentFlow from '../Components/PaymentFlow';

const StepPaymentMethods = () => {
	const { optionalMethods, setOptionalMethods } =
		OnboardingHooks.useOptionalPaymentMethods();
	const { ownBrandOnly, storeCountry } = CommonHooks.useWooSettings();
	const { isCasualSeller } = OnboardingHooks.useBusiness();
	const { canUseCardPayments, canUseDigitalWallets } =
		OnboardingHooks.useFlags();

	const hasAdvancedMethods = canUseCardPayments || canUseDigitalWallets;
	const isMexico = 'MX' === storeCountry;
	const showAdvancedSection = hasAdvancedMethods && ! isMexico;

	const optionalMethodTitle = useMemo( () => {
		// The BCDC flow does not show a title. No ACDC and no digital wallets does not show a title. Mexico does not show a title.
		if ( isCasualSeller || ! showAdvancedSection ) {
			return null;
		}

		return __(
			'Available with additional application',
			'woocommerce-paypal-payments'
		);
	}, [ isCasualSeller, showAdvancedSection ] );

	const methodChoices = [
		{
			value: true,
			title: optionalMethodTitle,
			description: <OptionalMethodDescription />,
		},
		{
			title:
				ownBrandOnly || ! showAdvancedSection
					? __(
							'No thanks, I prefer to use a different provider for local payment methods',
							'woocommerce-paypal-payments'
					  )
					: __(
							'No thanks, I prefer to use a different provider for processing credit cards, digital wallets, and local payment methods',
							'woocommerce-paypal-payments'
					  ),
			value: false,
		},
	];

	const handleMethodChange = ( value ) => {
		setOptionalMethods( value, 'user' );
	};

	return (
		<div className="ppcp-r-page-optional-payment-methods">
			<OnboardingHeader
				title={ <PaymentStepTitle isBrandedOnly={ ownBrandOnly } /> }
			/>
			<div className="ppcp-r-inner-container">
				<OptionSelector
					multiSelect={ false }
					options={ methodChoices }
					onChange={ handleMethodChange }
					value={ optionalMethods }
				/>

				<PricingDescription />
			</div>
		</div>
	);
};

export default StepPaymentMethods;

const PaymentStepTitle = ( ownBrandOnly ) => {
	if ( ownBrandOnly.isBrandedOnly ) {
		return __(
			'Add Expanded Checkout for more ways to pay',
			'woocommerce-paypal-payments'
		);
	}
	return __(
		'Add Expanded Checkout for more ways to pay',
		'woocommerce-paypal-payments'
	);
};

const OptionalMethodDescription = () => {
	const { isCasualSeller } = OnboardingHooks.useBusiness();
	const { storeCountry, storeCurrency, ownBrandOnly } =
		CommonHooks.useWooSettings();
	const { canUseCardPayments, canUseDigitalWallets } =
		OnboardingHooks.useFlags();

	return (
		<PaymentFlow
			onlyOptional={ true }
			useAcdc={
				! isCasualSeller && canUseCardPayments && 'MX' !== storeCountry
			}
			useDigitalWallets={
				! isCasualSeller &&
				canUseDigitalWallets &&
				'MX' !== storeCountry
			}
			isFastlane={ true }
			isPayLater={ true }
			ownBrandOnly={ ownBrandOnly }
			storeCountry={ storeCountry }
			storeCurrency={ storeCurrency }
		/>
	);
};
