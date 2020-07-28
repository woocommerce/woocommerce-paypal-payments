import ErrorHandler from '../ErrorHandler';
import CheckoutActionHandler from '../ActionHandler/CheckoutActionHandler';

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
        this.switchBetweenPayPalandOrderButton();
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
            new ErrorHandler(this.gateway.labels.error.generic),
        );

        this.renderer.render(
            this.gateway.button.wrapper,
            this.gateway.hosted_fields.wrapper,
            actionHandler.configuration(),
        );
    }

    switchBetweenPayPalandOrderButton() {
        const currentPaymentMethod = jQuery(
            'input[name="payment_method"]:checked').val();

        if (currentPaymentMethod !== 'ppcp-gateway') {
            this.renderer.hideButtons(this.gateway.button.wrapper);
            this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            jQuery('#place_order').show();
        }
        else {
            this.renderer.showButtons(this.gateway.button.wrapper);
            this.renderer.showButtons(this.gateway.hosted_fields.wrapper);
            jQuery('#place_order').hide();
        }
    }
}

export default CheckoutBootstap;