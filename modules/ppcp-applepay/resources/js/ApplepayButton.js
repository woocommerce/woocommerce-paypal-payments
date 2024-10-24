/* global ApplePaySession */
/* global PayPalCommerceGateway */

import { createAppleErrors } from './Helper/applePayError';
import FormValidator from '../../../ppcp-button/resources/js/modules/Helper/FormValidator';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import widgetBuilder from '../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder';
import PaymentButton from '../../../ppcp-button/resources/js/modules/Renderer/PaymentButton';
import {
	PaymentContext,
	PaymentMethods,
} from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';
import {
	combineStyles,
	combineWrapperIds,
} from '../../../ppcp-button/resources/js/modules/Helper/PaymentButtonHelpers';

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
 * This object describes the transaction details.
 *
 * @typedef {Object} TransactionInfo
 * @property {string} countryCode           - The ISO country code
 * @property {string} currencyCode          - The ISO currency code
 * @property {string} totalPriceStatus      - Usually 'FINAL', can also be 'DRAFT'
 * @property {string} totalPrice            - Total monetary value of the transaction.
 * @property {Array}  chosenShippingMethods - Selected shipping method.
 * @property {string} shippingPackages      - A list of available shipping methods, defined by WooCommerce.
 */

/**
 * A payment button for Apple Pay.
 *
 * On a single page, multiple Apple Pay buttons can be displayed, which also means multiple
 * ApplePayButton instances exist. A typical case is on the product page, where one Apple Pay button
 * is located inside the minicart-popup, and another pay-now button is in the product context.
 */
class ApplePayButton extends PaymentButton {
	/**
	 * @inheritDoc
	 */
	static methodId = PaymentMethods.APPLEPAY;

	/**
	 * @inheritDoc
	 */
	static cssClass = 'ppcp-button-applepay';

	#formData = null;
	#updatedContactInfo = [];
	#selectedShippingMethod = [];

	/**
	 * Initialization data sent to the button.
	 */
	#initialPaymentRequest = null;

	/**
	 * Details about the processed transaction, provided to the Apple SDK.
	 *
	 * @type {?TransactionInfo}
	 */
	#transactionInfo = null;

	/**
	 * Apple Pay specific API configuration.
	 */
	#applePayConfig = null;

	/**
	 * Details about the product (relevant on product page)
	 *
	 * @type {{quantity: ?number, items: []}}
	 */
	#product = {};

	/**
	 * @inheritDoc
	 */
	static getWrappers( buttonConfig, ppcpConfig ) {
		return combineWrapperIds(
			buttonConfig?.button?.wrapper || '',
			buttonConfig?.button?.mini_cart_wrapper || '',
			ppcpConfig?.button?.wrapper || '',
			'ppc-button-applepay-container',
			'ppc-button-ppcp-applepay'
		);
	}

	/**
	 * @inheritDoc
	 */
	static getStyles( buttonConfig, ppcpConfig ) {
		return combineStyles(
			ppcpConfig?.button || {},
			buttonConfig?.button || {}
		);
	}

	constructor(
		context,
		externalHandler,
		buttonConfig,
		ppcpConfig,
		contextHandler
	) {
		// Disable debug output in the browser console:
		// buttonConfig.is_debug = false;

		super(
			context,
			externalHandler,
			buttonConfig,
			ppcpConfig,
			contextHandler
		);

		this.init = this.init.bind( this );
		this.onPaymentAuthorized = this.onPaymentAuthorized.bind( this );
		this.onButtonClick = this.onButtonClick.bind( this );

		this.#product = {
			quantity: null,
			items: [],
		};

