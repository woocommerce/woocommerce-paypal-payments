import CartActionHandler from '../ActionHandler/CartActionHandler';
import BootstrapHelper from '../Helper/BootstrapHelper';
import { paypalSubscriptionButtonAllowed } from '@ppcp-blocks/Helper/Subscription';

class CartBootstrap {
	constructor( gateway, renderer, errorHandler ) {
		this.gateway = gateway;
		this.renderer = renderer;
		this.errorHandler = errorHandler;

		this.renderer.onButtonsInit(
			this.gateway.button.wrapper,
			() => {
				this.handleButtonStatus();
			},
			true
		);
	}

	init() {
		if ( this.shouldRender() ) {
			this.render();
			this.handleButtonStatus();
		}

		jQuery( document.body ).on(
			'updated_cart_totals updated_checkout',
			() => {
				if ( this.shouldRender() ) {
					this.render();
					this.handleButtonStatus();
				}

				fetch( this.gateway.ajax.cart_script_params.endpoint, {
					method: 'GET',
					credentials: 'same-origin',
				} )
					.then( ( result ) => result.json() )
					.then( ( result ) => {
						if ( ! result.success ) {
							return;
						}

						// handle script reload
						const newParams = result.data.url_params;
						const reloadRequired =
							JSON.stringify( this.gateway.url_params ) !==
							JSON.stringify( newParams );

						if ( reloadRequired ) {
							this.gateway.url_params = newParams;
							jQuery( this.gateway.button.wrapper ).trigger(
								'ppcp-reload-buttons'
							);
						}

						// handle button status
						const newData = {};
						if ( result.data.button ) {
							newData.button = result.data.button;
						}
						if ( result.data.messages ) {
							newData.messages = result.data.messages;
						}
						// Keep the subscription gate in sync with the current cart.
						if (
							typeof result.data.subscription_button_allowed !==
							'undefined'
						) {
							newData.subscription_button_allowed =
								result.data.subscription_button_allowed;
						}
						if ( result.data.locations_with_subscription_product ) {
							newData.locations_with_subscription_product =
								result.data.locations_with_subscription_product;
						}
						if ( newData ) {
							BootstrapHelper.updateScriptData( this, newData );
							if ( this.shouldRender() ) {
								this.render();
							}
							this.handleButtonStatus();
						}

						jQuery( document.body ).trigger(
							'ppcp_cart_total_updated',
							[ result.data.amount ]
						);
					} );
			}
		);
	}

	handleButtonStatus() {
		BootstrapHelper.handleButtonStatus( this );
	}

	shouldRender() {
		return document.querySelector( this.gateway.button.wrapper ) !== null;
	}

	shouldEnable() {
		return BootstrapHelper.shouldEnable( this );
	}

	/**
	 * Whether the button is allowed for the current (subscription) cart.
	 * Shared rule used by the block cart and mini-cart as well.
	 *
	 * @return {boolean} True when the button may be displayed.
	 */
	subscriptionButtonAllowed() {
		return paypalSubscriptionButtonAllowed( this.gateway );
	}

	hide() {
		jQuery( this.gateway.button.wrapper ).hide();
	}

	show() {
		jQuery( this.gateway.button.wrapper ).show();
	}

	render() {
		if ( ! this.shouldRender() ) {
			return;
		}

		// Hide the button for subscription carts PayPal cannot process, so the
		// classic and block carts behave consistently and the button does not
		// render only to be removed afterwards.
		if ( ! this.subscriptionButtonAllowed() ) {
			this.hide();
			return;
		}

		this.show();

		const actionHandler = new CartActionHandler(
			PayPalCommerceGateway,
			this.errorHandler
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
				actionHandler.subscriptionsConfiguration( subscription_plan_id )
			);

			return;
		}

		this.renderer.render( actionHandler.configuration() );

		jQuery( document.body ).trigger( 'ppcp_cart_rendered' );
	}
}

export default CartBootstrap;
