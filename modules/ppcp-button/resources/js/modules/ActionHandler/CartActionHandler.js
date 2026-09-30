import onApprove from '../OnApproveHandler/onApproveForContinue.js';
import { payerData } from '../Helper/PayerData';
import { PaymentMethods } from '../Helper/CheckoutMethodState';
import ResumeFlowHelper from '../Helper/ResumeFlowHelper';
import {
	approvalRedirectUrl as resolveApprovalRedirectUrl,
} from '../Helper/Subscriptions';

class CartActionHandler {
	constructor( config, errorHandler ) {
		this.config = config;
		this.errorHandler = errorHandler;
	}

	subscriptionsConfiguration( subscriptionPlanId ) {
		return {
			createSubscription: ( data, actions ) => {
				return actions.subscription.create( {
					plan_id: subscriptionPlanId,
					custom_id: this.config.subscription_custom_id,
				} );
			},
			// Async so the SDK receives the rejection: returning nothing left a
			// failed approval as an unhandled rejection and told the shopper
			// nothing.
			onApprove: async ( data ) => {
				const res = await fetch(
					this.config.ajax.approve_subscription.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						body: JSON.stringify( {
							nonce: this.config.ajax.approve_subscription.nonce,
							order_id: data.orderID,
							subscription_id: data.subscriptionID,
							should_create_wc_order:
								! this.config.vaultingEnabled ||
								data.paymentSource !== 'venmo',
						} ),
					}
				);

				location.href = this.approvalRedirectUrl( await res.json() );
			},
			onError: ( err ) => {
				console.error( err );
			},
		};
	}

	/**
	 * Where to send the shopper once the subscription has been approved.
	 *
	 * @param {Object} response - The approve endpoint response.
	 * @return {string} The URL to navigate to.
	 * @throws {Error} When the endpoint reported a failure.
	 */
	approvalRedirectUrl( response ) {
		return resolveApprovalRedirectUrl(
			response,
			this.errorHandler,
			this.config.redirect
		);
	}

	configuration() {
		const errorHandler = this.errorHandler;
		const createOrder = () => {
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
						errorHandler.clear();
						errorHandler.message( data.data.message );
						throw { type: 'create-order-error' };
					}
					return data.data.id;
				} );
		};

		return {
			createOrder,
			onApprove: onApprove( this, this.errorHandler ),
			onCancel: () => {
				ResumeFlowHelper.reloadButtonsIfRequired(
					this.config.button.wrapper
				);
			},
			onError: ( err ) => {
				if ( ! err || err.type !== 'create-order-error' ) {
					this.errorHandler.genericError();
				}

				ResumeFlowHelper.reloadButtonsIfRequired(
					this.config.button.wrapper
				);
			},
		};
	}
}

export default CartActionHandler;
