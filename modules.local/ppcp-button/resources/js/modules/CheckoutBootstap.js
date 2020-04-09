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

        jQuery(document.body).on('updated_checkout', () => {
            renderer.render(this.configurator.configuration());

            jQuery(document.body).trigger('payment_method_selected');
        });

        jQuery(document.body).on('payment_method_selected', () => {
            // TODO: replace this dirty check, possible create a separate
            const currentPaymentMethod = jQuery(
                'input[name="payment_method"]:checked').val();

            if (currentPaymentMethod !== 'ppcp-gateway') {
                jQuery(PayPalCommerceGateway.button.wrapper).hide();
                jQuery('#place_order').show();
            }
            else {
                jQuery(PayPalCommerceGateway.button.wrapper).show();
                jQuery('#place_order').hide();
            }
        });

        renderer.render(this.configurator.configuration());
    }

    shouldRender() {
        if (document.querySelector(PayPalCommerceGateway.button.cancel_wrapper)) {
            return false;
        }

        return document.querySelector(PayPalCommerceGateway.button.wrapper);
    }
}

export default CheckoutBootstap;