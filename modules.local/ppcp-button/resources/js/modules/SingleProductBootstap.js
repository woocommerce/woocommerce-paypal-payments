import ErrorHandler from './ErrorHandler';
import Renderer from './Renderer';
import UpdateCart from './UpdateCart';
import SingleProductConfig from './SingleProductConfig';

class SingleProductBootstap {
    init() {
        if (!this.shouldRender()) {
            return;
        }

        const renderer = new Renderer(PayPalCommerceGateway.button.wrapper);
        const errorHandler = new ErrorHandler();
        const updateCart = new UpdateCart(
            PayPalCommerceGateway.ajax.change_cart.endpoint,
            PayPalCommerceGateway.ajax.change_cart.nonce,
        );
        const configurator = new SingleProductConfig(
            PayPalCommerceGateway,
            updateCart,
            renderer.showButtons.bind(renderer),
            renderer.hideButtons.bind(renderer),
            document.querySelector('form.cart'),
            errorHandler,
        );

        renderer.render(configurator.configuration());
    }

    shouldRender() {
        if (document.querySelector('form.cart') === null) {
            return false;
        }

        return document.querySelector(PayPalCommerceGateway.button.wrapper);
    }
}

export default SingleProductBootstap;