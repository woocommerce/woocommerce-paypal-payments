import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import PaymentMethodIcons from '@ppcp-settings/Components/ReusableComponents/PaymentMethodIcons';
import { Separator } from '@ppcp-settings/Components/ReusableComponents/Elements';
import Accordion from '@ppcp-settings/Components/ReusableComponents/AccordionSection';
import { CommonHooks, OnboardingHooks } from '@ppcp-settings/data';
import BusyStateWrapper from '@ppcp-settings/Components/ReusableComponents/BusyStateWrapper';
import HelpSection from '@ppcp-settings/Components/ReusableComponents/HelpSection';
import OnboardingHeader from '../Components/OnboardingHeader';
import WelcomeDocs from '../Components/WelcomeDocs';
import AdvancedOptionsForm from '../Components/AdvancedOptionsForm';
import { usePaymentConfig } from '../hooks/usePaymentConfig';

const StepWelcome = ( { onNext } ) => {
	const { storeCountry, ownBrandOnly } = CommonHooks.useWooSettings();
	const { canUseCardPayments, canUseDigitalWallets, canUseFastlane } =
		OnboardingHooks.useFlags();

	const { icons } = usePaymentConfig(
		storeCountry,
		canUseCardPayments,
		canUseDigitalWallets,
		canUseFastlane,
		ownBrandOnly
	);

	const onboardingHeaderDescription =
		( canUseCardPayments || canUseDigitalWallets ) && ! ownBrandOnly
			? __(
					'Your all-in-one integration for PayPal checkout solutions that enable buyers to pay via PayPal, Pay Later, all major credit/debit cards, Apple Pay, Google Pay, and more.',
					'woocommerce-paypal-payments'
			  )
			: __(
					'Your all-in-one integration for PayPal checkout solutions that enable buyers to pay via PayPal, Pay Later, and more.',
					'woocommerce-paypal-payments'
			  );

	return (
		<div className="ppcp-r-page-welcome">
			<OnboardingHeader
				title={ __(
					'Welcome to PayPal Payments',
					'woocommerce-paypal-payments'
				) }
				description={ onboardingHeaderDescription }
			/>
			<div className="ppcp-r-inner-container">
				<WelcomeFeatures />
				<PaymentMethodIcons icons={ icons } />
				<p className="ppcp-r-button__description">
					{ __(
						'Click the button below to be guided through connecting your existing PayPal account or creating a new one. You will be able to choose the payment options that are right for your store.',
						'woocommerce-paypal-payments'
					) }
				</p>
				<BusyStateWrapper>
					<Button
						className="ppcp-r-button-activate-paypal"
						variant="primary"
						onClick={ onNext }
					>
						{ __(
							'Activate PayPal Payments',
							'woocommerce-paypal-payments'
						) }
					</Button>
				</BusyStateWrapper>
			</div>
			<Separator className="ppcp-r-page-welcome-mode-separator" />
			<WelcomeDocs
				useAcdc={ canUseCardPayments }
				useDigitalWallets={ canUseDigitalWallets }
				isFastlane={ canUseFastlane }
				storeCountry={ storeCountry }
				ownBrandOnly={ ownBrandOnly }
			/>
			<Separator text={ __( 'or', 'woocommerce-paypal-payments' ) } />
			<Accordion
				title={ __(
					'See advanced options',
					'woocommerce-paypal-payments'
				) }
				className="onboarding-advanced-options"
				noCaps={ true }
				id="advanced-options"
			>
				<AdvancedOptionsForm />
			</Accordion>
			<HelpSection />
		</div>
	);
};

const WelcomeFeatures = () => {
	return (
		<div className="ppcp-r-welcome-features">
			<div className="ppcp-r-welcome-features__col">
				<span>{ __( 'Deposits', 'woocommerce-paypal-payments' ) }</span>
				<p>{ __( 'Instant', 'woocommerce-paypal-payments' ) }</p>
			</div>
			<div className="ppcp-r-welcome-features__col">
				<span>
					{ __( 'Payment Capture', 'woocommerce-paypal-payments' ) }
				</span>
				<p>
					{ __(
						'Authorize only or Capture',
						'woocommerce-paypal-payments'
					) }
				</p>
			</div>
			<div className="ppcp-r-welcome-features__col">
				<span>
					{ __(
						'Recurring payments',
						'woocommerce-paypal-payments'
					) }
				</span>
				<p>{ __( 'Supported', 'woocommerce-paypal-payments' ) }</p>
			</div>
		</div>
	);
};
export default StepWelcome;
