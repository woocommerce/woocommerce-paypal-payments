import CartActionHandler from '../ActionHandler/CartActionHandler';
import BootstrapHelper from '../Helper/BootstrapHelper';
import { paypalSubscriptionButtonAllowed } from '@ppcp-blocks/Helper/Subscription';
import { debounce } from '@ppcp-blocks/Helper/debounce';

class MiniCartBootstrap {
	constructor( gateway, renderer, errorHandler ) {
		this.gateway = gateway;
		this.renderer = renderer;
		this.errorHandler = errorHandler;
		this.actionHandler = null;
		this.lastItemsCount = null;
	}

	init() {
		/*
		The context coming from the server can be inaccurate because the product
		context takes precedence over the mini-cart context, so we hardcode it.
		 */
		const miniCartConfig = {
			...PayPalCommerceGateway,
			context: 'mini-cart',
		};

		this.actionHandler = new CartActionHandler(
			miniCartConfig,
			this.errorHandler
		);
		this.gateway = miniCartConfig;
		this.render();
		this.handleButtonStatus();

		jQuery( document.body ).on(
			'wc_fragments_loaded wc_fragments_refreshed',
			() => {
				this.render();
				this.handleButtonStatus();
			}
		);

		/*
		The block cart/mini-cart mutates the `wc/store/cart` data store instead of
		firing the jQuery fragment events above, so subscribe to it to keep the
		mini-cart button in sync (and clear it when the cart becomes empty).
		 */
		if ( typeof wp !== 'undefined' && wp.data?.subscribe ) {
			wp.data.subscribe(
				debounce( () => {
					this._handleStoreCartChange();
				}, 300 )
			);
		}

		this.renderer.onButtonsInit(
			this.gateway.button.mini_cart_wrapper,
			() => {
				this.handleButtonStatus();
			},
			true
		);
	}

	/**
	 * @private
	 */
	_handleStoreCartChange() {
		if ( typeof wp === 'undefined' || ! wp.data?.select ) {
			return;
		}

		const cart = wp.data.select( 'wc/store/cart' );
		if ( ! cart ) {
			return;
		}

		const itemsCount =
			cart.getCartData?.()?.itemsCount ?? cart.getItemsCount?.() ?? null;
		if ( itemsCount === null || itemsCount === this.lastItemsCount ) {
			return;
		}
		this.lastItemsCount = itemsCount;

		if ( itemsCount === 0 ) {
			this.hide();
			return;
		}

		this.render();
		this.handleButtonStatus();
	}

	handleButtonStatus() {
		BootstrapHelper.handleButtonStatus( this, {
			wrapper: this.gateway.button.mini_cart_wrapper,
			skipMessages: true,
		} );
	}

	shouldRender() {
		return (
			document.querySelector( this.gateway.button.mini_cart_wrapper ) !==
				null ||
			document.querySelector(
				this.gateway.hosted_fields.mini_cart_wrapper
			) !== null
		);
	}

	shouldEnable() {
		return BootstrapHelper.shouldEnable( this, {
			isDisabled: !! this.gateway.button.is_mini_cart_disabled,
		} );
	}

	/**
	 * Whether the button is allowed for the current (subscription) cart.
	 * Shared rule used by the classic cart and block cart as well.
	 *
	 * @return {boolean} True when the button may be displayed.
	 */
	subscriptionButtonAllowed() {
		return paypalSubscriptionButtonAllowed( this.gateway );
	}

	hide() {
		jQuery( this.gateway.button.mini_cart_wrapper ).empty().hide();
	}

	render() {
		if ( ! this.shouldRender() ) {
			return;
		}

		// Hide the button for subscription carts PayPal cannot process, so the
		// mini-cart behaves consistently with the classic and block carts.
		if ( ! this.subscriptionButtonAllowed() ) {
			this.hide();
			return;
		}

		jQuery( this.gateway.button.mini_cart_wrapper ).show();

		this.renderer.render( this.actionHandler.configuration(), {
			button: {
				wrapper: this.gateway.button.mini_cart_wrapper,
				style: this.gateway.button.mini_cart_style,
			},
		} );
	}
}

export default MiniCartBootstrap;
