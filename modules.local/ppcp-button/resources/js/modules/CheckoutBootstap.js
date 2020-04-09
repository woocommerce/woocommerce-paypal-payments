import Renderer from './Renderer';

class CheckoutBootstap {
    constructor(configurator) {
        this.configurator = configurator;
    }

    init() {
        if (!this.shouldRender()) {
            return;
        }

        const renderer = new Renderer(PayPalCommerceGateway.button.wrapper);
        const toggleButtons = () => {
            const currentPaymentMethod = jQuery(
                'input[name="payment_method"]:checked').val();

            if (currentPaymentMethod !== 'ppcp-gateway') {
                renderer.hideButtons();
                jQuery('#place_order').show();
            }
            else {
                renderer.showButtons();
                jQuery('#place_order').hide();
            }
        };

        jQuery(document.body).on('updated_checkout', () => {
            renderer.render(this.configurator.configuration());
            toggleButtons();
        });

        jQuery(document.body).on('payment_method_selected', () => {
            toggleButtons();
        });

        renderer.render(this.configurator.configuration());
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