import ErrorHandler from './ErrorHandler';
import Renderer from './Renderer';
import UpdateCart from './UpdateCart';
import SingleProductConfig from './SingleProductConfig';

class SingleProductBootstap {
    init() {
        const buttonWrapper = PayPalCommerceGateway.button.wrapper;

        if (!document.querySelector(buttonWrapper)) {
            return;
        }

        if (!document.querySelector('form.cart')) {
            return;
        }

        const renderer = new Renderer(buttonWrapper);
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
}

export default SingleProductBootstap;