import ErrorHandler from './ErrorHandler';
import CheckoutActionHandler from './CheckoutActionHandler';

class CheckoutBootstap {
    constructor(gateway, renderer) {
        this.gateway = gateway;
        this.renderer = renderer;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        this.render();

        jQuery(document.body).on('updated_checkout', () => {
            this.render();
        });

        jQuery(document.body).
            on('updated_checkout payment_method_selected', () => {
                this.switchBetweenPayPalandOrderButton();
            });
    }

    shouldRender() {
        if (document.querySelector(this.gateway.button.cancel_wrapper)) {
            return false;
        }

        return document.querySelector(this.gateway.button.wrapper) !== null;
    }

    render() {
        const actionHandler = new CheckoutActionHandler(
            PayPalCommerceGateway,
            new ErrorHandler(),
        );

        this.renderer.render(
            this.gateway.button.wrapper,
            actionHandler.configuration(),
        );
    }

    switchBetweenPayPalandOrderButton() {
        const currentPaymentMethod = jQuery(
            'input[name="payment_method"]:checked').val();

        if (currentPaymentMethod !== 'ppcp-gateway') {
            this.renderer.hideButtons(this.gateway.button.wrapper);
            jQuery('#place_order').show();
        }
        else {
            this.renderer.showButtons(this.gateway.button.wrapper);
            jQuery('#place_order').hide();
        }
    }
}

export default CheckoutBootstap;