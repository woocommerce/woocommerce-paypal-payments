import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';

import {
	CommonHooks,
	OnboardingHooks,
	PaymentHooks,
} from '@ppcp-settings/data';
import {
	useActiveModal,
	useWooSettings,
} from '@ppcp-settings/data/common/hooks';
import Modal from '../Components/Payment/Modal';
import PaymentMethodCard from '../Components/Payment/PaymentMethodCard';
import MigrationBanner from '../Components/Payment/MigrationBanner';
import { useFeatures } from '@ppcp-settings/data/features/hooks';

const TabPaymentMethods = () => {
	const methods = PaymentHooks.usePaymentMethods();
	const store = PaymentHooks.useStore();
	const { setPersistent, changePaymentSettings } = store;
	const { activeModal, setActiveModal } = useActiveModal();
	const { features } = useFeatures();

	// Get all methods as a map for dependency checking
	const methodsMap = {};
	methods.all.forEach( ( method ) => {
		methodsMap[ method.id ] = method;
	} );

	const getActiveMethod = () => {
		if ( ! activeModal ) {
			return null;
		}
		return methods.all.find( ( method ) => method.id === activeModal );
	};

	const handleSave = useCallback(
		( methodId, settings ) => {
			changePaymentSettings( methodId, {
				title: settings.checkoutPageTitle,
				description: settings.checkoutPageDescription,
			} );

			const persistentSettings = [
				'paypalShowLogo',
				'threeDSecure',
				'cardholderName',
				'fastlaneDisplayWatermark',
				'puiBrandName',
				'puiLogoUrl',
				'puiCustomerServiceInstructions',
			];

			persistentSettings.forEach( ( setting ) => {
				if ( setting in settings ) {
					// TODO: Create a dedicated setter for those values.
					setPersistent( setting, settings[ setting ] );
				}
			} );

			setActiveModal( null );
		},
		[ changePaymentSettings, setActiveModal, setPersistent ]
	);

	const merchant = CommonHooks.useMerchant();
	const { storeCountry } = useWooSettings();
	const { canUseCardPayments } = OnboardingHooks.useFlags();

	const showCardPayments =
		methods.cardPayment.length > 0 &&
		merchant.isBusinessSeller &&
		canUseCardPayments &&
		// Show ACDC if the merchant has the feature enabled in PayPal account.
		features.some(
			( feature ) =>
				feature.id === 'advanced_credit_and_debit_cards' &&
				feature.enabled
		);

	// Hide BCDC for all countries except Mexico when ACDC is turned on.
	const filteredPayPalMethods = methods.paypal.filter(
		( method ) =>
			method.id !== 'ppcp-card-button-gateway' ||
			storeCountry === 'MX' ||
			! features.some(
				( feature ) =>
					feature.id === 'advanced_credit_and_debit_cards' &&
					feature.enabled === true
			)
	);

	const showApms = methods.apm.length > 0 && merchant.isBusinessSeller;

	const isBcdcOverrideFlagEnabled =
		window.ppcpSettings?.isBcdcOverrideFlagEnabled;
	return (
		<div className="ppcp-r-payment-methods">
			<PaymentMethodCard
				id="ppcp-paypal-checkout-card"
				title={ __( 'PayPal Checkout', 'woocommerce-paypal-payments' ) }
				description={ __(
					'Select your preferred checkout option with PayPal for easy payment processing.',
					'woocommerce-paypal-payments'
				) }
				icon="icon-checkout-standard.svg"
				methods={ filteredPayPalMethods }
				onTriggerModal={ setActiveModal }
				methodsMap={ methodsMap }
			/>

			{ showCardPayments && (
				<PaymentMethodCard
					id="ppcp-card-payments-card"
					title={ __(
						'Online Card Payments',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'Select your preferred card payment options for efficient payment processing.',
						'woocommerce-paypal-payments'
					) }
					icon="icon-checkout-online-methods.svg"
					methods={ methods.cardPayment }
					onTriggerModal={ setActiveModal }
					methodsMap={ methodsMap }
				/>
			) }

			{ isBcdcOverrideFlagEnabled && (
				<MigrationBanner
					id="ppcp-migration-banner"
					className="ppcp-r-settings-migration-banner"
					title={ __(
						'Unlock Advanced Card Processing',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'Upgrade your card payments with embedded card fields for faster checkout, Apple Pay and Google Pay support, and Fastlane one-click checkout (US only).',
						'woocommerce-paypal-payments'
					) }
					actionProps={ {
						buttons: [
							{
								type: 'secondary',
								text: __(
									'Upgrade now',
									'woocommerce-paypal-payments'
								),
							},
							{
								type: 'tertiary',
								text: __(
									'Dismiss',
									'woocommerce-paypal-payments'
								),
							},
						],
					} }
				/>
			) }

			{ showApms && (
				<PaymentMethodCard
					id="ppcp-alternative-payments-card"
					title={ __(
						'Alternative Payment Methods',
						'woocommerce-paypal-payments'
					) }
					description={ __(
						'With alternative payment methods, customers across the globe can pay with their bank accounts and other local payment methods.',
						'woocommerce-paypal-payments'
					) }
					icon="icon-checkout-alternative-methods.svg"
					methods={ methods.apm }
					onTriggerModal={ setActiveModal }
					methodsMap={ methodsMap }
					showBulkToggle={ methods.apm.length > 1 }
					groupName="Alternative Payment"
				/>
			) }

			{ activeModal && (
				<Modal
					method={ getActiveMethod() }
					setModalIsVisible={ () => setActiveModal( null ) }
					onSave={ handleSave }
				/>
			) }
		</div>
	);
};

export default TabPaymentMethods;
