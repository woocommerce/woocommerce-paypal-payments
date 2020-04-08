import Renderer from './Renderer';

class CheckoutBootstap {
    constructor(configurator) {
        this.configurator = configurator;
    }

    init() {
        const buttonWrapper = PayPalCommerceGateway.button.wrapper;
        const cancelWrapper = PayPalCommerceGateway.button.cancel_wrapper;

        if (!document.querySelector(buttonWrapper)) {
            return;
        }

        const renderer = new Renderer(buttonWrapper);

        jQuery(document.body).on('updated_checkout', () => {
            if (document.querySelector(cancelWrapper)) {
                return;
            }

            renderer.render(this.configurator.configuration());

            jQuery(document.body).trigger('payment_method_selected');
        });

        jQuery(document.body).on('payment_method_selected', () => {
            // TODO: replace this dirty check, possible create a separate
            const currentPaymentMethod = jQuery(
                'input[name="payment_method"]:checked').val();

            if (currentPaymentMethod !== 'ppcp-gateway') {
                jQuery(buttonWrapper).hide();
                jQuery('#place_order').show();
            }
            else {
                jQuery(buttonWrapper).show();
                jQuery('#place_order').hide();
            }
        });

        renderer.render(this.configurator.configuration());
    }
}

export default CheckoutBootstap;