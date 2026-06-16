/* global PayPalCommerceGateway */

import CheckoutActionHandler from '../ActionHandler/CheckoutActionHandler';
import { setVisible, setVisibleByClass } from '../Helper/Hiding';
import {
	getCurrentPaymentMethod,
	isSavedCardSelected,
	ORDER_BUTTON_SELECTOR,
	PaymentMethods,
} from '../Helper/CheckoutMethodState';
import BootstrapHelper from '../Helper/BootstrapHelper';
import { addPaymentMethodConfiguration } from '../../../../../ppcp-save-payment-methods/resources/js/configuration';
import {
	ButtonEvents,
	dispatchButtonEvent,
} from '../Helper/PaymentButtonHelpers';
import VaultRenderer from '../Renderer/VaultRenderer';

class CheckoutBootstrap {
	constructor( gateway, renderer, spinner, errorHandler ) {
		this.gateway = gateway;
		this.renderer = renderer;
		this.spinner = spinner;
		this.errorHandler = errorHandler;

		this.standardOrderButtonSelector = ORDER_BUTTON_SELECTOR;

		this.vaultRenderer = PayPalCommerceGateway.vault_component?.is_eligible
			? new VaultRenderer( PayPalCommerceGateway )
			: null;
		this.approvedVaultOrderId = null;

		this.renderer.onButtonsInit(
			this.gateway.button.wrapper,
			() => {
				this.handleButtonStatus();
			},
			true
		);
	}

	init() {
		this.render();
		this.handleButtonStatus();

		// Unselect saved card.
		// WC saves form values, so with our current UI it would be a bit weird
		// if the user paid with saved, then after some time tries to pay again,
		// but wants to enter a new card, and to do that they have to choose “Select payment” in the list.
		jQuery( '#saved-credit-card' ).val(
			jQuery( '#saved-credit-card option:first' ).val()
		);

		jQuery( document.body ).on( 'updated_checkout', () => {
			if ( this.vaultRenderer ) {
				this.vaultRenderer.reset();
				this.approvedVaultOrderId = null;
				this.removeVaultOrderIdInput();
			}

			this.render();
			this.handleButtonStatus();

			if (
				this.shouldShowMessages() &&
				document.querySelector( this.gateway.messages.wrapper )
			) {
				// currently we need amount only for Pay Later
				fetch( this.gateway.ajax.cart_script_params.endpoint, {
					method: 'GET',
					credentials: 'same-origin',
				} )
					.then( ( result ) => result.json() )
					.then( ( result ) => {
						if ( ! result.success ) {
							return;
						}

						jQuery( document.body ).trigger(
							'ppcp_checkout_total_updated',
							[ result.data.amount ]
						);
					} );
			}
		} );

		jQuery( document.body ).on(
			'updated_checkout payment_method_selected',
			() => {
				this.invalidatePaymentMethods();
				this.updateUi();
			}
		);

		jQuery( document ).on( 'hosted_fields_loaded', () => {
			jQuery( '#saved-credit-card' ).on( 'change', () => {
				this.updateUi();
			} );
		} );

		jQuery( document ).on(
			'change',
			'input[name="wc-ppcp-gateway-payment-token"]',
			() => {
				this.updateUi();
			}
		);

		jQuery( document ).on( 'ppcp_should_show_messages', ( e, data ) => {
			if ( ! this.shouldShowMessages() ) {
				data.result = false;
			}
		} );

		this.updateUi();
	}

	handleButtonStatus() {
		BootstrapHelper.handleButtonStatus( this );
	}

	shouldRender() {
		if ( document.querySelector( this.gateway.button.cancel_wrapper ) ) {
			return false;
		}

		return (
			document.querySelector( this.gateway.button.wrapper ) !== null ||
			document.querySelector( this.gateway.hosted_fields.wrapper ) !==
				null
		);
	}

	shouldEnable() {
		return BootstrapHelper.shouldEnable( this );
	}

	render() {
		if ( ! this.shouldRender() ) {
			return;
		}
		if (
			document.querySelector(
				this.gateway.hosted_fields.wrapper + '>div'
			)
		) {
			document
				.querySelector( this.gateway.hosted_fields.wrapper + '>div' )
				.setAttribute( 'style', '' );
		}
		const actionHandler = new CheckoutActionHandler(
			PayPalCommerceGateway,
			this.errorHandler,
			this.spinner
		);

		if (
			PayPalCommerceGateway.data_client_id.has_subscriptions &&
			PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled
		) {
			let subscription_plan_id =
				PayPalCommerceGateway.subscription_plan_id;
			if (
				PayPalCommerceGateway.variable_paypal_subscription_variation_from_cart !==
				''
			) {
				subscription_plan_id =
					PayPalCommerceGateway.variable_paypal_subscription_variation_from_cart;
			}
			this.renderer.render(
				actionHandler.subscriptionsConfiguration(
					subscription_plan_id
				),
				{},
				actionHandler.configuration()
			);

			if ( ! PayPalCommerceGateway.subscription_product_allowed ) {
				this.gateway.button.is_disabled = true;
				this.handleButtonStatus();
			}

			return;
		}

		if ( PayPalCommerceGateway.is_free_trial_cart ) {
			this.renderer.render(
				addPaymentMethodConfiguration( PayPalCommerceGateway ),
				{},
				actionHandler.configuration()
			);
			return;
		}

		this.renderer.render(
			actionHandler.configuration(),
			{},
			actionHandler.configuration()
		);
	}

