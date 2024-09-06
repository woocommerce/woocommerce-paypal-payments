/* global ApplePaySession */
/* global PayPalCommerceGateway */

import ContextHandlerFactory from './Context/ContextHandlerFactory';
import { createAppleErrors } from './Helper/applePayError';
import { setVisible } from '../../../ppcp-button/resources/js/modules/Helper/Hiding';
import { setEnabled } from '../../../ppcp-button/resources/js/modules/Helper/ButtonDisabler';
import FormValidator from '../../../ppcp-button/resources/js/modules/Helper/FormValidator';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import widgetBuilder from '../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder';
import { apmButtonsInit } from '../../../ppcp-button/resources/js/modules/Helper/ApmButtons';

/**
 * Plugin-specific styling.
 *
 * Note that most properties of this object do not apply to the Apple Pay button.
 *
 * @typedef {Object} PPCPStyle
 * @property {string}  shape  - Outline shape.
 * @property {?number} height - Button height in pixel.
 */

/**
 * Style options that are defined by the Apple Pay SDK and are required to render the button.
 *
 * @typedef {Object} ApplePayStyle
 * @property {string} type  - Defines the button label.
 * @property {string} color - Button color
 * @property {string} lang  - The locale; an empty string will apply the user-agent's language.
 */

/**
 * List of valid context values that the button can have.
 *
 * @type {Object}
 */
const CONTEXT = {
	Product: 'product',
	Cart: 'cart',
	Checkout: 'checkout',
	PayNow: 'pay-now',
	MiniCart: 'mini-cart',
	BlockCart: 'cart-block',
	BlockCheckout: 'checkout-block',
	Preview: 'preview',

	// Block editor contexts.
	Blocks: [ 'cart-block', 'checkout-block' ],

	// Custom gateway contexts.
	Gateways: [ 'checkout', 'pay-now' ],
};

/**
 * A payment button for Apple Pay.
 *
 * On a single page, multiple Apple Pay buttons can be displayed, which also means multiple
 * ApplePayButton instances exist. A typical case is on the product page, where one Apple Pay button
 * is located inside the minicart-popup, and another pay-now button is in the product context.
 *
 * TODO - extend from PaymentButton (same as we do in GooglepayButton.js)
 */
class ApplePayButton {
	/**
	 * Whether the payment button is initialized.
	 *
	 * @type {boolean}
	 */
	#isInitialized = false;

	#wrapperId = '';
	#ppcpButtonWrapperId = '';

	/**
	 * Context describes the button's location on the website and what details it submits.
	 *
	 * @type {''|'product'|'cart'|'checkout'|'pay-now'|'mini-cart'|'cart-block'|'checkout-block'|'preview'}
	 */
	context = '';

	externalHandler = null;
	buttonConfig = null;
	ppcpConfig = null;
	paymentsClient = null;
	formData = null;
	contextHandler = null;
	updatedContactInfo = [];
	selectedShippingMethod = [];

	/**
	 * Stores initialization data sent to the button.
	 */
	initialPaymentRequest = null;

	constructor( context, externalHandler, buttonConfig, ppcpConfig ) {
		this._initDebug( !! buttonConfig?.is_debug );

		apmButtonsInit( ppcpConfig );

		this.context = context;
		this.externalHandler = externalHandler;
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;

		this.contextHandler = ContextHandlerFactory.create(
			this.context,
			this.buttonConfig,
			this.ppcpConfig
		);

		this.refreshContextData();
	}

	/**
	 * NOOP log function to avoid errors when debugging is disabled.
	 */
	log() {}

	/**
	 * Enables debugging tools, when the button's is_debug flag is set.
	 *
	 * @param {boolean} enableDebugging If debugging features should be enabled for this instance.
	 * @private
	 */
	_initDebug( enableDebugging ) {
		if ( ! enableDebugging || this.#isInitialized ) {
			return;
		}

		document.ppcpApplepayButtons = document.ppcpApplepayButtons || {};
		document.ppcpApplepayButtons[ this.context ] = this;

		this.log = ( ...args ) => {
			console.log( `[ApplePayButton | ${ this.context }]`, ...args );
		};

		jQuery( document ).on( 'ppcp-applepay-debug', () => {
			this.log( this );
		} );
	}

	/**
	 * The nonce for ajax requests.
	 *
	 * @return {string} The nonce value
	 */
	get nonce() {
		const input = document.getElementById(
			'woocommerce-process-checkout-nonce'
		);

		return input?.value || this.buttonConfig.nonce;
	}

