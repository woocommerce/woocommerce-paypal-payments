/* global google */

import {
	combineStyles,
	combineWrapperIds,
} from '../../../ppcp-button/resources/js/modules/Helper/PaymentButtonHelpers';
import PaymentButton from '../../../ppcp-button/resources/js/modules/Renderer/PaymentButton';
import widgetBuilder from '../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder';
import UpdatePaymentData from './Helper/UpdatePaymentData';
import { PaymentMethods } from '../../../ppcp-button/resources/js/modules/Helper/CheckoutMethodState';

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

class GooglepayButton extends PaymentButton {
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
		const wrappers = combineWrapperIds(
			buttonConfig.button.wrapper,
			buttonConfig.button.mini_cart_wrapper,
			ppcpConfig.button.wrapper,
			'express-payment-method-ppcp-googlepay',
			'ppc-button-ppcp-googlepay'
		);

		console.log( ppcpConfig.button, buttonConfig.button );

		const styles = combineStyles( ppcpConfig.button, buttonConfig.button );

		if ( 'buy' === styles.MiniCart.type ) {
			styles.MiniCart.type = 'pay';
		}

		super(
			PaymentMethods.GOOGLEPAY,
			context,
			wrappers,
			styles,
			buttonConfig,
			ppcpConfig
		);

		this.buttonConfig = buttonConfig;
		this.contextHandler = contextHandler;

		this.log( 'Create instance' );
	}

	/**
	 * @inheritDoc
	 */
	get isConfigValid() {
		const validEnvs = [ 'PRODUCTION', 'TEST' ];

		if ( ! validEnvs.includes( this.buttonConfig.environment ) ) {
			this.error( 'Invalid environment.', this.buttonConfig.environment );
			return false;
		}

		if ( ! typeof this.contextHandler?.validateContext() ) {
			this.error( 'Invalid context handler.', this.contextHandler );
			return false;
		}

		return true;
	}

	init( config = null, transactionInfo = null ) {
		if ( this.isInitialized ) {
			return;
		}
		if ( config ) {
			this.googlePayConfig = config;
		}
		if ( transactionInfo ) {
			this.transactionInfo = transactionInfo;
		}

		if ( ! this.googlePayConfig || ! this.transactionInfo ) {
			this.error( 'Missing config or transactionInfo during init.' );
			return;
		}

		if ( ! this.isConfigValid ) {
			return;
		}

		this.allowedPaymentMethods = config.allowedPaymentMethods;
		this.baseCardPaymentMethod = this.allowedPaymentMethods[ 0 ];

		super.init();
		this.initClient();

		if ( ! this.isPresent ) {
			this.log( 'Payment wrapper not found', this.wrapperId );
			return;
		}

		this.paymentsClient
			.isReadyToPay(
				this.buildReadyToPayRequest(
					this.allowedPaymentMethods,
					config
				)
			)
			.then( ( response ) => {
				this.log( 'PaymentsClient.isReadyToPay response:', response );
				this.isEligible = !! response.result;
			} )
			.catch( ( err ) => {
				console.error( err );
				this.isEligible = false;
			} );
	}

	reinit() {
		if ( ! this.isInitialized ) {
			return;
		}

		super.reinit();
		this.init();
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

	buildReadyToPayRequest( allowedPaymentMethods, baseRequest ) {
		this.log( 'Ready To Pay request', baseRequest, allowedPaymentMethods );

		return Object.assign( {}, baseRequest, {
			allowedPaymentMethods,
		} );
	}

	/**
	 * Add a Google Pay purchase button.
	 */
	addButton() {
		const baseCardPaymentMethod = this.baseCardPaymentMethod;
		const { color, type, language } = this.style;

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

		this.insertButton( button );
	}

	//------------------------
	// Button click
	//------------------------

	/**
	 * Show Google Pay payment sheet when Google Pay payment button is clicked
	 */
	onButtonClick() {
		this.log( 'onButtonClick' );

		this.contextHandler.validateForm().then(
			() => {
				window.ppcpFundingSource = 'googlepay';

				const paymentDataRequest = this.paymentDataRequest();

				this.log(
					'onButtonClick: paymentDataRequest',
					paymentDataRequest,
					this.context
				);

				this.paymentsClient.loadPaymentData( paymentDataRequest );
			},
			() => {
				console.error( '[GooglePayButton] Form validation failed.' );
			}
		);
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
