import ErrorHandler from './ErrorHandler';
import UpdateCart from './UpdateCart';
import SingleProductConfig from './SingleProductConfig';

class SingleProductBootstap {
    constructor(gateway, renderer) {
        this.gateway = gateway;
        this.renderer = renderer;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        const errorHandler = new ErrorHandler();
        const updateCart = new UpdateCart(
            this.gateway.ajax.change_cart.endpoint,
            this.gateway.ajax.change_cart.nonce,
        );
        const buttonWrapper = this.gateway.button.wrapper;
        const configurator = new SingleProductConfig(
            this.gateway,
            updateCart,
            () => {
                this.renderer.showButtons(buttonWrapper);
            },
            () => {
                this.renderer.hideButtons(buttonWrapper);
            },
            document.querySelector('form.cart'),
            errorHandler,
        );

        this.renderer.render(
            buttonWrapper,
            configurator.configuration(),
        );
    }

    shouldRender() {
        if (document.querySelector('form.cart') === null) {
            return false;
        }

        return document.querySelector(this.gateway.button.wrapper) !== null;
    }
}

export default SingleProductBootstap;