class CheckoutBootstap {
    constructor(gateway, renderer, configurator) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.configurator = configurator;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        this.render();

        jQuery(document.body).on('updated_checkout', () => {
            this.render();
        });

        jQuery(document.body).on('updated_checkout payment_method_selected', () => {
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
        this.renderer.render(
            this.gateway.button.wrapper,
            this.configurator.configuration(),
        );
    }

    switchBetweenPayPalandOrderButton() {
        const currentPaymentMethod = jQuery('input[name="payment_method"]:checked').val();

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