	/**
	 * Whether the current page qualifies to use the Apple Pay button.
	 *
	 * In admin, the button is always eligible, to display an accurate preview.
	 * On front-end, PayPal's response decides if customers can use Apple Pay.
	 *
	 * @return {boolean} True, if the button can be displayed.
	 */
	get isEligible() {
		if ( ! this.#isInitialized ) {
			return true;
		}

		if ( CONTEXT.Preview === this.context ) {
			return true;
		}

		/**
		 * Ensure the ApplePaySession is available and accepts payments
		 * This check is required when using Apple Pay SDK v1; canMakePayments() returns false
		 * if the current device is not liked to iCloud or the Apple Wallet is not available
		 * for a different reason.
		 */
		try {
			if ( ! window.ApplePaySession?.canMakePayments() ) {
				return false;
			}
		} catch ( error ) {
			console.warn( error );
			return false;
		}

		return !! this.applePayConfig.isEligible;
	}

	/**
	 * Determines if the current payment button should be rendered as a stand-alone gateway.
	 * The return value `false` usually means, that the payment button is bundled with all available
	 * payment buttons.
	 *
	 * The decision depends on the button context (placement) and the plugin settings.
	 *
	 * @return {boolean} True, if the current button represents a stand-alone gateway.
	 */
	get isSeparateGateway() {
		return (
			this.buttonConfig.is_wc_gateway_enabled &&
			CONTEXT.Gateways.includes( this.context )
		);
	}

	/**
	 * Returns the wrapper ID for the current button context.
	 * The ID varies for the MiniCart context.
	 *
	 * @return {string} The wrapper-element's ID (without the `#` prefix).
	 */
	get wrapperId() {
		if ( ! this.#wrapperId ) {
			let id;

			if ( CONTEXT.MiniCart === this.context ) {
				id = this.buttonConfig.button.mini_cart_wrapper;
			} else if ( this.isSeparateGateway ) {
				id = 'ppc-button-ppcp-applepay';
			} else {
				id = this.buttonConfig.button.wrapper;
			}

			this.#wrapperId = id.replace( /^#/, '' );
		}

		return this.#wrapperId;
	}

	/**
	 * Returns the wrapper ID for the ppcpButton
	 *
	 * @return {string} The wrapper-element's ID (without the `#` prefix).
	 */
	get ppcpButtonWrapperId() {
		if ( ! this.#ppcpButtonWrapperId ) {
			let id;

			if ( CONTEXT.MiniCart === this.context ) {
				id = this.ppcpConfig.button.mini_cart_wrapper;
			} else if ( CONTEXT.Blocks.includes( this.context ) ) {
				id = '#express-payment-method-ppcp-gateway-paypal';
			} else {
				id = this.ppcpConfig.button.wrapper;
			}

			this.#ppcpButtonWrapperId = id.replace( /^#/, '' );
		}

		return this.#ppcpButtonWrapperId;
	}

	/**
	 * Returns the context-relevant PPCP style object.
	 * The style for the MiniCart context can be different.
	 *
	 * The PPCP style are custom style options, that are provided by this plugin.
	 *
	 * @return {PPCPStyle} The style object.
	 */
	get ppcpStyle() {
		if ( CONTEXT.MiniCart === this.context ) {
			return this.ppcpConfig.button.mini_cart_style;
		}

		return this.ppcpConfig.button.style;
	}

	/**
	 * Returns default style options that are propagated to and rendered by the Apple Pay button.
	 *
	 * These styles are the official style options provided by the Apple Pay SDK.
	 *
	 * @return {ApplePayStyle} The style object.
	 */
	get buttonStyle() {
		return {
			type: this.buttonConfig.button.type,
			lang: this.buttonConfig.button.lang,
			color: this.buttonConfig.button.color,
		};
	}

	/**
	 * Returns the HTML element that wraps the current button
	 *
	 * @return {HTMLElement|null} The wrapper element, or null.
	 */
	get wrapperElement() {
		return document.getElementById( this.wrapperId );
	}