	invalidatePaymentMethods() {
		/**
		 * Custom JS event to notify other modules that the payment button on the checkout page
		 * has become irrelevant or invalid.
		 */
		dispatchButtonEvent( { event: ButtonEvents.INVALIDATE } );
	}

	updateUi() {
		const currentPaymentMethod = getCurrentPaymentMethod();
		const isPaypal = currentPaymentMethod === PaymentMethods.PAYPAL;
		const isCard = currentPaymentMethod === PaymentMethods.CARDS;
		const isSeparateButtonGateway = [ PaymentMethods.CARD_BUTTON ].includes(
			currentPaymentMethod
		);
		const isGooglePayMethod =
			currentPaymentMethod === PaymentMethods.GOOGLEPAY;
		const isApplePayMethod =
			currentPaymentMethod === PaymentMethods.APPLEPAY;
		const isSavedCard = isCard && isSavedCardSelected();
		const isNotOurGateway =
			! isPaypal &&
			! isCard &&
			! isSeparateButtonGateway &&
			! isGooglePayMethod &&
			! isApplePayMethod;
		const isFreeTrial = PayPalCommerceGateway.is_free_trial_cart;
		const hasVaultedPaypal =
			!! PayPalCommerceGateway.vaulted_paypal_email;
		const useSmartButtons = this.renderer.useSmartButtons ?? true;
		// A zero-total subscription cart (free trial or 100% coupon) must use the
		// save-without-purchase flow. The Vault Component is order-based and would
		// create a $0 order, which PayPal rejects with CANNOT_BE_ZERO_OR_NEGATIVE.
		const showVaultComponent =
			!! this.vaultRenderer && isPaypal && ! isFreeTrial;

		const paypalButtonWrappers = {
			...Object.entries( PayPalCommerceGateway.separate_buttons ).reduce(
				( result, [ k, data ] ) => {
					return { ...result, [ data.id ]: data.wrapper };
				},
				{}
			),
		};

		setVisibleByClass(
			this.standardOrderButtonSelector,
			( isPaypal && isFreeTrial && hasVaultedPaypal ) ||
				// On a zero-total cart the Vault Component is disabled, so selecting a
				// saved PayPal token must show the standard "Place order" button. The
				// saved token completes via process_payment's free-trial short-circuit.
				( isPaypal &&
					isFreeTrial &&
					this.isSavedPayPalTokenSelected() ) ||
				isNotOurGateway ||
				isSavedCard ||
				( isPaypal && ! useSmartButtons ) ||
				( showVaultComponent && ! this.isNewPaymentMethodSelected() ),
			'ppcp-hidden'
		);
		this.updatePlaceOrderButtonText( showVaultComponent );
		setVisible( '.ppcp-vaulted-paypal-details', isPaypal );
		setVisible(
			this.gateway.button.wrapper,
			isPaypal &&
				! ( isFreeTrial && hasVaultedPaypal ) &&
				this.isNewPaymentMethodSelected()
		);
		if ( showVaultComponent && ! this.vaultRenderer.isRendered() ) {
			this.vaultRenderer.render(
				( orderID ) => {
					this.approvedVaultOrderId = orderID;
					this.injectVaultOrderIdInput( orderID );
				},
				() => {
					this.approvedVaultOrderId = null;
					this.removeVaultOrderIdInput();
				}
			);
		} else if ( ! showVaultComponent && this.vaultRenderer ) {
			this.vaultRenderer.close();
			this.approvedVaultOrderId = null;
			this.removeVaultOrderIdInput();
		}
		setVisible(
			this.gateway.hosted_fields.wrapper,
			isCard && ! isSavedCard
		);
		for ( const [ gatewayId, wrapper ] of Object.entries(
			paypalButtonWrappers
		) ) {
			setVisible( wrapper, gatewayId === currentPaymentMethod );
		}

		if ( isCard ) {
			if ( isSavedCard ) {
				this.disableCreditCardFields();
			} else {
				this.enableCreditCardFields();
			}
		}

		/**
		 * Custom JS event that is observed by the relevant payment gateway.
		 *
		 * Dynamic part of the event name is the payment method ID, for example
		 * "ppcp-credit-card-gateway" or "ppcp-googlepay"
		 */
		dispatchButtonEvent( {
			event: ButtonEvents.RENDER,
			paymentMethod: currentPaymentMethod,
		} );

		setVisible( '#ppc-button-ppcp-applepay', isApplePayMethod );

		document.body.dispatchEvent( new Event( 'ppcp_checkout_rendered' ) );
	}

