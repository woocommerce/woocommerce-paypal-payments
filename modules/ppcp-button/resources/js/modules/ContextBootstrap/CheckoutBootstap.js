import CheckoutActionHandler from '../ActionHandler/CheckoutActionHandler';
import {setVisible, setVisibleByClass} from '../Helper/Hiding';
import {
    getCurrentPaymentMethod,
    isSavedCardSelected, ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "../Helper/CheckoutMethodState";
import BootstrapHelper from "../Helper/BootstrapHelper";

class CheckoutBootstap {
    constructor(gateway, renderer, spinner, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.spinner = spinner;
        this.errorHandler = errorHandler;

        this.standardOrderButtonSelector = ORDER_BUTTON_SELECTOR;

        this.renderer.onButtonsInit(this.gateway.button.wrapper, () => {
            this.handleButtonStatus();
        }, true);
    }

    init() {
        this.render();
        this.handleButtonStatus();

        // Unselect saved card.
        // WC saves form values, so with our current UI it would be a bit weird
        // if the user paid with saved, then after some time tries to pay again,
        // but wants to enter a new card, and to do that they have to choose “Select payment” in the list.
        jQuery('#saved-credit-card').val(jQuery('#saved-credit-card option:first').val());

        jQuery(document.body).on('updated_checkout', () => {
            this.render()
            this.handleButtonStatus();

            if (this.shouldShowMessages() && document.querySelector(this.gateway.messages.wrapper)) { // currently we need amount only for Pay Later
                fetch(
                    this.gateway.ajax.cart_script_params.endpoint,
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                    }
                )
                    .then(result => result.json())
                    .then(result => {
                        if (! result.success) {
                            return;
                        }

                        jQuery(document.body).trigger('ppcp_checkout_total_updated', [result.data.amount]);
                    });
            }
        });

        jQuery(document.body).on('updated_checkout payment_method_selected', () => {
            this.updateUi();
        });

        jQuery(document).on('hosted_fields_loaded', () => {
            jQuery('#saved-credit-card').on('change', () => {
                this.updateUi();
            })
        });

        jQuery(document).on('ppcp_should_show_messages', (e, data) => {
            if (!this.shouldShowMessages()) {
                data.result = false;
            }
        });

        this.updateUi();
    }

    handleButtonStatus() {
        BootstrapHelper.handleButtonStatus(this);
    }

    shouldRender() {
        if (document.querySelector(this.gateway.button.cancel_wrapper)) {
            return false;
        }

        return document.querySelector(this.gateway.button.wrapper) !== null || document.querySelector(this.gateway.hosted_fields.wrapper) !== null;
    }

    shouldEnable() {
        return BootstrapHelper.shouldEnable(this);
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
            this.errorHandler,
            this.spinner
        );

        if(
            PayPalCommerceGateway.data_client_id.has_subscriptions
            && PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled
        ) {
            this.renderer.render(actionHandler.subscriptionsConfiguration(), {}, actionHandler.configuration());

            if(!PayPalCommerceGateway.subscription_product_allowed) {
                this.gateway.button.is_disabled = true;
                this.handleButtonStatus();
            }

            return;
        }

        this.renderer.render(actionHandler.configuration(), {}, actionHandler.configuration());
    }

    updateUi() {
        const currentPaymentMethod = getCurrentPaymentMethod();
        const isPaypal = currentPaymentMethod === PaymentMethods.PAYPAL;
        const isCard = currentPaymentMethod === PaymentMethods.CARDS;
        const isSeparateButtonGateway = [PaymentMethods.CARD_BUTTON].includes(currentPaymentMethod);
        const isSavedCard = isCard && isSavedCardSelected();
        const isNotOurGateway = !isPaypal && !isCard && !isSeparateButtonGateway;
        const isFreeTrial = PayPalCommerceGateway.is_free_trial_cart;
        const hasVaultedPaypal = PayPalCommerceGateway.vaulted_paypal_email !== '';

        const paypalButtonWrappers = {
            ...Object.entries(PayPalCommerceGateway.separate_buttons)
                .reduce((result, [k, data]) => {
                    return {...result, [data.id]: data.wrapper}
                }, {}),
        };

        setVisibleByClass(this.standardOrderButtonSelector, (isPaypal && isFreeTrial && hasVaultedPaypal) || isNotOurGateway || isSavedCard, 'ppcp-hidden');
        setVisible('.ppcp-vaulted-paypal-details', isPaypal);
        setVisible(this.gateway.button.wrapper, isPaypal && !(isFreeTrial && hasVaultedPaypal));
        setVisible(this.gateway.hosted_fields.wrapper, isCard && !isSavedCard);
        for (const [gatewayId, wrapper] of Object.entries(paypalButtonWrappers)) {
            setVisible(wrapper, gatewayId === currentPaymentMethod);
        }

        if (isCard) {
            if (isSavedCard) {
                this.disableCreditCardFields();
            } else {
                this.enableCreditCardFields();
            }
        }

        jQuery(document.body).trigger('ppcp_checkout_rendered');
    }

    shouldShowMessages() {
        return getCurrentPaymentMethod() === PaymentMethods.PAYPAL
            && !PayPalCommerceGateway.is_free_trial_cart;
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