	/**
	 * Returns an array of HTMLElements that belong to the payment button.
	 *
	 * @return {HTMLElement[]} List of payment button wrapper elements.
	 */
	get allElements() {
		const selectors = [];

		// Payment button (Pay now, smart button block)
		selectors.push( `#${ this.wrapperId }` );

		// Block Checkout: Express checkout button.
		if ( CONTEXT.Blocks.includes( this.context ) ) {
			selectors.push( '#express-payment-method-ppcp-applepay' );
		}

		// Classic Checkout: Apple Pay gateway.
		if ( CONTEXT.Gateways.includes( this.context ) ) {
			selectors.push( '.wc_payment_method.payment_method_ppcp-applepay' );
		}

		this.log( 'Wrapper Elements:', selectors );
		return /** @type {HTMLElement[]} */ selectors.flatMap( ( selector ) =>
			Array.from( document.querySelectorAll( selector ) )
		);
	}

	/**
	 * Checks whether the main button-wrapper is present in the current DOM.
	 *
	 * @return {boolean} True, if the button context (wrapper element) is found.
	 */
	get isPresent() {
		return this.wrapperElement instanceof HTMLElement;
	}

	init( config ) {
		if ( this.#isInitialized ) {
			return;
		}

		if ( ! this.contextHandler.validateContext() ) {
			return;
		}

		this.log( 'Init' );
		this.initEventHandlers();

		this.#isInitialized = true;
		this.applePayConfig = config;

		if ( this.isSeparateGateway ) {
			document
				.querySelectorAll( '#ppc-button-applepay-container' )
				.forEach( ( el ) => el.remove() );
		}

		if ( ! this.isEligible ) {
			this.hide();
		} else {
			// Bail if the button wrapper is not present; handles mini-cart logic on checkout page.
			if ( ! this.isPresent ) {
				this.log( 'Abort init (no wrapper found)' );
				return;
			}

			this.show();

			this.fetchTransactionInfo().then( () => {
				const button = this.addButton();

				if ( ! button ) {
					return;
				}

				button.addEventListener( 'click', ( evt ) => {
					evt.preventDefault();
					this.onButtonClick();
				} );
			} );
		}
	}

	reinit() {
		if ( ! this.applePayConfig ) {
			return;
		}

		this.#isInitialized = false;
		this.init( this.applePayConfig );
	}

	/**
	 * Hides all wrappers that belong to this ApplePayButton instance.
	 */
	hide() {
		this.log( 'Hide button' );
		this.allElements.forEach( ( element ) => {
			element.style.display = 'none';
		} );
	}

	/**
	 * Ensures all wrapper elements of this ApplePayButton instance are visible.
	 */
	show() {
		this.log( 'Show button' );
		if ( ! this.isPresent ) {
			this.log( '!! Cannot show button, wrapper is not present' );
			return;
		}

		// Classic Checkout/PayNow: Make the Apple Pay gateway visible after page load.
		document
			.querySelectorAll( 'style#ppcp-hide-apple-pay' )
			.forEach( ( el ) => el.remove() );

		this.allElements.forEach( ( element ) => {
			element.style.display = '';
		} );
	}

	async fetchTransactionInfo() {
		this.transactionInfo = await this.contextHandler.transactionInfo();
	}

	initEventHandlers() {
		const ppcpButtonWrapper = `#${ this.ppcpButtonWrapperId }`;
		const wrapperId = `#${ this.wrapperId }`;

		if ( wrapperId === ppcpButtonWrapper ) {
			throw new Error(
				`[ApplePayButton] "wrapper" and "ppcpButtonWrapper" values must differ to avoid infinite loop. Current value: "${ wrapperId }"`
			);
		}

		const syncButtonVisibility = () => {
			if ( ! this.isEligible ) {
				return;
			}

			const $ppcpButtonWrapper = jQuery( ppcpButtonWrapper );
			setVisible( wrapperId, $ppcpButtonWrapper.is( ':visible' ) );
			setEnabled(
				wrapperId,
				! $ppcpButtonWrapper.hasClass( 'ppcp-disabled' )
			);
		};

		jQuery( document ).on(
			'ppcp-shown ppcp-hidden ppcp-enabled ppcp-disabled',
			( ev, data ) => {
				if ( jQuery( data.selector ).is( ppcpButtonWrapper ) ) {
					syncButtonVisibility();
				}
			}
		);

		syncButtonVisibility();
	}

	/**
	 * Starts an Apple Pay session.
	 *
	 * @param {Object} paymentRequest The payment request object.
	 */
	applePaySession( paymentRequest ) {
		this.log( 'applePaySession', paymentRequest );
		const session = new ApplePaySession( 4, paymentRequest );
		session.begin();

		if ( this.shouldRequireShippingInButton() ) {
			session.onshippingmethodselected =
				this.onShippingMethodSelected( session );
			session.onshippingcontactselected =
				this.onShippingContactSelected( session );
		}

		session.onvalidatemerchant = this.onValidateMerchant( session );
		session.onpaymentauthorized = this.onPaymentAuthorized( session );
		return session;
	}

