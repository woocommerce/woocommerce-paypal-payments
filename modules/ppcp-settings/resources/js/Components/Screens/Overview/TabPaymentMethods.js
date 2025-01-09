import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import SettingsCard from '../../ReusableComponents/SettingsCard';
import PaymentMethodsBlock from '../../ReusableComponents/SettingsBlocks/PaymentMethodsBlock';
import { CommonHooks } from '../../../data';
import { PaymentHooks } from '../../../data';
import { useActiveModal } from '../../../data/common/hooks';
import Modal from './TabSettingsElements/Blocks/Modal';

const TabPaymentMethods = () => {
	const { storeCountry, storeCurrency } = CommonHooks.useWooSettings();
	const { activeModal, setActiveModal } = useActiveModal();

	const filteredPaymentMethods = useMemo( () => {
		const contextProps = { storeCountry, storeCurrency };

		return {
			onlineCardPayments: filterPaymentMethods(
				paymentMethodsOnlineCardPayments,
				contextProps
			),
			alternative: filterPaymentMethods(
				paymentMethodsAlternative,
				contextProps
			),
		};
	}, [ storeCountry, storeCurrency ] );

	const { paymentMethods } = PaymentHooks.usePaymentMethods();

	const getActiveMethod = () => {
		if ( ! activeModal ) {
			return null;
		}

		const allMethods = [
			...filteredPaymentMethods.payPalCheckout,
			...filteredPaymentMethods.onlineCardPayments,
			...filteredPaymentMethods.alternative,
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
					paymentMethods={ paymentMethods?.paypalCheckout }
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
					paymentMethods={ filteredPaymentMethods.onlineCardPayments }
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
					paymentMethods={ filteredPaymentMethods.alternative }
					onTriggerModal={ setActiveModal }
				/>
			</SettingsCard>

			{ activeModal && (
				<Modal
					method={ getActiveMethod() }
					setModalIsVisible={ () => setActiveModal( null ) }
					onSave={ ( methodId, settings ) => {
						console.log(
							'Saving settings for:',
							methodId,
							settings
						);
						setActiveModal( null );
					} }
				/>
			) }
		</div>
	);
};

function filterPaymentMethods( paymentMethods, contextProps ) {
	return paymentMethods.filter( ( method ) =>
		typeof method.condition === 'function'
			? method.condition( contextProps )
			: true
	);
}

const paymentMethodsOnlineCardPayments = [
	{
		id: 'advanced_credit_and_debit_card_payments',
		title: __(
			'Advanced Credit and Debit Card Payments',
			'woocommerce-paypal-payments'
		),
		description: __(
			"Present custom credit and debit card fields to your payers so they can pay with credit and debit cards using your site's branding.",
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-advanced-cards',
	},
	{
		id: 'fastlane',
		title: __( 'Fastlane by PayPal', 'woocommerce-paypal-payments' ),
		description: __(
			"Tap into the scale and trust of PayPal's customer network to recognize shoppers and make guest checkout more seamless than ever.",
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-fastlane',
	},
	{
		id: 'apple_pay',
		title: __( 'Apple Pay', 'woocommerce-paypal-payments' ),
		description: __(
			'Allow customers to pay via their Apple Pay digital wallet.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-apple-pay',
	},
	{
		id: 'google_pay',
		title: __( 'Google Pay', 'woocommerce-paypal-payments' ),
		description: __(
			'Allow customers to pay via their Google Pay digital wallet.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-google-pay',
	},
];

const paymentMethodsAlternative = [
	{
		id: 'bancontact',
		title: __( 'Bancontact', 'woocommerce-paypal-payments' ),
		description: __(
			'Bancontact is the most widely used, accepted and trusted electronic payment method in Belgium. Bancontact makes it possible to pay directly through the online payment systems of all major Belgian banks.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-bancontact',
	},
	{
		id: 'ideal',
		title: __( 'iDEAL', 'woocommerce-paypal-payments' ),
		description: __(
			'iDEAL is a payment method in the Netherlands that allows buyers to select their issuing bank from a list of options.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-ideal',
	},
	{
		id: 'eps',
		title: __( 'eps', 'woocommerce-paypal-payments' ),
		description: __(
			'An online payment method in Austria, enabling Austrian buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-eps',
	},
	{
		id: 'blik',
		title: __( 'BLIK', 'woocommerce-paypal-payments' ),
		description: __(
			'A widely used mobile payment method in Poland, allowing Polish customers to pay directly via their banking apps. Transactions are processed in PLN.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-blik',
	},
	{
		id: 'mybank',
		title: __( 'MyBank', 'woocommerce-paypal-payments' ),
		description: __(
			'A European online banking payment solution primarily used in Italy, enabling customers to make secure bank transfers during checkout. Transactions are processed in EUR.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-mybank',
	},
	{
		id: 'przelewy24',
		title: __( 'Przelewy24', 'woocommerce-paypal-payments' ),
		description: __(
			'A popular online payment gateway in Poland, offering various payment options for Polish customers. Transactions can be processed in PLN or EUR.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-przelewy24',
	},
	{
		id: 'trustly',
		title: __( 'Trustly', 'woocommerce-paypal-payments' ),
		description: __(
			'A European payment method that allows buyers to make payments directly from their bank accounts, suitable for customers across multiple European countries. Supported currencies include EUR, DKK, SEK, GBP, and NOK.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-trustly',
	},
	{
		id: 'multibanco',
		title: __( 'Multibanco', 'woocommerce-paypal-payments' ),
		description: __(
			'An online payment method in Portugal, enabling Portuguese buyers to make secure payments directly through their bank accounts. Transactions are processed in EUR.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-multibanco',
	},
	{
		id: 'pui',
		title: __( 'Pay upon Invoice', 'woocommerce-paypal-payments' ),
		description: __(
			'Pay upon Invoice is an invoice payment method in Germany. It is a local buy now, pay later payment method that allows the buyer to place an order, receive the goods, try them, verify they are in good order, and then pay the invoice within 30 days.',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-ratepay',
		condition: ( { storeCountry, storeCurrency } ) =>
			storeCountry === 'DE' && storeCurrency === 'EUR',
	},
	{
		id: 'oxxo',
		title: __( 'OXXO', 'woocommerce-paypal-payments' ),
		description: __(
			'OXXO is a Mexican chain of convenience stores. *Get PayPal account permission to use OXXO payment functionality by contacting us at (+52) 800–925–0304',
			'woocommerce-paypal-payments'
		),
		icon: 'payment-method-oxxo',
		condition: ( { storeCountry, storeCurrency } ) =>
			storeCountry === 'MX' && storeCurrency === 'MXN',
	},
];

export default TabPaymentMethods;
