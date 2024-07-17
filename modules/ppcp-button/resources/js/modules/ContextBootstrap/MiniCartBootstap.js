import CartActionHandler from '../ActionHandler/CartActionHandler';
import BootstrapHelper from '../Helper/BootstrapHelper';

class MiniCartBootstap {
	constructor( gateway, renderer, errorHandler ) {
		this.gateway = gateway;
		this.renderer = renderer;
		this.errorHandler = errorHandler;
		this.actionHandler = null;
	}

	init() {
		this.actionHandler = new CartActionHandler(
			PayPalCommerceGateway,
			this.errorHandler
		);
		this.render();
		this.handleButtonStatus();

		jQuery( document.body ).on(
			'wc_fragments_loaded wc_fragments_refreshed',
			() => {
				this.render();
				this.handleButtonStatus();
			}
		);

		this.renderer.onButtonsInit(
			this.gateway.button.mini_cart_wrapper,
			() => {
				this.handleButtonStatus();
			},
			true
		);
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

	render() {
		if ( ! this.shouldRender() ) {
			return;
		}

		this.renderer.render( this.actionHandler.configuration(), {
			button: {
				wrapper: this.gateway.button.mini_cart_wrapper,
				style: this.gateway.button.mini_cart_style,
			},
		} );
	}
}

export default MiniCartBootstap;
