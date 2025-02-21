import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';

import SettingsCard from '../../../ReusableComponents/SettingsCard';
import { PaymentMethodsBlock } from '../../../ReusableComponents/SettingsBlocks';
import { CommonHooks, OnboardingHooks, PaymentHooks } from '../../../../data';
import { useActiveModal } from '../../../../data/common/hooks';
import Modal from '../Components/Payment/Modal';

const TabPaymentMethods = () => {
	const methods = PaymentHooks.usePaymentMethods();
	const { setPersistent, changePaymentSettings } = PaymentHooks.useStore();
	const { activeModal, setActiveModal } = useActiveModal();

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
				'fastlaneCardholderName',
				'fastlaneDisplayWatermark',
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
	const { canUseCardPayments } = OnboardingHooks.useFlags();

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
				methods={ methods.paypal }
				onTriggerModal={ setActiveModal }
			/>
			{ merchant.isBusinessSeller && canUseCardPayments && (
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
				/>
			) }
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
			/>

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

const PaymentMethodCard = ( {
	id,
	title,
	description,
	icon,
	methods,
	onTriggerModal,
} ) => (
	<SettingsCard
		id={ id }
		title={ title }
		description={ description }
		icon={ icon }
		contentContainer={ false }
	>
		<PaymentMethodsBlock
			paymentMethods={ methods }
			onTriggerModal={ onTriggerModal }
		/>
	</SettingsCard>
);
