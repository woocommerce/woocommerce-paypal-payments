import ErrorHandler from './ErrorHandler';
import UpdateCart from './UpdateCart';
import SingleProductConfig from './SingleProductConfig';

class SingleProductBootstap {
    constructor(renderer) {
        this.renderer = renderer;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        const errorHandler = new ErrorHandler();
        const updateCart = new UpdateCart(
            PayPalCommerceGateway.ajax.change_cart.endpoint,
            PayPalCommerceGateway.ajax.change_cart.nonce,
        );
        const configurator = new SingleProductConfig(
            PayPalCommerceGateway,
            updateCart,
            () => {
                this.renderer.showButtons(PayPalCommerceGateway.button.wrapper);
            },
            () => {
                this.renderer.hideButtons(PayPalCommerceGateway.button.wrapper);
            },
            document.querySelector('form.cart'),
            errorHandler,
        );

        this.renderer.render(
            PayPalCommerceGateway.button.wrapper,
            configurator.configuration(),
        );
    }

    shouldRender() {
        if (document.querySelector('form.cart') === null) {
            return false;
        }

        return document.querySelector(PayPalCommerceGateway.button.wrapper);
    }
}

export default SingleProductBootstap;