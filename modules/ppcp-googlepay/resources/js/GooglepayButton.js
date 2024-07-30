/* global google */
/* global jQuery */

import { setVisible } from '../../../ppcp-button/resources/js/modules/Helper/Hiding';
import { setEnabled } from '../../../ppcp-button/resources/js/modules/Helper/ButtonDisabler';
import widgetBuilder from '../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder';
import UpdatePaymentData from './Helper/UpdatePaymentData';
import { apmButtonsInit } from '../../../ppcp-button/resources/js/modules/Helper/ApmButtons';

/**
 * Plugin-specific styling.
 *
 * Note that most properties of this object do not apply to the Google Pay button.
 *
 * @typedef {Object} PPCPStyle
 * @property {string}  shape  - Outline shape.
 * @property {?number} height - Button height in pixel.
 */

/**
 * Style options that are defined by the Google Pay SDK and are required to render the button.
 *
 * @typedef {Object} GooglePayStyle
 * @property {string} type     - Defines the button label.
 * @property {string} color    - Button color
 * @property {string} language - The locale; an empty string will apply the user-agent's language.
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
	Preview: 'preview', // Block editor contexts.
	Blocks: [ 'cart-block', 'checkout-block' ], // Custom gateway contexts.
	Gateways: [ 'checkout', 'pay-now' ],
};

class GooglepayButton {
	#wrapperId = '';
	#ppcpButtonWrapperId = '';

	/**
	 * Whether the payment button is initialized.
	 *
	 * @type {boolean}
	 */
	#isInitialized = false;

	/**
	 * Whether the current client support the payment button.
	 * This state is mainly dependent on the response of `PaymentClient.isReadyToPay()`
	 *
	 * @type {boolean}
	 */
	#isEligible = false;

	/**
	 * Client reference, provided by the Google Pay JS SDK.
	 * @see https://developers.google.com/pay/api/web/reference/client
	 */
	paymentsClient = null;

	constructor(
		context,
		externalHandler,
		buttonConfig,
		ppcpConfig,
		contextHandler
	) {
		this._initDebug( !! buttonConfig?.is_debug, context );

		apmButtonsInit( ppcpConfig );

		this.context = context;
		this.externalHandler = externalHandler;
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.contextHandler = contextHandler;

		this.log( 'Create instance' );
	}

	/**
	 * NOOP log function to avoid errors when debugging is disabled.
	 */
	log() {}

	/**
	 * Enables debugging tools, when the button's is_debug flag is set.
	 *
	 * @param {boolean} enableDebugging If debugging features should be enabled for this instance.
	 * @param {string}  context         Used to make the instance accessible via the global debug
	 *                                  object.
	 * @private
	 */
	_initDebug( enableDebugging, context ) {
		if ( ! enableDebugging || this.#isInitialized ) {
			return;
		}

		document.ppcpGooglepayButtons = document.ppcpGooglepayButtons || {};
		document.ppcpGooglepayButtons[ context ] = this;

		this.log = ( ...args ) => {
			console.log( `[GooglePayButton | ${ context }]`, ...args );
		};

		document.addEventListener( 'ppcp-googlepay-debug', () => {
			this.log( this );
		} );
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
				id = 'ppc-button-ppcp-googlepay';
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
				id = 'express-payment-method-ppcp-gateway-paypal';
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
	 * Returns default style options that are propagated to and rendered by the Google Pay button.
	 *
	 * These styles are the official style options provided by the Google Pay SDK.
	 *
	 * @return {GooglePayStyle} The style object.
	 */
	get buttonStyle() {
		let style;

		if ( CONTEXT.MiniCart === this.context ) {
			style = this.buttonConfig.button.mini_cart_style;

			// Handle incompatible types.
			if ( style.type === 'buy' ) {
				style.type = 'pay';
			}
		} else {
			style = this.buttonConfig.button.style;
		}

		return {
			type: style.type,
			language: style.language,
			color: style.color,
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
			selectors.push( '#express-payment-method-ppcp-googlepay' );
		}

		// Classic Checkout: Google Pay gateway.
		if ( CONTEXT.Gateways === this.context ) {
			selectors.push(
				'.wc_payment_method.payment_method_ppcp-googlepay'
			);
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

	/**
	 * Whether the browser can accept Google Pay payments.
	 *
	 * @return {boolean} True, if payments are technically possible.
	 */
	get isEligible() {
		return this.#isEligible;
	}

	/**
	 * Changes the eligibility state of this button component.
	 *
	 * @param {boolean} newState Whether the browser can accept payments.
	 */
	set isEligible( newState ) {
		if ( newState === this.#isEligible ) {
			return;
		}

		this.#isEligible = newState;
		this.refresh();
	}

	init( config, transactionInfo ) {
		if ( this.#isInitialized ) {
			return;
		}

		if ( ! this.validateConfig() ) {
			return;
		}

		if ( ! this.contextHandler.validateContext() ) {
			return;
		}

		this.log( 'Init' );
		this.#isInitialized = true;

		this.googlePayConfig = config;
		this.transactionInfo = transactionInfo;
		this.allowedPaymentMethods = config.allowedPaymentMethods;
		this.baseCardPaymentMethod = this.allowedPaymentMethods[ 0 ];

		this.initClient();
		this.initEventHandlers();

		this.paymentsClient
			.isReadyToPay(
				this.buildReadyToPayRequest(
					this.allowedPaymentMethods,
					config
				)
			)
			.then( ( response ) => {
				this.log( 'PaymentsClient.isReadyToPay response:', response );

				/**
				 * In case the button wrapper element is not present in the DOM yet, wait for it
				 * to appear. Only proceed, if a button wrapper is found on this page.
				 *
				 * Not sure if this is needed, or if we can directly test for `this.isPresent`
				 * without any delay.
				 */
				this.waitForWrapper( () => {
					this.isEligible = !! response.result;
				} );
			} )
			.catch( ( err ) => {
				console.error( err );
				this.isEligible = false;
			} );
	}

	reinit() {
		if ( ! this.googlePayConfig ) {
			return;
		}

		this.#isInitialized = false;
		this.init( this.googlePayConfig, this.transactionInfo );
	}

	validateConfig() {
		if (
			[ 'PRODUCTION', 'TEST' ].indexOf(
				this.buttonConfig.environment
			) === -1
		) {
			console.error(
				'[GooglePayButton] Invalid environment.',
				this.buttonConfig.environment
			);
			return false;
		}

		if ( ! this.contextHandler ) {
			console.error(
				'[GooglePayButton] Invalid context handler.',
				this.contextHandler
			);
			return false;
		}

		return true;
	}

	initClient() {
		const callbacks = {
			onPaymentAuthorized: this.onPaymentAuthorized.bind( this ),
		};

		if (
			this.buttonConfig.shipping.enabled &&
			this.contextHandler.shippingAllowed()
		) {
			callbacks.onPaymentDataChanged =
				this.onPaymentDataChanged.bind( this );
		}

		/**
		 * Consider providing merchant info here:
		 *
		 * @see https://developers.google.com/pay/api/web/reference/request-objects#PaymentOptions
		 */
		this.paymentsClient = new google.payments.api.PaymentsClient( {
			environment: this.buttonConfig.environment,
			paymentDataCallbacks: callbacks,
		} );
	}

	initEventHandlers() {
		const ppcpButtonWrapper = `#${ this.ppcpButtonWrapperId }`;
		const wrapper = `#${ this.wrapperId }`;

		if ( wrapper === ppcpButtonWrapper ) {
			throw new Error(
				`[GooglePayButton] "wrapper" and "ppcpButtonWrapper" values must differ to avoid infinite loop. Current value: "${ wrapper }"`
			);
		}

		const syncButtonVisibility = () => {
			const $ppcpButtonWrapper = jQuery( ppcpButtonWrapper );
			setVisible( wrapper, $ppcpButtonWrapper.is( ':visible' ) );
			setEnabled(
				wrapper,
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

	buildReadyToPayRequest( allowedPaymentMethods, baseRequest ) {
		this.log( 'Ready To Pay request', baseRequest, allowedPaymentMethods );

		return Object.assign( {}, baseRequest, {
			allowedPaymentMethods,
		} );
	}

	/**
	 * Add a Google Pay purchase button
	 */
	addButton() {
		this.log( 'addButton' );

		const wrapper = this.wrapperElement;
		const baseCardPaymentMethod = this.baseCardPaymentMethod;
		const { color, type, language } = this.buttonStyle;
		const { shape, height } = this.ppcpStyle;

		wrapper.classList.add(
			`ppcp-button-${ shape }`,
			'ppcp-button-apm',
			'ppcp-button-googlepay'
		);

		if ( height ) {
			wrapper.style.height = `${ height }px`;
		}

		/**
		 * @see https://developers.google.com/pay/api/web/reference/client#createButton
		 */
		const button = this.paymentsClient.createButton( {
			onClick: this.onButtonClick.bind( this ),
			allowedPaymentMethods: [ baseCardPaymentMethod ],
			buttonColor: color || 'black',
			buttonType: type || 'pay',
			buttonLocale: language || 'en',
			buttonSizeMode: 'fill',
		} );

		this.log( 'Insert Button', { wrapper, button } );

		wrapper.replaceChildren( button );
	}

	/**
	 * Waits for the current button's wrapper element to become available in the DOM.
	 *
	 * Not sure if still needed, or if a simple `this.isPresent` check is sufficient.
	 *
	 * @param {Function} callback Function to call when the wrapper element was detected. Only called on success.
	 * @param {number}   delay    Optional. Polling interval to inspect the DOM. Default to 0.1 sec
	 * @param {number}   timeout  Optional. Max timeout in ms. Defaults to 2 sec
	 */
	waitForWrapper( callback, delay = 100, timeout = 2000 ) {
		let interval = 0;
		const startTime = Date.now();

		const stop = () => {
			if ( interval ) {
				clearInterval( interval );
			}
			interval = 0;
		};

		const checkElement = () => {
			if ( this.isPresent ) {
				stop();
				callback();
				return;
			}

			const timeElapsed = Date.now() - startTime;

			if ( timeElapsed > timeout ) {
				stop();
				this.log( '!! Wrapper not found:', this.wrapperId );
			}
		};

		interval = setInterval( checkElement, delay );
	}

	/**
	 * Refreshes the payment button on the page.
	 */
	refresh() {
		if ( this.isEligible && this.isPresent ) {
			this.show();
			this.addButton();
		} else {
			this.hide();
		}
	}

	/**
	 * Hides all wrappers that belong to this GooglePayButton instance.
	 */
	hide() {
		this.log( 'Hide' );
		this.allElements.forEach( ( element ) => {
			element.style.display = 'none';
		} );
	}

	/**
	 * Ensures all wrapper elements of this GooglePayButton instance are visible.
	 */
	show() {
		if ( ! this.isPresent ) {
			this.log( 'Cannot show button, wrapper is not present' );
			return;
		}
		this.log( 'Show' );

		// Classic Checkout: Make the Google Pay gateway visible.
		document
			.querySelectorAll( 'style#ppcp-hide-google-pay' )
			.forEach( ( el ) => el.remove() );

		this.allElements.forEach( ( element ) => {
			element.style.display = 'block';
		} );
	}

	//------------------------
	// Button click
	//------------------------

	/**
	 * Show Google Pay payment sheet when Google Pay payment button is clicked
	 */
	onButtonClick() {
		this.log( 'onButtonClick' );

		const paymentDataRequest = this.paymentDataRequest();

		this.log( 'onButtonClick: paymentDataRequest', paymentDataRequest );

		// Do this on another place like on create order endpoint handler.
		window.ppcpFundingSource = 'googlepay';

		this.paymentsClient.loadPaymentData( paymentDataRequest );
	}

	paymentDataRequest() {
		const baseRequest = {
			apiVersion: 2,
			apiVersionMinor: 0,
		};

		const googlePayConfig = this.googlePayConfig;
		const paymentDataRequest = Object.assign( {}, baseRequest );
		paymentDataRequest.allowedPaymentMethods =
			googlePayConfig.allowedPaymentMethods;
		paymentDataRequest.transactionInfo = this.transactionInfo;
		paymentDataRequest.merchantInfo = googlePayConfig.merchantInfo;

		if (
			this.buttonConfig.shipping.enabled &&
			this.contextHandler.shippingAllowed()
		) {
			paymentDataRequest.callbackIntents = [
				'SHIPPING_ADDRESS',
				'SHIPPING_OPTION',
				'PAYMENT_AUTHORIZATION',
			];
			paymentDataRequest.shippingAddressRequired = true;
			paymentDataRequest.shippingAddressParameters =
				this.shippingAddressParameters();
			paymentDataRequest.shippingOptionRequired = true;
		} else {
			paymentDataRequest.callbackIntents = [ 'PAYMENT_AUTHORIZATION' ];
		}

		return paymentDataRequest;
	}

	//------------------------
	// Shipping processing
	//------------------------

	shippingAddressParameters() {
		return {
			allowedCountryCodes: this.buttonConfig.shipping.countries,
			phoneNumberRequired: true,
		};
	}

	onPaymentDataChanged( paymentData ) {
		this.log( 'onPaymentDataChanged', paymentData );

		return new Promise( async ( resolve, reject ) => {
			try {
				const paymentDataRequestUpdate = {};

				const updatedData = await new UpdatePaymentData(
					this.buttonConfig.ajax.update_payment_data
				).update( paymentData );
				const transactionInfo = this.transactionInfo;

				this.log( 'onPaymentDataChanged:updatedData', updatedData );
				this.log(
					'onPaymentDataChanged:transactionInfo',
					transactionInfo
				);

				updatedData.country_code = transactionInfo.countryCode;
				updatedData.currency_code = transactionInfo.currencyCode;
				updatedData.total_str = transactionInfo.totalPrice;

				// Handle unserviceable address.
				if ( ! updatedData.shipping_options?.shippingOptions?.length ) {
					paymentDataRequestUpdate.error =
						this.unserviceableShippingAddressError();
					resolve( paymentDataRequestUpdate );
					return;
				}

				switch ( paymentData.callbackTrigger ) {
					case 'INITIALIZE':
					case 'SHIPPING_ADDRESS':
						paymentDataRequestUpdate.newShippingOptionParameters =
							updatedData.shipping_options;
						paymentDataRequestUpdate.newTransactionInfo =
							this.calculateNewTransactionInfo( updatedData );
						break;
					case 'SHIPPING_OPTION':
						paymentDataRequestUpdate.newTransactionInfo =
							this.calculateNewTransactionInfo( updatedData );
						break;
				}

				resolve( paymentDataRequestUpdate );
			} catch ( error ) {
				console.error( 'Error during onPaymentDataChanged:', error );
				reject( error );
			}
		} );
	}

	unserviceableShippingAddressError() {
		return {
			reason: 'SHIPPING_ADDRESS_UNSERVICEABLE',
			message: 'Cannot ship to the selected address',
			intent: 'SHIPPING_ADDRESS',
		};
	}

	calculateNewTransactionInfo( updatedData ) {
		return {
			countryCode: updatedData.country_code,
			currencyCode: updatedData.currency_code,
			totalPriceStatus: 'FINAL',
			totalPrice: updatedData.total_str,
		};
	}

	//------------------------
	// Payment process
	//------------------------

	onPaymentAuthorized( paymentData ) {
		this.log( 'onPaymentAuthorized' );
		return this.processPayment( paymentData );
	}

	async processPayment( paymentData ) {
		this.log( 'processPayment' );

		return new Promise( async ( resolve, reject ) => {
			try {
				const id = await this.contextHandler.createOrder();

				this.log( 'processPayment: createOrder', id );

				const confirmOrderResponse = await widgetBuilder.paypal
					.Googlepay()
					.confirmOrder( {
						orderId: id,
						paymentMethodData: paymentData.paymentMethodData,
					} );

				this.log(
					'processPayment: confirmOrder',
					confirmOrderResponse
				);

				/** Capture the Order on the Server */
				if ( confirmOrderResponse.status === 'APPROVED' ) {
					let approveFailed = false;
					await this.contextHandler.approveOrder(
						{
							orderID: id,
						},
						{
							// actions mock object.
							restart: () =>
								new Promise( ( resolve, reject ) => {
									approveFailed = true;
									resolve();
								} ),
							order: {
								get: () =>
									new Promise( ( resolve, reject ) => {
										resolve( null );
									} ),
							},
						}
					);

					if ( ! approveFailed ) {
						resolve( this.processPaymentResponse( 'SUCCESS' ) );
					} else {
						resolve(
							this.processPaymentResponse(
								'ERROR',
								'PAYMENT_AUTHORIZATION',
								'FAILED TO APPROVE'
							)
						);
					}
				} else {
					resolve(
						this.processPaymentResponse(
							'ERROR',
							'PAYMENT_AUTHORIZATION',
							'TRANSACTION FAILED'
						)
					);
				}
			} catch ( err ) {
				resolve(
					this.processPaymentResponse(
						'ERROR',
						'PAYMENT_AUTHORIZATION',
						err.message
					)
				);
			}
		} );
	}

	processPaymentResponse( state, intent = null, message = null ) {
		const response = {
			transactionState: state,
		};

		if ( intent || message ) {
			response.error = {
				intent,
				message,
			};
		}

		this.log( 'processPaymentResponse', response );

		return response;
	}
}

export default GooglepayButton;
