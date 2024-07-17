import ContextHandlerFactory from './Context/ContextHandlerFactory';
import { createAppleErrors } from './Helper/applePayError';
import { setVisible } from '../../../ppcp-button/resources/js/modules/Helper/Hiding';
import { setEnabled } from '../../../ppcp-button/resources/js/modules/Helper/ButtonDisabler';
import FormValidator from '../../../ppcp-button/resources/js/modules/Helper/FormValidator';
import ErrorHandler from '../../../ppcp-button/resources/js/modules/ErrorHandler';
import widgetBuilder from '../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder';
import { apmButtonsInit } from '../../../ppcp-button/resources/js/modules/Helper/ApmButtons';

class ApplepayButton {
	constructor( context, externalHandler, buttonConfig, ppcpConfig ) {
		apmButtonsInit( ppcpConfig );

		this.isInitialized = false;

		this.context = context;
		this.externalHandler = externalHandler;
		this.buttonConfig = buttonConfig;
		this.ppcpConfig = ppcpConfig;
		this.paymentsClient = null;
		this.formData = null;

		this.contextHandler = ContextHandlerFactory.create(
			this.context,
			this.buttonConfig,
			this.ppcpConfig
		);

		this.updatedContactInfo = [];
		this.selectedShippingMethod = [];
		this.nonce =
			document.getElementById( 'woocommerce-process-checkout-nonce' )
				?.value || buttonConfig.nonce;

		// Stores initialization data sent to the button.
		this.initialPaymentRequest = null;

		// Default eligibility status.
		this.isEligible = true;

		this.log = function () {
			if ( this.buttonConfig.is_debug ) {
				//console.log('[ApplePayButton]', ...arguments);
			}
		};

		this.refreshContextData();

		// Debug helpers
		jQuery( document ).on( 'ppcp-applepay-debug', () => {
			console.log( 'ApplePayButton', this.context, this );
		} );
		document.ppcpApplepayButtons = document.ppcpApplepayButtons || {};
		document.ppcpApplepayButtons[ this.context ] = this;
	}

	init( config ) {
		if ( this.isInitialized ) {
			return;
		}

		if ( ! this.contextHandler.validateContext() ) {
			return;
		}

		this.log( 'Init', this.context );
		this.initEventHandlers();
		this.isInitialized = true;
		this.applePayConfig = config;
		this.isEligible =
			( this.applePayConfig.isEligible && window.ApplePaySession ) ||
			this.buttonConfig.is_admin;

		if ( this.isEligible ) {
			this.fetchTransactionInfo().then( () => {
				this.addButton();
				const id_minicart =
					'#apple-' + this.buttonConfig.button.mini_cart_wrapper;
				const id = '#apple-' + this.buttonConfig.button.wrapper;

				if ( this.context === 'mini-cart' ) {
					document
						.querySelector( id_minicart )
						?.addEventListener( 'click', ( evt ) => {
							evt.preventDefault();
							this.onButtonClick();
						} );
				} else {
					document
						.querySelector( id )
						?.addEventListener( 'click', ( evt ) => {
							evt.preventDefault();
							this.onButtonClick();
						} );
				}
			} );
		} else {
			jQuery( '#' + this.buttonConfig.button.wrapper ).hide();
			jQuery( '#' + this.buttonConfig.button.mini_cart_wrapper ).hide();
			jQuery( '#express-payment-method-ppcp-applepay' ).hide();
		}
	}

	reinit() {
		if ( ! this.applePayConfig ) {
			return;
		}

		this.isInitialized = false;
		this.init( this.applePayConfig );
	}

	async fetchTransactionInfo() {
		this.transactionInfo = await this.contextHandler.transactionInfo();
	}

	/**
	 * Returns configurations relative to this button context.
	 */
	contextConfig() {
		const config = {
			wrapper: this.buttonConfig.button.wrapper,
			ppcpStyle: this.ppcpConfig.button.style,
			buttonStyle: this.buttonConfig.button.style,
			ppcpButtonWrapper: this.ppcpConfig.button.wrapper,
		};

		if ( this.context === 'mini-cart' ) {
			config.wrapper = this.buttonConfig.button.mini_cart_wrapper;
			config.ppcpStyle = this.ppcpConfig.button.mini_cart_style;
			config.buttonStyle = this.buttonConfig.button.mini_cart_style;
			config.ppcpButtonWrapper = this.ppcpConfig.button.mini_cart_wrapper;
		}

		if (
			[ 'cart-block', 'checkout-block' ].indexOf( this.context ) !== -1
		) {
			config.ppcpButtonWrapper =
				'#express-payment-method-ppcp-gateway-paypal';
		}

		return config;
	}

