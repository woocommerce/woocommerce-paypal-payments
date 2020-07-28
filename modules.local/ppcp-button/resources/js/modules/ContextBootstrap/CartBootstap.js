import CartActionHandler from '../ActionHandler/CartActionHandler';
import ErrorHandler from '../ErrorHandler';

class CartBootstrap {
    constructor(gateway, renderer) {
        this.gateway = gateway;
        this.renderer = renderer;
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
            new ErrorHandler(this.gateway.labels.error.generic),
        );

        this.renderer.render(
            this.gateway.button.wrapper,
            this.gateway.hosted_fields.wrapper,
            actionHandler.configuration(),
        );
    }
}

export default CartBootstrap;