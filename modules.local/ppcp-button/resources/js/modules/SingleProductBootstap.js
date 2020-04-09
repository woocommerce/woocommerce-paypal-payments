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
        this.configurator = new SingleProductConfig(
            this.gateway,
            updateCart,
            () => {
                this.renderer.showButtons(this.gateway.button.wrapper);
            },
            () => {
                this.renderer.hideButtons(this.gateway.button.wrapper);
            },
            document.querySelector('form.cart'),
            errorHandler,
        );

        this.render();
    }

    shouldRender() {
        if (document.querySelector('form.cart') === null) {
            return false;
        }

        return document.querySelector(this.gateway.button.wrapper) !== null;
    }

    render() {
        this.renderer.render(
            this.gateway.button.wrapper,
            this.configurator.configuration(),
        );
    }
}

export default SingleProductBootstap;