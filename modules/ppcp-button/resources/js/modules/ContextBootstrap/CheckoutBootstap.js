import ErrorHandler from '../ErrorHandler';
import CheckoutActionHandler from '../ActionHandler/CheckoutActionHandler';
import { setVisible } from '../Helper/Hiding';
import {
    getCurrentPaymentMethod,
    isSavedCardSelected, ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "../Helper/CheckoutMethodState";

class CheckoutBootstap {
    constructor(gateway, renderer, messages, spinner) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.spinner = spinner;

        this.standardOrderButtonSelector = ORDER_BUTTON_SELECTOR;

        this.buttonChangeObserver = new MutationObserver((el) => {
            this.updateUi();
        });
    }

    init() {
        this.render();

        // Unselect saved card.
        // WC saves form values, so with our current UI it would be a bit weird
        // if the user paid with saved, then after some time tries to pay again,
        // but wants to enter a new card, and to do that they have to choose “Select payment” in the list.
        jQuery('#saved-credit-card').val(jQuery('#saved-credit-card option:first').val());

        jQuery(document.body).on('updated_checkout', () => {
            this.render()
        });

        jQuery(document.body).on('updated_checkout payment_method_selected', () => {
            this.updateUi();
        });

        jQuery(document).on('hosted_fields_loaded', () => {
            jQuery('#saved-credit-card').on('change', () => {
                this.updateUi();
            })
        });

        this.updateUi();
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

        this.buttonChangeObserver.observe(
            document.querySelector(this.standardOrderButtonSelector),
            {attributes: true}
        );
    }

    updateUi() {
        const currentPaymentMethod = getCurrentPaymentMethod();
        const isPaypal = currentPaymentMethod === PaymentMethods.PAYPAL;
        const isCard = currentPaymentMethod === PaymentMethods.CARDS;
        const isSavedCard = isCard && isSavedCardSelected();
        const isNotOurGateway = !isPaypal && !isCard;
        const isFreeTrial = PayPalCommerceGateway.is_free_trial_cart;
        const hasVaultedPaypal = PayPalCommerceGateway.vaulted_paypal_email !== '';

        setVisible(this.standardOrderButtonSelector,  (isPaypal && isFreeTrial && hasVaultedPaypal) || isNotOurGateway || isSavedCard, true);
        setVisible('.ppcp-vaulted-paypal-details', isPaypal);
        setVisible(this.gateway.button.wrapper, isPaypal && !(isFreeTrial && hasVaultedPaypal));
        setVisible(this.gateway.messages.wrapper, isPaypal && !isFreeTrial);
        setVisible(this.gateway.hosted_fields.wrapper, isCard && !isSavedCard);

        if (isPaypal && !isFreeTrial) {
            this.messages.render();
        }

        if (isCard) {
            if (isSavedCard) {
                this.disableCreditCardFields();
            } else {
                this.enableCreditCardFields();
            }
        }
    }

    disableCreditCardFields() {
        jQuery('label[for="ppcp-credit-card-gateway-card-number"]').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-gateway-card-number').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('label[for="ppcp-credit-card-gateway-card-expiry"]').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-gateway-card-expiry').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('label[for="ppcp-credit-card-gateway-card-cvc"]').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-gateway-card-cvc').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('label[for="vault"]').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-vault').addClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-vault').attr("disabled", true)
        this.renderer.disableCreditCardFields()
    }

    enableCreditCardFields() {
        jQuery('label[for="ppcp-credit-card-gateway-card-number"]').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-gateway-card-number').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('label[for="ppcp-credit-card-gateway-card-expiry"]').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-gateway-card-expiry').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('label[for="ppcp-credit-card-gateway-card-cvc"]').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-gateway-card-cvc').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('label[for="vault"]').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-vault').removeClass('ppcp-credit-card-gateway-form-field-disabled')
        jQuery('#ppcp-credit-card-vault').attr("disabled", false)
        this.renderer.enableCreditCardFields()
    }
}

export default CheckoutBootstap