	/**
	 * Adds an Apple Pay purchase button.
	 *
	 * @return {HTMLElement|null} The newly created `<apple-pay-button>` element. Null on failure.
	 */
	addButton() {
		this.log( 'addButton' );

		const wrapper = this.wrapperElement;
		const style = this.buttonStyle;
		const id = 'apple-' + this.wrapperId;

		if ( ! wrapper ) {
			return null;
		}

		const ppcpStyle = this.ppcpStyle;

		wrapper.innerHTML = `<apple-pay-button id='${ id }' buttonstyle='${ style.color }' type='${ style.type }' locale='${ style.lang }' />`;
		wrapper.classList.remove( 'ppcp-button-rect', 'ppcp-button-pill' );
		wrapper.classList.add(
			`ppcp-button-${ ppcpStyle.shape }`,
			'ppcp-button-apm',
			'ppcp-button-applepay'
		);

		if ( ppcpStyle.height ) {
			wrapper.style.setProperty(
				'--apple-pay-button-height',
				`${ ppcpStyle.height }px`
			);
			wrapper.style.height = `${ ppcpStyle.height }px`;
		}

		return wrapper.querySelector( 'apple-pay-button' );
	}

	//------------------------
	// Button click
	//------------------------

	/**
	 * Show Apple Pay payment sheet when Apple Pay payment button is clicked
	 */
	async onButtonClick() {
		this.log( 'onButtonClick' );

		const paymentRequest = this.paymentRequest();

		// Do this on another place like on create order endpoint handler.
		window.ppcpFundingSource = 'apple_pay';

		// Trigger woocommerce validation if we are in the checkout page.
		if ( CONTEXT.Checkout === this.context ) {
			const checkoutFormSelector = 'form.woocommerce-checkout';
			const errorHandler = new ErrorHandler(
				PayPalCommerceGateway.labels.error.generic,
				document.querySelector( '.woocommerce-notices-wrapper' )
			);

			try {
				const formData = new FormData(
					document.querySelector( checkoutFormSelector )
				);
				this.formData = Object.fromEntries( formData.entries() );

				this.updateRequestDataWithForm( paymentRequest );
			} catch ( error ) {
				console.error( error );
			}

			this.log( '=== paymentRequest', paymentRequest );

			const session = this.applePaySession( paymentRequest );
			const formValidator =
				PayPalCommerceGateway.early_checkout_validation_enabled
					? new FormValidator(
							PayPalCommerceGateway.ajax.validate_checkout.endpoint,
							PayPalCommerceGateway.ajax.validate_checkout.nonce
					  )
					: null;

			if ( formValidator ) {
				try {
					const errors = await formValidator.validate(
						document.querySelector( checkoutFormSelector )
					);
					if ( errors.length > 0 ) {
						errorHandler.messages( errors );
						jQuery( document.body ).trigger( 'checkout_error', [
							errorHandler.currentHtml(),
						] );
						session.abort();
						return;
					}
				} catch ( error ) {
					console.error( error );
				}
			}
			return;
		}

		// Default session initialization.
		this.applePaySession( paymentRequest );
	}

	/**
	 * If the button should show the shipping fields.
	 *
	 * @return {boolean} True, if shipping fields should be captured by ApplePay.
	 */
	shouldRequireShippingInButton() {
		return (
			this.contextHandler.shippingAllowed() &&
			this.buttonConfig.product.needShipping &&
			( CONTEXT.Checkout !== this.context ||
				this.shouldUpdateButtonWithFormData() )
		);
	}

	/**
	 * If the button should be updated with the form addresses.
	 *
	 * @return {boolean} True, when Apple Pay data should be submitted to WooCommerce.
	 */
	shouldUpdateButtonWithFormData() {
		if ( CONTEXT.Checkout !== this.context ) {
			return false;
		}
		return (
			this.buttonConfig?.preferences?.checkout_data_mode ===
			'use_applepay'
		);
	}

