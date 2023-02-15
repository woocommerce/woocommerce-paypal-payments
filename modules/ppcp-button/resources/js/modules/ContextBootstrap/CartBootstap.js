import CartActionHandler from '../ActionHandler/CartActionHandler';
import {setVisible} from "../Helper/Hiding";

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

            fetch(
                this.gateway.ajax.cart_script_params.endpoint,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                }
            )
            .then(result => result.json())
            .then(result => {
                if (! result.success) {
                    return;
                }

                const newParams = result.data;
                const reloadRequired = this.gateway.url_params.intent !== newParams.intent;

                // TODO: should reload the script instead
                setVisible(this.gateway.button.wrapper, !reloadRequired)
            });
        });
    }

    shouldRender() {
        return document.querySelector(this.gateway.button.wrapper) !== null;
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
