import CartActionHandler from '../ActionHandler/CartActionHandler';

class CartBootstrap {
    constructor(gateway, renderer, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.errorHandler = errorHandler;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        this.render();

        jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
            this.render();
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.wrapper) !==
            null || document.querySelector(this.gateway.hosted_fields.wrapper) !==
            null;
    }

    render() {
        const actionHandler = new CartActionHandler(
            PayPalCommerceGateway,
            this.errorHandler,
        );

        this.renderer.render(
            actionHandler.configuration()
        );
    }
}

export default CartBootstrap;
