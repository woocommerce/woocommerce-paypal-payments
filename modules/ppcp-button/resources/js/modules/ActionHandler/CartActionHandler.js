import onApprove from '../OnApproveHandler/onApproveForContinue.js';
import { payerData } from '../Helper/PayerData';
import { PaymentMethods } from '../Helper/CheckoutMethodState';

class CartActionHandler {
	constructor( config, errorHandler ) {
		this.config = config;
		this.errorHandler = errorHandler;
	}

	subscriptionsConfiguration( subscription_plan_id ) {
		return {
			createSubscription: ( data, actions ) => {
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
						should_create_wc_order:
							! context.config.vaultingEnabled ||
							data.paymentSource !== 'venmo',
					} ),
				} )
					.then( ( res ) => {
						return res.json();
					} )
					.then( ( data ) => {
						if ( ! data.success ) {
							console.log( data );
							throw Error( data.data.message );
						}

						const orderReceivedUrl = data.data?.order_received_url;

						location.href = orderReceivedUrl
							? orderReceivedUrl
							: context.config.redirect;
					} );
			},
			onError: ( err ) => {
				console.error( err );
			},
		};
	}

	configuration() {
		const createOrder = ( data, actions ) => {
			const payer = payerData();
			const bnCode =
				typeof this.config.bn_codes[ this.config.context ] !==
				'undefined'
					? this.config.bn_codes[ this.config.context ]
					: '';
			return fetch( this.config.ajax.create_order.endpoint, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: this.config.ajax.create_order.nonce,
					purchase_units: [],
					payment_method: PaymentMethods.PAYPAL,
					funding_source: window.ppcpFundingSource,
					bn_code: bnCode,
					payer,
					context: this.config.context,
				} ),
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( data ) {
					if ( ! data.success ) {
						console.error( data );
						throw Error( data.data.message );
					}
					return data.data.id;
				} );
		};

		return {
			createOrder,
			onApprove: onApprove( this, this.errorHandler ),
			onError: ( error ) => {
				this.errorHandler.genericError();
			},
		};
	}
}

export default CartActionHandler;
