class CheckoutBootstap {
    constructor(renderer, configurator) {
        this.renderer = renderer;
        this.configurator = configurator;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        const toggleButtons = () => {
            const currentPaymentMethod = jQuery(
                'input[name="payment_method"]:checked').val();

            if (currentPaymentMethod !== 'ppcp-gateway') {
                this.renderer.hideButtons(PayPalCommerceGateway.button.wrapper);
                jQuery('#place_order').show();
            }
            else {
                this.renderer.showButtons(PayPalCommerceGateway.button.wrapper);
                jQuery('#place_order').hide();
            }
        };

        jQuery(document.body).on('updated_checkout', () => {
            this.renderer.render(
                PayPalCommerceGateway.button.wrapper,
                this.configurator.configuration(),
            );
            toggleButtons();
        });

        jQuery(document.body).on('payment_method_selected', () => {
            toggleButtons();
        });

        this.renderer.render(
            PayPalCommerceGateway.button.wrapper,
            this.configurator.configuration(),
        );
    }

    shouldRender() {
        if (document.querySelector(
            PayPalCommerceGateway.button.cancel_wrapper)) {
            return false;
        }

        return document.querySelector(PayPalCommerceGateway.button.wrapper);
    }
}

export default CheckoutBootstap;