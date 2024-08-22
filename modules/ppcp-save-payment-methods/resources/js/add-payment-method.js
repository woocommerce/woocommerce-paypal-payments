import {
	getCurrentPaymentMethod,
	ORDER_BUTTON_SELECTOR,
	PaymentMethods,
} from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';
import { loadScript } from '@paypal/paypal-js';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import { buttonConfiguration, cardFieldsConfiguration } from './Configuration';
import { renderFields } from '../../../ppcp-card-fields/resources/js/Render';
import {
	setVisible,
	setVisibleByClass,
} from '../../../ppcp-button/resources/js/modules/Helper/Hiding';

( function ( { ppcp_add_payment_method, jQuery } ) {
	document.addEventListener( 'DOMContentLoaded', () => {
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

		setTimeout( () => {
			loadScript( {
				clientId: ppcp_add_payment_method.client_id,
				merchantId: ppcp_add_payment_method.merchant_id,
				dataUserIdToken: ppcp_add_payment_method.id_token,
				components: 'buttons,card-fields',
			} ).then( ( paypal ) => {
				const errorHandler = new ErrorHandler(
					ppcp_add_payment_method.labels.error.generic,
					document.querySelector( '.woocommerce-notices-wrapper' )
				);
				errorHandler.clear();

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

				document
					.querySelector( '#place_order' )
					?.addEventListener( 'click', ( event ) => {
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

						event.preventDefault();

						cardFields.submit().catch( ( error ) => {
							console.error( error );
						} );
					} );
			} );
		}, 1000 );
	} );
} )( {
	ppcp_add_payment_method: window.ppcp_add_payment_method,
	jQuery: window.jQuery,
} );
