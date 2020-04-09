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

        const buttonWrapper = this.gateway.button.wrapper;

        const toggleButtons = () => {
            const currentPaymentMethod = jQuery(
                'input[name="payment_method"]:checked').val();

            if (currentPaymentMethod !== 'ppcp-gateway') {
                this.renderer.hideButtons(buttonWrapper);
                jQuery('#place_order').show();
            }
            else {
                this.renderer.showButtons(buttonWrapper);
                jQuery('#place_order').hide();
            }
        };

        jQuery(document.body).on('updated_checkout', () => {
            this.renderer.render(
                buttonWrapper,
                this.configurator.configuration(),
            );
            toggleButtons();
        });

        jQuery(document.body).on('payment_method_selected', () => {
            toggleButtons();
        });

        this.renderer.render(
            buttonWrapper,
            this.configurator.configuration(),
        );
    }

    shouldRender() {
        if (document.querySelector(this.gateway.button.cancel_wrapper)) {
            return false;
        }

        return document.querySelector(this.gateway.button.wrapper) !== null;
    }
}

export default CheckoutBootstap;