	shouldShowMessages() {
		// hide when another method selected only if messages are near buttons
		const messagesWrapper = document.querySelector(
			this.gateway.messages.wrapper
		);
		if (
			getCurrentPaymentMethod() !== PaymentMethods.PAYPAL &&
			messagesWrapper &&
			jQuery( messagesWrapper ).closest( '.ppc-button-wrapper' ).length
		) {
			return false;
		}

		return ! PayPalCommerceGateway.is_free_trial_cart;
	}

	disableCreditCardFields() {
		jQuery( 'label[for="ppcp-credit-card-gateway-card-number"]' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-gateway-card-number' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( 'label[for="ppcp-credit-card-gateway-card-expiry"]' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-gateway-card-expiry' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( 'label[for="ppcp-credit-card-gateway-card-cvc"]' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-gateway-card-cvc' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( 'label[for="vault"]' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-vault' ).addClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-vault' ).attr( 'disabled', true );
		this.renderer.disableCreditCardFields();
	}

	isSavedPayPalTokenSelected() {
		const checkedRadio = document.querySelector(
			'input[name="wc-ppcp-gateway-payment-token"]:checked'
		);
		return (
			checkedRadio &&
			checkedRadio.value &&
			checkedRadio.value !== 'new'
		);
	}

	updatePlaceOrderButtonText( showVaultComponent ) {
		const $placeOrder = jQuery( this.standardOrderButtonSelector );
		if ( ! $placeOrder.length ) {
			return;
		}

		if ( showVaultComponent && ! this.isNewPaymentMethodSelected() ) {
			// The saved-token vault flow approves the order in-page, so the
			// standard "Place order" label fits — clicking does not redirect.
			$placeOrder.text( $placeOrder.data( 'value' ) );
			return;
		}

		// Replicate WooCommerce core: gateway-specific label, else default.
		const gatewayButtonText = jQuery(
			'input[name="payment_method"]:checked'
		).data( 'order_button_text' );
		$placeOrder.text( gatewayButtonText || $placeOrder.data( 'value' ) );
	}

	isNewPaymentMethodSelected() {
		const radios = document.querySelectorAll(
			'input[name="wc-ppcp-gateway-payment-token"]'
		);
		// No saved-token selector on the page (guest checkout or vaulting
		// disabled) means there is nothing to choose from, so the payment is
		// always a "new" one and the smart button must be shown.
		if ( radios.length === 0 ) {
			return true;
		}
		const checkedRadio = document.querySelector(
			'input[name="wc-ppcp-gateway-payment-token"]:checked'
		);
		return checkedRadio?.value === 'new';
	}

	injectVaultOrderIdInput( orderID ) {
		const form =
			document.querySelector( 'form.checkout' ) ||
			document.querySelector( 'form#order_review' );
		if ( ! form ) {
			return;
		}

		let input = form.querySelector( 'input[name="paypal_order_id"]' );
		if ( ! input ) {
			input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = 'paypal_order_id';
			form.appendChild( input );
		}
		input.value = orderID;
	}

	removeVaultOrderIdInput() {
		const input = document.querySelector(
			'input[name="paypal_order_id"]'
		);
		if ( input ) {
			input.remove();
		}
	}

	enableCreditCardFields() {
		jQuery(
			'label[for="ppcp-credit-card-gateway-card-number"]'
		).removeClass( 'ppcp-credit-card-gateway-form-field-disabled' );
		jQuery( '#ppcp-credit-card-gateway-card-number' ).removeClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery(
			'label[for="ppcp-credit-card-gateway-card-expiry"]'
		).removeClass( 'ppcp-credit-card-gateway-form-field-disabled' );
		jQuery( '#ppcp-credit-card-gateway-card-expiry' ).removeClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( 'label[for="ppcp-credit-card-gateway-card-cvc"]' ).removeClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-gateway-card-cvc' ).removeClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( 'label[for="vault"]' ).removeClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-vault' ).removeClass(
			'ppcp-credit-card-gateway-form-field-disabled'
		);
		jQuery( '#ppcp-credit-card-vault' ).attr( 'disabled', false );
		this.renderer.enableCreditCardFields();
	}
}

export default CheckoutBootstrap;