	/**
	 * Indicates how payment completion should be handled if with the context handler default
	 * actions. Or with Apple Pay module specific completion.
	 *
	 * @return {boolean} True, when the Apple Pay data should be submitted to WooCommerce.
	 */
	shouldCompletePaymentWithContextHandler() {
		// Data already handled, ex: PayNow
		if ( ! this.contextHandler.shippingAllowed() ) {
			return true;
		}

		// Use WC form data mode in Checkout.
		return (
			CONTEXT.Checkout === this.context &&
			! this.shouldUpdateButtonWithFormData()
		);
	}

	/**
	 * Updates Apple Pay paymentRequest with form data.
	 *
	 * @param {Object} paymentRequest Object to extend with form data.
	 */
	updateRequestDataWithForm( paymentRequest ) {
		if ( ! this.shouldUpdateButtonWithFormData() ) {
			return;
		}

		// Add billing address.
		paymentRequest.billingContact = this.fillBillingContact(
			this.formData
		);

		// Add custom data.
		// "applicationData" is originating a "PayPalApplePayError: An internal server error has
		// occurred" on paypal.Applepay().confirmOrder(). paymentRequest.applicationData =
		// this.fillApplicationData(this.formData);

		if ( ! this.shouldRequireShippingInButton() ) {
			return;
		}

		// Add shipping address.
		paymentRequest.shippingContact = this.fillShippingContact(
			this.formData
		);

		// Get shipping methods.
		const rate = this.transactionInfo.chosenShippingMethods[ 0 ];
		paymentRequest.shippingMethods = [];

		// Add selected shipping method.
		for ( const shippingPackage of this.transactionInfo.shippingPackages ) {
			if ( rate === shippingPackage.id ) {
				const shippingMethod = {
					label: shippingPackage.label,
					detail: '',
					amount: shippingPackage.cost_str,
					identifier: shippingPackage.id,
				};

				// Remember this shipping method as the selected one.
				this.selectedShippingMethod = shippingMethod;

				paymentRequest.shippingMethods.push( shippingMethod );
				break;
			}
		}

		// Add other shipping methods.
		for ( const shippingPackage of this.transactionInfo.shippingPackages ) {
			if ( rate !== shippingPackage.id ) {
				paymentRequest.shippingMethods.push( {
					label: shippingPackage.label,
					detail: '',
					amount: shippingPackage.cost_str,
					identifier: shippingPackage.id,
				} );
			}
		}

		// Store for reuse in case this data is not provided by ApplePay on authorization.
		this.initialPaymentRequest = paymentRequest;

		this.log(
			'=== paymentRequest.shippingMethods',
			paymentRequest.shippingMethods
		);
	}

	paymentRequest() {
		const applepayConfig = this.applePayConfig;
		const buttonConfig = this.buttonConfig;
		const baseRequest = {
			countryCode: applepayConfig.countryCode,
			merchantCapabilities: applepayConfig.merchantCapabilities,
			supportedNetworks: applepayConfig.supportedNetworks,
			requiredShippingContactFields: [
				'postalAddress',
				'email',
				'phone',
			],
			requiredBillingContactFields: [ 'postalAddress' ], // ApplePay does not implement billing
			// email and phone fields.
		};

		if ( ! this.shouldRequireShippingInButton() ) {
			if ( this.shouldCompletePaymentWithContextHandler() ) {
				// Data needs handled externally.
				baseRequest.requiredShippingContactFields = [];
			} else {
				// Minimum data required for order creation.
				baseRequest.requiredShippingContactFields = [
					'email',
					'phone',
				];
			}
		}

		const paymentRequest = Object.assign( {}, baseRequest );
		paymentRequest.currencyCode = buttonConfig.shop.currencyCode;
		paymentRequest.total = {
			label: buttonConfig.shop.totalLabel,
			type: 'final',
			amount: this.transactionInfo.totalPrice,
		};

		return paymentRequest;
	}

	refreshContextData() {
		if ( CONTEXT.Product === this.context ) {
			// Refresh product data that makes the price change.
			this.productQuantity = document.querySelector( 'input.qty' )?.value;
			this.products = this.contextHandler.products();
			this.log( 'Products updated', this.products );
		}
	}

	//------------------------
	// Payment process
	//------------------------

