import {
	getCurrentPaymentMethod,
	ORDER_BUTTON_SELECTOR,
	PaymentMethods,
} from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import { buttonConfiguration, cardFieldsConfiguration } from './Configuration';
import { renderFields } from '../../../ppcp-card-fields/resources/js/Render';
import {
	setVisible,
	setVisibleByClass,
} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

( function ( { ppcp_add_payment_method, jQuery } ) {
	document.addEventListener( 'DOMContentLoaded', async () => {
		jQuery( document.body ).on(
			'click init_add_payment_method',
			'.payment_methods input.input-radio',
			function () {
				setVisibleByClass(
					ORDER_BUTTON_SELECTOR,
					getCurrentPaymentMethod() !== PaymentMethods.PAYPAL,
					'ppcp-hidden'
				);
				setVisible(
					`#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`,
					getCurrentPaymentMethod() === PaymentMethods.PAYPAL
				);
			}
		);

		// TODO move to wc subscriptions module
		if ( ppcp_add_payment_method.is_subscription_change_payment_page ) {
			const saveToAccount = document.querySelector(
				'#wc-ppcp-credit-card-gateway-new-payment-method'
			);
			if ( saveToAccount ) {
				saveToAccount.checked = true;
				saveToAccount.disabled = true;
			}
		}

		const errorHandler = new ErrorHandler(
			ppcp_add_payment_method.labels.error.generic,
			document.querySelector( '.woocommerce-notices-wrapper' )
		);
		errorHandler.clear();

		try {
			const config = {
				url_params: {
					'client-id': ppcp_add_payment_method.client_id,
					'merchant-id': ppcp_add_payment_method.merchant_id,
					components: 'buttons,card-fields',
				},
				save_payment_methods: {
					id_token: ppcp_add_payment_method.id_token,
				},
				user: {
					is_logged: ppcp_add_payment_method.user?.is_logged ?? false,
				},
			};

			const paypal = await loadPayPalScript(
				'ppcp-add-payment-method',
				config
			);

			const paypalButtonContainer = document.querySelector(
				`#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`
			);

			if ( paypalButtonContainer ) {
				paypal
					.Buttons(
						buttonConfiguration(
							ppcp_add_payment_method,
							errorHandler
						)
					)
					.render(
						`#ppc-button-${ PaymentMethods.PAYPAL }-save-payment-method`
					);
			}

			const cardFields = paypal.CardFields(
				cardFieldsConfiguration(
					ppcp_add_payment_method,
					errorHandler
				)
			);

			if ( cardFields.isEligible() ) {
				renderFields( cardFields );
			}

			const placeOrderButton =
				document.querySelector( '#place_order' );
			placeOrderButton?.addEventListener( 'click', ( event ) => {
				const cardPaymentToken = document.querySelector(
					'input[name="wc-ppcp-credit-card-gateway-payment-token"]:checked'
				)?.value;
				if (
					getCurrentPaymentMethod() !==
						'ppcp-credit-card-gateway' ||
					( cardPaymentToken && cardPaymentToken !== 'new' )
				) {
					return;
				}
				placeOrderButton.disabled = true;
				event.preventDefault();
				cardFields.submit().catch( ( error ) => {
					console.error( error );
					errorHandler.message( ppcp_add_payment_method.error_message );
					placeOrderButton.disabled = false;
				} );
			} );
		} catch ( error ) {
			console.error( 'Failed to load PayPal script:', error );
			errorHandler.message(
				ppcp_add_payment_method.labels.error.generic ||
				'Failed to load PayPal. Please refresh the page.'
			);
		}
	} );
} )( {
	ppcp_add_payment_method: window.ppcp_add_payment_method,
	jQuery: window.jQuery,
} );
