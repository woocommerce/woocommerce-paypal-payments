import ErrorHandler from '../ErrorHandler';
import CheckoutActionHandler from '../ActionHandler/CheckoutActionHandler';

class PayNowBootstrap {
    constructor(gateway, renderer, messages, spinner) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.spinner = spinner;
    }

    init() {

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

        return document.querySelector(this.gateway.button.wrapper) !== null || document.querySelector(this.gateway.hosted_fields.wrapper) !== null;
    }

    render() {
        if (!this.shouldRender()) {
            return;
        }
        if (document.querySelector(this.gateway.hosted_fields.wrapper + '>div')) {
            document.querySelector(this.gateway.hosted_fields.wrapper + '>div').setAttribute('style', '');
        }
        const actionHandler = new CheckoutActionHandler(
            PayPalCommerceGateway,
            new ErrorHandler(this.gateway.labels.error.generic),
            this.spinner
        );

        this.renderer.render(
            this.gateway.button.wrapper,
            this.gateway.hosted_fields.wrapper,
            actionHandler.configuration(),
        );
    }

    switchBetweenPayPalandOrderButton() {
        const urlParams = new URLSearchParams(window.location.search)
        if (urlParams.has('change_payment_method')) {
            return
        }

        const currentPaymentMethod = jQuery(
            'input[name="payment_method"]:checked').val();

        if (currentPaymentMethod !== 'ppcp-gateway' && currentPaymentMethod !== 'ppcp-credit-card-gateway') {
            this.renderer.hideButtons(this.gateway.button.wrapper);
            this.renderer.hideButtons(this.gateway.messages.wrapper);
            this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            jQuery('#place_order').show();
        }
        else {
            jQuery('#place_order').hide();
            if (currentPaymentMethod === 'ppcp-gateway') {
                this.renderer.showButtons(this.gateway.button.wrapper);
                this.renderer.showButtons(this.gateway.messages.wrapper);
                this.messages.render();
                this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            }
            if (currentPaymentMethod === 'ppcp-credit-card-gateway') {
                this.renderer.hideButtons(this.gateway.button.wrapper);
                this.renderer.hideButtons(this.gateway.messages.wrapper);
                this.renderer.showButtons(this.gateway.hosted_fields.wrapper);
            }
        }
    }
}

export default PayNowBootstrap;