	initEventHandlers() {
		const { wrapper, ppcpButtonWrapper } = this.contextConfig();
		const wrapper_id = '#' + wrapper;

		if ( wrapper_id === ppcpButtonWrapper ) {
			throw new Error(
				`[ApplePayButton] "wrapper" and "ppcpButtonWrapper" values must differ to avoid infinite loop. Current value: "${ wrapper_id }"`
			);
		}

		const syncButtonVisibility = () => {
			if ( ! this.isEligible ) {
				return;
			}

			const $ppcpButtonWrapper = jQuery( ppcpButtonWrapper );
			setVisible( wrapper_id, $ppcpButtonWrapper.is( ':visible' ) );
			setEnabled(
				wrapper_id,
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
	 * Starts an ApplePay session.
	 * @param paymentRequest
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
	 */
	addButton() {
		this.log( 'addButton', this.context );

		const { wrapper, ppcpStyle } = this.contextConfig();

		const appleContainer = document.getElementById( wrapper );
		const type = this.buttonConfig.button.type;
		const language = this.buttonConfig.button.lang;
		const color = this.buttonConfig.button.color;
		const id = 'apple-' + wrapper;

		if ( appleContainer ) {
			appleContainer.innerHTML = `<apple-pay-button id="${ id }" buttonstyle="${ color }" type="${ type }" locale="${ language }">`;
		}

		const $wrapper = jQuery( '#' + wrapper );
		$wrapper.addClass( 'ppcp-button-' + ppcpStyle.shape );

		if ( ppcpStyle.height ) {
			$wrapper.css(
				'--apple-pay-button-height',
				`${ ppcpStyle.height }px`
			);
			$wrapper.css( 'height', `${ ppcpStyle.height }px` );
		}
	}

	//------------------------
	// Button click
	//------------------------

	/**
	 * Show Apple Pay payment sheet when Apple Pay payment button is clicked
	 */
	async onButtonClick() {
		this.log( 'onButtonClick', this.context );

		const paymentRequest = this.paymentRequest();

		window.ppcpFundingSource = 'apple_pay'; // Do this on another place like on create order endpoint handler.

		// Trigger woocommerce validation if we are in the checkout page.
		if ( this.context === 'checkout' ) {
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
	 * @return {false|*}
	 */
	shouldRequireShippingInButton() {
		return (
			this.contextHandler.shippingAllowed() &&
			this.buttonConfig.product.needShipping &&
			( this.context !== 'checkout' ||
				this.shouldUpdateButtonWithFormData() )
		);
	}

	/**
	 * If the button should be updated with the form addresses.
	 *
	 * @return {boolean}
	 */
	shouldUpdateButtonWithFormData() {
		if ( this.context !== 'checkout' ) {
			return false;
		}
		return (
			this.buttonConfig?.preferences?.checkout_data_mode ===
			'use_applepay'
		);
	}

	/**
	 * Indicates how payment completion should be handled if with the context handler default actions.
	 * Or with ApplePay module specific completion.
	 *
	 * @return {boolean}
	 */
	shouldCompletePaymentWithContextHandler() {
		// Data already handled, ex: PayNow
		if ( ! this.contextHandler.shippingAllowed() ) {
			return true;
		}
		// Use WC form data mode in Checkout.
		if (
			this.context === 'checkout' &&
			! this.shouldUpdateButtonWithFormData()
		) {
			return true;
		}
		return false;
	}

	/**
	 * Updates ApplePay paymentRequest with form data.
	 * @param paymentRequest
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
		// "applicationData" is originating a "PayPalApplePayError: An internal server error has occurred" on paypal.Applepay().confirmOrder().
		// paymentRequest.applicationData = this.fillApplicationData(this.formData);

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
			requiredBillingContactFields: [ 'postalAddress' ], // ApplePay does not implement billing email and phone fields.
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
		switch ( this.context ) {
			case 'product':
				// Refresh product data that makes the price change.
				this.productQuantity =
					document.querySelector( 'input.qty' )?.value;
				this.products = this.contextHandler.products();
				this.log( 'Products updated', this.products );
				break;
		}
	}

	//------------------------
	// Payment process
	//------------------------

	onValidateMerchant( session ) {
		this.log( 'onvalidatemerchant', this.buttonConfig.ajax_url );
		return ( applePayValidateMerchantEvent ) => {
			this.log( 'onvalidatemerchant call' );

			widgetBuilder.paypal
				.Applepay()
				.validateMerchant( {
					validationUrl: applePayValidateMerchantEvent.validationURL,
				} )
				.then( ( validateResult ) => {
					this.log( 'onvalidatemerchant ok' );
					session.completeMerchantValidation(
						validateResult.merchantSession
					);
					//call backend to update validation to true
					jQuery.ajax( {
						url: this.buttonConfig.ajax_url,
						type: 'POST',
						data: {
							action: 'ppcp_validate',
							validation: true,
							'woocommerce-process-checkout-nonce': this.nonce,
						},
					} );
				} )
				.catch( ( validateError ) => {
					this.log( 'onvalidatemerchant error', validateError );
					console.error( validateError );
					//call backend to update validation to false
					jQuery.ajax( {
						url: this.buttonConfig.ajax_url,
						type: 'POST',
						data: {
							action: 'ppcp_validate',
							validation: false,
							'woocommerce-process-checkout-nonce': this.nonce,
						},
					} );
					this.log( 'onvalidatemerchant session abort' );
					session.abort();
				} );
		};
	}

	onShippingMethodSelected( session ) {
		this.log( 'onshippingmethodselected', this.buttonConfig.ajax_url );
		const ajax_url = this.buttonConfig.ajax_url;
		return ( event ) => {
			this.log( 'onshippingmethodselected call' );

			const data = this.getShippingMethodData( event );

			jQuery.ajax( {
				url: ajax_url,
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

					// Sort the response shipping methods, so that the selected shipping method is the first one.
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

		const ajax_url = this.buttonConfig.ajax_url;

		return ( event ) => {
			this.log( 'onshippingcontactselected call' );

			const data = this.getShippingContactData( event );

			jQuery.ajax( {
				url: ajax_url,
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
		const product_id = this.buttonConfig.product.id;

		this.refreshContextData();

		switch ( this.context ) {
			case 'product':
				return {
					action: 'ppcp_update_shipping_contact',
					product_id,
					products: JSON.stringify( this.products ),
					caller_page: 'productDetail',
					product_quantity: this.productQuantity,
					simplified_contact: event.shippingContact,
					need_shipping: this.shouldRequireShippingInButton(),
					'woocommerce-process-checkout-nonce': this.nonce,
				};
			case 'cart':
			case 'checkout':
			case 'cart-block':
			case 'checkout-block':
			case 'mini-cart':
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
		const product_id = this.buttonConfig.product.id;

		this.refreshContextData();

		switch ( this.context ) {
			case 'product':
				return {
					action: 'ppcp_update_shipping_method',
					shipping_method: event.shippingMethod,
					simplified_contact:
						this.updatedContactInfo ||
						this.initialPaymentRequest.shippingContact ||
						this.initialPaymentRequest.billingContact,
					product_id,
					products: JSON.stringify( this.products ),
					caller_page: 'productDetail',
					product_quantity: this.productQuantity,
					'woocommerce-process-checkout-nonce': this.nonce,
				};
			case 'cart':
			case 'checkout':
			case 'cart-block':
			case 'checkout-block':
			case 'mini-cart':
				return {
					action: 'ppcp_update_shipping_method',
					shipping_method: event.shippingMethod,
					simplified_contact:
						this.updatedContactInfo ||
						this.initialPaymentRequest.shippingContact ||
						this.initialPaymentRequest.billingContact,
					caller_page: 'cart',
					'woocommerce-process-checkout-nonce': this.nonce,
				};
		}
	}

	onPaymentAuthorized( session ) {
		this.log( 'onpaymentauthorized' );
		return async ( event ) => {
			this.log( 'onpaymentauthorized call' );

			function form() {
				return document.querySelector( 'form.cart' );
			}
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

						const request_data = {
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
							data: request_data,
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
								// No shipping, expect immediate capture, ex: PayNow, Checkout with form data.

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
}

export default ApplepayButton;
