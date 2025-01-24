import { __ } from '@wordpress/i18n';

import SettingsCard from '../../ReusableComponents/SettingsCard';
import { PaymentMethodsBlock } from '../../ReusableComponents/SettingsBlocks';
import { PaymentHooks } from '../../../data';
import { useActiveModal } from '../../../data/common/hooks';
import Modal from './TabSettingsElements/Blocks/Modal';
import { usePaymentMethods } from '../../../data/payment/hooks';

const TabPaymentMethods = () => {
	const { paymentMethodsPayPalCheckout } =
		PaymentHooks.usePaymentMethodsPayPalCheckout();
	const { paymentMethodsOnlineCardPayments } =
		PaymentHooks.usePaymentMethodsOnlineCardPayments();
	const { paymentMethodsAlternative } =
		PaymentHooks.usePaymentMethodsAlternative();

	const { setPersistent } = usePaymentMethods();

	const { activeModal, setActiveModal } = useActiveModal();

	const getActiveMethod = () => {
		if ( ! activeModal ) {
			return null;
		}

		const allMethods = [
			...paymentMethodsPayPalCheckout,
			...paymentMethodsOnlineCardPayments,
			...paymentMethodsAlternative,
		];

		return allMethods.find( ( method ) => method.id === activeModal );
	};

	return (
		<div className="ppcp-r-payment-methods">
			<SettingsCard
				id="ppcp-paypal-checkout-card"
				title={ __( 'PayPal Checkout', 'woocommerce-paypal-payments' ) }
				description={ __(
					'Select your preferred checkout option with PayPal for easy payment processing.',
					'woocommerce-paypal-payments'
				) }
				icon="icon-checkout-standard.svg"
				contentContainer={ false }
			>
				<PaymentMethodsBlock
					paymentMethods={ paymentMethodsPayPalCheckout }
					onTriggerModal={ setActiveModal }
				/>
			</SettingsCard>
			<SettingsCard
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
				contentContainer={ false }
			>
				<PaymentMethodsBlock
					paymentMethods={ paymentMethodsOnlineCardPayments }
					onTriggerModal={ setActiveModal }
				/>
			</SettingsCard>
			<SettingsCard
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
				contentContainer={ false }
			>
				<PaymentMethodsBlock
					paymentMethods={ paymentMethodsAlternative }
					onTriggerModal={ setActiveModal }
				/>
			</SettingsCard>

			{ activeModal && (
				<Modal
					method={ getActiveMethod() }
					setModalIsVisible={ () => setActiveModal( null ) }
					onSave={ ( methodId, settings ) => {
						setPersistent( methodId, {
							...getActiveMethod(),
							title: settings.checkoutPageTitle,
							description: settings.checkoutPageDescription,
						} );

						if ( 'paypalShowLogo' in settings ) {
							setPersistent(
								'paypalShowLogo',
								settings.paypalShowLogo
							);
						}
						if ( 'threeDSecure' in settings ) {
							setPersistent(
								'threeDSecure',
								settings.threeDSecure
							);
						}
						if ( 'fastlaneCardholderName' in settings ) {
							setPersistent(
								'fastlaneCardholderName',
								settings.fastlaneCardholderName
							);
						}
						if ( 'fastlaneDisplayWatermark' in settings ) {
							setPersistent(
								'fastlaneDisplayWatermark',
								settings.fastlaneDisplayWatermark
							);
						}

						setActiveModal( null );
					} }
				/>
			) }
		</div>
	);
};

export default TabPaymentMethods;