		this.log( 'Create instance' );
	}

	/**
	 * @inheritDoc
	 */
	get requiresShipping() {
		if ( ! super.requiresShipping ) {
			return false;
		}

		if ( ! this.buttonConfig.product?.needShipping ) {
			return false;
		}

		return (
			PaymentContext.Checkout !== this.context ||
			this.shouldUpdateButtonWithFormData
		);
	}

	/**
	 * Details about the processed transaction.
	 *
	 * This object defines the price that is charged, and text that is displayed inside the
	 * payment sheet.
	 *
	 * @return {?TransactionInfo} The TransactionInfo object.
	 */
	get transactionInfo() {
		return this.#transactionInfo;
	}

	/**
	 * Assign the new transaction details to the payment button.
	 *
	 * @param {TransactionInfo} newTransactionInfo - Transaction details.
	 */
	set transactionInfo( newTransactionInfo ) {
		this.#transactionInfo = newTransactionInfo;

		this.refresh();
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
	 * @inheritDoc
	 */
	registerValidationRules( invalidIf, validIf ) {
		validIf( () => this.isPreview );

		invalidIf(
			() => ! this.#applePayConfig,
			'No API configuration - missing configure() call?'
		);

		invalidIf(
			() => ! this.#transactionInfo,
			'No transactionInfo - missing configure() call?'
		);

		invalidIf(
			() => ! this.contextHandler?.validateContext(),
			`Invalid context handler.`
		);
	}

	/**
	 * Configures the button instance. Must be called before the initial `init()`.
	 *
	 * @param {Object}          apiConfig       - API configuration.
	 * @param {TransactionInfo} transactionInfo - Transaction details.
	 */
	configure( apiConfig, transactionInfo ) {
		this.#applePayConfig = apiConfig;
		this.#transactionInfo = transactionInfo;
	}

	init() {
		// Use `reinit()` to force a full refresh of an initialized button.
		if ( this.isInitialized ) {
			return;
		}

		// Stop, if configuration is invalid.
		if ( ! this.validateConfiguration() ) {
			return;
		}

		super.init();
		this.checkEligibility();
	}

	reinit() {
		// Missing (invalid) configuration indicates, that the first `init()` call did not happen yet.
		if ( ! this.validateConfiguration( true ) ) {
			return;
		}

		super.reinit();

		this.init();
	}

	/**
	 * Re-check if the current session is eligible for Apple Pay.
	 */
	checkEligibility() {
		if ( this.isPreview ) {
			this.isEligible = true;
			return;
		}

		try {
			if ( ! window.ApplePaySession?.canMakePayments() ) {
				this.isEligible = false;
				return;
			}

			this.isEligible = !! this.#applePayConfig.isEligible;
		} catch ( error ) {
			this.isEligible = false;
		}
	}

	/**
	 * Starts an Apple Pay session, which means that the user interacted with the Apple Pay button.
	 *
	 * @param {Object} paymentRequest The payment request object.
	 */
	applePaySession( paymentRequest ) {
		this.log( 'applePaySession', paymentRequest );
		const session = new ApplePaySession( 4, paymentRequest );

		if ( this.requiresShipping ) {
			session.onshippingmethodselected =
				this.onShippingMethodSelected( session );
			session.onshippingcontactselected =
				this.onShippingContactSelected( session );
		}

		session.onvalidatemerchant = this.onValidateMerchant( session );
		session.onpaymentauthorized = this.onPaymentAuthorized( session );

		/**
		 * This starts the merchant validation process and displays the payment sheet
		 * {@see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaysession/1778001-begin}
		 *
		 * After calling the `begin` method, the browser invokes your `onvalidatemerchant` handler
		 * {@see https://applepaydemo.apple.com/apple-pay-js-api}
		 */
		session.begin();

		return session;
	}

	/**
	 * Applies CSS classes and inline styling to the payment button wrapper.
	 */
	applyWrapperStyles() {
		super.applyWrapperStyles();

		const { height } = this.style;

		if ( height ) {
			const wrapper = this.wrapperElement;

			wrapper.style.setProperty(
				'--apple-pay-button-height',
				`${ height }px`
			);

			wrapper.style.height = `${ height }px`;
		}
	}

	/**
	 * Creates the payment button and calls `this.insertButton()` to make the button visible in the
	 * correct wrapper.
	 */
	addButton() {
		const { color, type, language } = this.style;

		const button = document.createElement( 'apple-pay-button' );
		button.id = 'apple-' + this.wrapperId;
		button.setAttribute( 'buttonstyle', color );
		button.setAttribute( 'type', type );
		button.setAttribute( 'locale', language );

		button.addEventListener( 'click', ( evt ) => {
			evt.preventDefault();
			this.onButtonClick();
		} );

		this.insertButton( button );
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
		if ( PaymentContext.Checkout === this.context ) {
			const checkoutFormSelector = 'form.woocommerce-checkout';
			const errorHandler = new ErrorHandler(
				PayPalCommerceGateway.labels.error.generic,
				document.querySelector( '.woocommerce-notices-wrapper' )
			);

			try {
				const formData = new FormData(
					document.querySelector( checkoutFormSelector )
				);
				this.#formData = Object.fromEntries( formData.entries() );

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
	 * If the button should be updated with the form addresses.
	 *
	 * @return {boolean} True, when Apple Pay data should be submitted to WooCommerce.
	 */
	get shouldUpdateButtonWithFormData() {
		if ( PaymentContext.Checkout !== this.context ) {
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
	get shouldCompletePaymentWithContextHandler() {
		// Data already handled, ex: PayNow
		if ( ! this.contextHandler.shippingAllowed() ) {
			return true;
		}

		// Use WC form data mode in Checkout.
		return (
			PaymentContext.Checkout === this.context &&
			! this.shouldUpdateButtonWithFormData
		);
	}

	/**
	 * Updates Apple Pay paymentRequest with form data.
	 *
	 * @param {Object} paymentRequest Object to extend with form data.
	 */
	updateRequestDataWithForm( paymentRequest ) {
		if ( ! this.shouldUpdateButtonWithFormData ) {
			return;
		}

		// Add billing address.
		paymentRequest.billingContact = this.fillBillingContact(
			this.#formData
		);

		if ( ! this.requiresShipping ) {
			return;
		}

		// Add shipping address.
		paymentRequest.shippingContact = this.fillShippingContact(
			this.#formData
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
				this.#selectedShippingMethod = shippingMethod;

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
		this.#initialPaymentRequest = paymentRequest;

		this.log(
			'=== paymentRequest.shippingMethods',
			paymentRequest.shippingMethods
		);
	}

	paymentRequest() {
		const applepayConfig = this.#applePayConfig;
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
			// ApplePay does not implement billing email and phone fields.
			requiredBillingContactFields: [ 'postalAddress' ],
		};

		if ( ! this.requiresShipping ) {
			if ( this.shouldCompletePaymentWithContextHandler ) {
				// Data is handled externally.
				baseRequest.requiredShippingContactFields = [];
			} else {
				// Minimum data required to create order.
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

	refreshProductContextData() {
		if ( PaymentContext.Product !== this.context ) {
			return;
		}

		// Refresh product data that makes the price change.
		this.#product.quantity = document.querySelector( 'input.qty' )?.value;

		// Always an array; grouped products can return multiple items.
		this.#product.items = this.contextHandler.products();

		this.log( 'Products updated', this.#product );
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
				success: ( applePayShippingMethodUpdate ) => {
					this.log( 'onshippingmethodselected ok' );
					const response = applePayShippingMethodUpdate.data;
					if ( applePayShippingMethodUpdate.success === false ) {
						response.errors = createAppleErrors( response.errors );
					}
					this.#selectedShippingMethod = event.shippingMethod;

					// Sort the response shipping methods, so that the selected shipping method is
					// the first one.
					response.newShippingMethods =
						response.newShippingMethods.sort( ( a ) => {
							if (
								a.label === this.#selectedShippingMethod.label
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
				success: ( applePayShippingContactUpdate ) => {
					this.log( 'onshippingcontactselected ok' );
					const response = applePayShippingContactUpdate.data;
					this.#updatedContactInfo = event.shippingContact;
					if ( applePayShippingContactUpdate.success === false ) {
						response.errors = createAppleErrors( response.errors );
					}
					if ( response.newShippingMethods ) {
						this.#selectedShippingMethod =
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

		this.refreshProductContextData();

		switch ( this.context ) {
			case PaymentContext.Product:
				return {
					action: 'ppcp_update_shipping_contact',
					product_id: productId,
					products: JSON.stringify( this.#product.items ),
					caller_page: 'productDetail',
					product_quantity: this.#product.quantity,
					simplified_contact: event.shippingContact,
					need_shipping: this.requiresShipping,
					'woocommerce-process-checkout-nonce': this.nonce,
				};

			case PaymentContext.Cart:
			case PaymentContext.Checkout:
			case PaymentContext.BlockCart:
			case PaymentContext.BlockCheckout:
			case PaymentContext.MiniCart:
				return {
					action: 'ppcp_update_shipping_contact',
					simplified_contact: event.shippingContact,
					caller_page: 'cart',
					need_shipping: this.requiresShipping,
					'woocommerce-process-checkout-nonce': this.nonce,
				};
		}
	}

	getShippingMethodData( event ) {
		const productId = this.buttonConfig.product.id;

		this.refreshProductContextData();

		switch ( this.context ) {
			case PaymentContext.Product:
				return {
					action: 'ppcp_update_shipping_method',
					shipping_method: event.shippingMethod,
					simplified_contact: this.hasValidContactInfo(
						this.#updatedContactInfo
					)
						? this.#updatedContactInfo
						: this.#initialPaymentRequest?.shippingContact ??
						  this.#initialPaymentRequest?.billingContact,
					product_id: productId,
					products: JSON.stringify( this.#product.items ),
					caller_page: 'productDetail',
					product_quantity: this.#product.quantity,
					'woocommerce-process-checkout-nonce': this.nonce,
				};

			case PaymentContext.Cart:
			case PaymentContext.Checkout:
			case PaymentContext.BlockCart:
			case PaymentContext.BlockCheckout:
			case PaymentContext.MiniCart:
				return {
					action: 'ppcp_update_shipping_method',
					shipping_method: event.shippingMethod,
					simplified_contact: this.hasValidContactInfo(
						this.#updatedContactInfo
					)
						? this.#updatedContactInfo
						: this.#initialPaymentRequest?.shippingContact ??
						  this.#initialPaymentRequest?.billingContact,
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
							this.#initialPaymentRequest.billingContact;
						const shippingContact =
							data.shipping_contact ||
							this.#initialPaymentRequest.shippingContact;
						const shippingMethod =
							this.#selectedShippingMethod ||
							( this.#initialPaymentRequest.shippingMethods ||
								[] )[ 0 ];

						const requestData = {
							action: 'ppcp_create_order',
							caller_page: this.context,
							product_id: this.buttonConfig.product.id ?? null,
							products: JSON.stringify( this.#product.items ),
							product_quantity: this.#product.quantity,
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
							complete: () => {
								this.log( 'onpaymentauthorized complete' );
							},
							success: ( authorizationResult ) => {
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
						this.error( 'onpaymentauthorized catch', error );
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
								this.shouldCompletePaymentWithContextHandler
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
											new Promise( ( resolve ) => {
												approveFailed = true;
												resolve();
											} ),
										order: {
											get: () =>
												new Promise( ( resolve ) => {
													resolve( null );
												} ),
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
									this.error(
										'onpaymentauthorized approveOrder FAIL'
									);
									session.completePayment(
										ApplePaySession.STATUS_FAILURE
									);
									session.abort();
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

	#extractContactInfo( data, primaryPrefix, fallbackPrefix ) {
		if ( ! data || typeof data !== 'object' ) {
			data = {};
		}

		const getValue = ( key ) =>
			data[ `${ primaryPrefix }_${ key }` ] ||
			data[ `${ fallbackPrefix }_${ key }` ] ||
			'';

		return {
			givenName: getValue( 'first_name' ),
			familyName: getValue( 'last_name' ),
			emailAddress: getValue( 'email' ),
			phoneNumber: getValue( 'phone' ),
			addressLines: [ getValue( 'address_1' ), getValue( 'address_2' ) ],
			locality: getValue( 'city' ),
			postalCode: getValue( 'postcode' ),
			countryCode: getValue( 'country' ),
			administrativeArea: getValue( 'state' ),
		};
	}

	fillBillingContact( data ) {
		return this.#extractContactInfo( data, 'billing', '' );
	}

	fillShippingContact( data ) {
		if ( ! data?.shipping_first_name ) {
			return this.fillBillingContact( data );
		}

		return this.#extractContactInfo( data, 'shipping', 'billing' );
	}

	hasValidContactInfo( value ) {
		return Array.isArray( value )
			? value.length > 0
			: Object.keys( value || {} ).length > 0;
	}
}

export default ApplePayButton;