	/**
	 * Make ajax call to change the verification-status of the current domain.
	 *
	 * @param {boolean} isValid
	 */
	adminValidation( isValid ) {
		// eslint-disable-next-line no-unused-vars
		const ignored = fetch( this.buttonConfig.ajax_url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: new URLSearchParams( {
				action: 'ppcp_validate',
				'woocommerce-process-checkout-nonce': this.nonce,
				validation: isValid,
			} ).toString(),
		} );
	}

	/**
	 * Returns an event handler that Apple Pay calls when displaying the payment sheet.
	 *
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778021-onvalidatemerchant
	 *
	 * @param {Object} session The ApplePaySession object.
	 *
	 * @return {(function(*): void)|*} Callback that runs after the merchant validation
	 */
	onValidateMerchant( session ) {
		return ( applePayValidateMerchantEvent ) => {
			this.log( 'onvalidatemerchant call' );

			widgetBuilder.paypal
				.Applepay()
				.validateMerchant( {
					validationUrl: applePayValidateMerchantEvent.validationURL,
				} )
				.then( ( validateResult ) => {
					session.completeMerchantValidation(
						validateResult.merchantSession
					);

					this.adminValidation( true );
				} )
				.catch( ( validateError ) => {
					console.error( validateError );
					this.adminValidation( false );
					this.log( 'onvalidatemerchant session abort' );
					session.abort();
				} );
		};
	}

	onShippingMethodSelected( session ) {
		this.log( 'onshippingmethodselected', this.buttonConfig.ajax_url );
		const ajaxUrl = this.buttonConfig.ajax_url;
		return ( event ) => {
			this.log( 'onshippingmethodselected call' );

			const data = this.getShippingMethodData( event );

			jQuery.ajax( {
				url: ajaxUrl,
				method: 'POST',
				data,
				success: (
					applePayShippingMethodUpdate,
					textStatus,
					jqXHR
				) => {
					this.log( 'onshippingmethodselected ok' );
					const response = applePayShippingMethodUpdate.data;
					if ( applePayShippingMethodUpdate.success === false ) {
						response.errors = createAppleErrors( response.errors );
					}
					this.selectedShippingMethod = event.shippingMethod;

					// Sort the response shipping methods, so that the selected shipping method is
					// the first one.
					response.newShippingMethods =
						response.newShippingMethods.sort( ( a, b ) => {
							if (
								a.label === this.selectedShippingMethod.label
							) {
								return -1;
							}
							return 1;
						} );

					if ( applePayShippingMethodUpdate.success === false ) {
						response.errors = createAppleErrors( response.errors );
					}
					session.completeShippingMethodSelection( response );
				},
				error: ( jqXHR, textStatus, errorThrown ) => {
					this.log( 'onshippingmethodselected error', textStatus );
					console.warn( textStatus, errorThrown );
					session.abort();
				},
			} );
		};
	}

	onShippingContactSelected( session ) {
		this.log( 'onshippingcontactselected', this.buttonConfig.ajax_url );

		const ajaxUrl = this.buttonConfig.ajax_url;

		return ( event ) => {
			this.log( 'onshippingcontactselected call' );

			const data = this.getShippingContactData( event );

			jQuery.ajax( {
				url: ajaxUrl,
				method: 'POST',
				data,
				success: (
					applePayShippingContactUpdate,
					textStatus,
					jqXHR
				) => {
					this.log( 'onshippingcontactselected ok' );
					const response = applePayShippingContactUpdate.data;
					this.updatedContactInfo = event.shippingContact;
					if ( applePayShippingContactUpdate.success === false ) {
						response.errors = createAppleErrors( response.errors );
					}
					if ( response.newShippingMethods ) {
						this.selectedShippingMethod =
							response.newShippingMethods[ 0 ];
					}
					session.completeShippingContactSelection( response );
				},
				error: ( jqXHR, textStatus, errorThrown ) => {
					this.log( 'onshippingcontactselected error', textStatus );
					console.warn( textStatus, errorThrown );
					session.abort();
				},
			} );
		};
	}

	getShippingContactData( event ) {
		const productId = this.buttonConfig.product.id;

		this.refreshContextData();

		switch ( this.context ) {
			case CONTEXT.Product:
				return {
					action: 'ppcp_update_shipping_contact',
					product_id: productId,
					products: JSON.stringify( this.products ),
					caller_page: 'productDetail',
					product_quantity: this.productQuantity,
					simplified_contact: event.shippingContact,
					need_shipping: this.shouldRequireShippingInButton(),
					'woocommerce-process-checkout-nonce': this.nonce,
				};

			case CONTEXT.Cart:
			case CONTEXT.Checkout:
			case CONTEXT.BlockCart:
			case CONTEXT.BlockCheckout:
			case CONTEXT.MiniCart:
				return {
					action: 'ppcp_update_shipping_contact',
					simplified_contact: event.shippingContact,
					caller_page: 'cart',
					need_shipping: this.shouldRequireShippingInButton(),
					'woocommerce-process-checkout-nonce': this.nonce,
				};
		}
	}

	getShippingMethodData( event ) {
		const productId = this.buttonConfig.product.id;

		this.refreshContextData();

		switch ( this.context ) {
			case CONTEXT.Product:
				return {
					action: 'ppcp_update_shipping_method',
					shipping_method: event.shippingMethod,
					simplified_contact: this.hasValidContactInfo(
						this.updatedContactInfo
					)
						? this.updatedContactInfo
						: this.initialPaymentRequest?.shippingContact ??
						  this.initialPaymentRequest?.billingContact,
					product_id: productId,
					products: JSON.stringify( this.products ),
					caller_page: 'productDetail',
					product_quantity: this.productQuantity,
					'woocommerce-process-checkout-nonce': this.nonce,
				};

			case CONTEXT.Cart:
			case CONTEXT.Checkout:
			case CONTEXT.BlockCart:
			case CONTEXT.BlockCheckout:
			case CONTEXT.MiniCart:
				return {
					action: 'ppcp_update_shipping_method',
					shipping_method: event.shippingMethod,
					simplified_contact: this.hasValidContactInfo(
						this.updatedContactInfo
					)
						? this.updatedContactInfo
						: this.initialPaymentRequest?.shippingContact ??
						  this.initialPaymentRequest?.billingContact,
					caller_page: 'cart',
					'woocommerce-process-checkout-nonce': this.nonce,
				};
		}
	}

	onPaymentAuthorized( session ) {
		this.log( 'onpaymentauthorized' );
		return async ( event ) => {
			this.log( 'onpaymentauthorized call' );

			const processInWooAndCapture = async ( data ) => {
				return new Promise( ( resolve, reject ) => {
					try {
						const billingContact =
							data.billing_contact ||
							this.initialPaymentRequest.billingContact;
						const shippingContact =
							data.shipping_contact ||
							this.initialPaymentRequest.shippingContact;
						const shippingMethod =
							this.selectedShippingMethod ||
							( this.initialPaymentRequest.shippingMethods ||
								[] )[ 0 ];

						const requestData = {
							action: 'ppcp_create_order',
							caller_page: this.context,
							product_id: this.buttonConfig.product.id ?? null,
							products: JSON.stringify( this.products ),
							product_quantity: this.productQuantity ?? null,
							shipping_contact: shippingContact,
							billing_contact: billingContact,
							token: event.payment.token,
							shipping_method: shippingMethod,
							'woocommerce-process-checkout-nonce': this.nonce,
							funding_source: 'applepay',
							_wp_http_referer: '/?wc-ajax=update_order_review',
							paypal_order_id: data.paypal_order_id,
						};

						this.log(
							'onpaymentauthorized request',
							this.buttonConfig.ajax_url,
							data
						);

						jQuery.ajax( {
							url: this.buttonConfig.ajax_url,
							method: 'POST',
							data: requestData,
							complete: ( jqXHR, textStatus ) => {
								this.log( 'onpaymentauthorized complete' );
							},
							success: (
								authorizationResult,
								textStatus,
								jqXHR
							) => {
								this.log( 'onpaymentauthorized ok' );
								resolve( authorizationResult );
							},
							error: ( jqXHR, textStatus, errorThrown ) => {
								this.log(
									'onpaymentauthorized error',
									textStatus
								);
								reject( new Error( errorThrown ) );
							},
						} );
					} catch ( error ) {
						this.log( 'onpaymentauthorized catch', error );
						console.log( error ); // handle error
					}
				} );
			};

			const id = await this.contextHandler.createOrder();

			this.log(
				'onpaymentauthorized paypal order ID',
				id,
				event.payment.token,
				event.payment.billingContact
			);

			try {
				const confirmOrderResponse = await widgetBuilder.paypal
					.Applepay()
					.confirmOrder( {
						orderId: id,
						token: event.payment.token,
						billingContact: event.payment.billingContact,
					} );

				this.log(
					'onpaymentauthorized confirmOrderResponse',
					confirmOrderResponse
				);

				if (
					confirmOrderResponse &&
					confirmOrderResponse.approveApplePayPayment
				) {
					if (
						confirmOrderResponse.approveApplePayPayment.status ===
						'APPROVED'
					) {
						try {
							if (
								this.shouldCompletePaymentWithContextHandler()
							) {
								// No shipping, expect immediate capture, ex: PayNow, Checkout with
								// form data.

								let approveFailed = false;
								await this.contextHandler.approveOrder(
									{
										orderID: id,
									},
									{
										// actions mock object.
										restart: () =>
											new Promise(
												( resolve, reject ) => {
													approveFailed = true;
													resolve();
												}
											),
										order: {
											get: () =>
												new Promise(
													( resolve, reject ) => {
														resolve( null );
													}
												),
										},
									}
								);

								if ( ! approveFailed ) {
									this.log(
										'onpaymentauthorized approveOrder OK'
									);
									session.completePayment(
										ApplePaySession.STATUS_SUCCESS
									);
								} else {
									this.log(
										'onpaymentauthorized approveOrder FAIL'
									);
									session.completePayment(
										ApplePaySession.STATUS_FAILURE
									);
									session.abort();
									console.error( error );
								}
							} else {
								// Default payment.

								const data = {
									billing_contact:
										event.payment.billingContact,
									shipping_contact:
										event.payment.shippingContact,
									paypal_order_id: id,
								};
								const authorizationResult =
									await processInWooAndCapture( data );
								if (
									authorizationResult.result === 'success'
								) {
									session.completePayment(
										ApplePaySession.STATUS_SUCCESS
									);
									window.location.href =
										authorizationResult.redirect;
								} else {
									session.completePayment(
										ApplePaySession.STATUS_FAILURE
									);
								}
							}
						} catch ( error ) {
							session.completePayment(
								ApplePaySession.STATUS_FAILURE
							);
							session.abort();
							console.error( error );
						}
					} else {
						console.error( 'Error status is not APPROVED' );
						session.completePayment(
							ApplePaySession.STATUS_FAILURE
						);
					}
				} else {
					console.error( 'Invalid confirmOrderResponse' );
					session.completePayment( ApplePaySession.STATUS_FAILURE );
				}
			} catch ( error ) {
				console.error(
					'Error confirming order with applepay token',
					error
				);
				session.completePayment( ApplePaySession.STATUS_FAILURE );
				session.abort();
			}
		};
	}

	fillBillingContact( data ) {
		return {
			givenName: data.billing_first_name ?? '',
			familyName: data.billing_last_name ?? '',
			emailAddress: data.billing_email ?? '',
			phoneNumber: data.billing_phone ?? '',
			addressLines: [ data.billing_address_1, data.billing_address_2 ],
			locality: data.billing_city ?? '',
			postalCode: data.billing_postcode ?? '',
			countryCode: data.billing_country ?? '',
			administrativeArea: data.billing_state ?? '',
		};
	}

	fillShippingContact( data ) {
		if ( data.shipping_first_name === '' ) {
			return this.fillBillingContact( data );
		}
		return {
			givenName:
				data?.shipping_first_name && data.shipping_first_name !== ''
					? data.shipping_first_name
					: data?.billing_first_name,
			familyName:
				data?.shipping_last_name && data.shipping_last_name !== ''
					? data.shipping_last_name
					: data?.billing_last_name,
			emailAddress:
				data?.shipping_email && data.shipping_email !== ''
					? data.shipping_email
					: data?.billing_email,
			phoneNumber:
				data?.shipping_phone && data.shipping_phone !== ''
					? data.shipping_phone
					: data?.billing_phone,
			addressLines: [
				data.shipping_address_1 ?? '',
				data.shipping_address_2 ?? '',
			],
			locality:
				data?.shipping_city && data.shipping_city !== ''
					? data.shipping_city
					: data?.billing_city,
			postalCode:
				data?.shipping_postcode && data.shipping_postcode !== ''
					? data.shipping_postcode
					: data?.billing_postcode,
			countryCode:
				data?.shipping_country && data.shipping_country !== ''
					? data.shipping_country
					: data?.billing_country,
			administrativeArea:
				data?.shipping_state && data.shipping_state !== ''
					? data.shipping_state
					: data?.billing_state,
		};
	}

	fillApplicationData( data ) {
		const jsonString = JSON.stringify( data );
		const utf8Str = encodeURIComponent( jsonString ).replace(
			/%([0-9A-F]{2})/g,
			( match, p1 ) => {
				return String.fromCharCode( '0x' + p1 );
			}
		);

		return btoa( utf8Str );
	}

	hasValidContactInfo( value ) {
		return Array.isArray( value )
			? value.length > 0
			: Object.keys( value || {} ).length > 0;
	}
}

export default ApplePayButton;
