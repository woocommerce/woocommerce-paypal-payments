import 'formdata-polyfill';
import onApprove from '../OnApproveHandler/onApproveForPayNow.js';
import { payerData } from '../Helper/PayerData';
import { getCurrentPaymentMethod } from '../Helper/CheckoutMethodState';
import validateCheckoutForm from '../Helper/CheckoutFormValidation';

class CheckoutActionHandler {
	constructor( config, errorHandler, spinner ) {
		this.config = config;
		this.errorHandler = errorHandler;
		this.spinner = spinner;
	}

	subscriptionsConfiguration( subscription_plan_id ) {
		return {
			createSubscription: async ( data, actions ) => {
				try {
					await validateCheckoutForm( this.config );
				} catch ( error ) {
					throw { type: 'form-validation-error' };
				}

				return actions.subscription.create( {
					plan_id: subscription_plan_id,
				} );
			},
			onApprove: ( data, actions ) => {
				fetch( this.config.ajax.approve_subscription.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					body: JSON.stringify( {
						nonce: this.config.ajax.approve_subscription.nonce,
						order_id: data.orderID,
						subscription_id: data.subscriptionID,
					} ),
				} )
					.then( ( res ) => {
						return res.json();
					} )
					.then( ( data ) => {
						document.querySelector( '#place_order' ).click();
					} );
			},
			onError: ( err ) => {
				console.error( err );
			},
		};
	}

	configuration() {
		const spinner = this.spinner;
		const createOrder = ( data, actions ) => {
			const payer = payerData();
			const bnCode =
				typeof this.config.bn_codes[ this.config.context ] !==
				'undefined'
					? this.config.bn_codes[ this.config.context ]
					: '';

			const errorHandler = this.errorHandler;

			const formSelector =
				this.config.context === 'checkout'
					? 'form.checkout'
					: 'form#order_review';
			const formData = new FormData(
				document.querySelector( formSelector )
			);

			const createaccount = jQuery( '#createaccount' ).is( ':checked' )
				? true
				: false;

			const paymentMethod = getCurrentPaymentMethod();
			const fundingSource = window.ppcpFundingSource;

			const savePaymentMethod = !! document.getElementById(
				'wc-ppcp-credit-card-gateway-new-payment-method'
			)?.checked;

			return fetch( this.config.ajax.create_order.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: this.config.ajax.create_order.nonce,
					payer,
					bn_code: bnCode,
					context: this.config.context,
					order_id: this.config.order_id,
					payment_method: paymentMethod,
					funding_source: fundingSource,
					// send as urlencoded string to handle complex fields via PHP functions the same as normal form submit
					form_encoded: new URLSearchParams( formData ).toString(),
					createaccount,
					save_payment_method: savePaymentMethod,
				} ),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					if ( ! data.success ) {
						spinner.unblock();
						//handle both messages sent from Woocommerce (data.messages) and this plugin (data.data.message)
						if ( typeof data.messages !== 'undefined' ) {
							const domParser = new DOMParser();
							errorHandler.appendPreparedErrorMessageElement(
								domParser
									.parseFromString(
										data.messages,
										'text/html'
									)
									.querySelector( 'ul' )
							);
						} else {
							errorHandler.clear();

							if ( data.data.refresh ) {
								jQuery( document.body ).trigger(
									'update_checkout'
								);
							}

							if ( data.data.errors?.length > 0 ) {
								errorHandler.messages( data.data.errors );
							} else if ( data.data.details?.length > 0 ) {
								errorHandler.message(
									data.data.details
										.map(
											( d ) =>
												`${ d.issue } ${ d.description }`
										)
										.join( '<br/>' )
								);
							} else {
								errorHandler.message( data.data.message );
							}

							// fire WC event for other plugins
							jQuery( document.body ).trigger( 'checkout_error', [
								errorHandler.currentHtml(),
							] );
						}

						throw { type: 'create-order-error', data: data.data };
					}
					const input = document.createElement( 'input' );
					input.setAttribute( 'type', 'hidden' );
					input.setAttribute( 'name', 'ppcp-resume-order' );
					input.setAttribute( 'value', data.data.custom_id );
					document.querySelector( formSelector ).appendChild( input );
					return data.data.id;
				} );
		};
		return {
			createOrder,
			onApprove: onApprove( this, this.errorHandler, this.spinner ),
			onCancel: () => {
				spinner.unblock();
			},
			onError: ( err ) => {
				console.error( err );
				spinner.unblock();

				if ( err && err.type === 'create-order-error' ) {
					return;
				}

				this.errorHandler.genericError();
			},
		};
	}
}

export default CheckoutActionHandler;
