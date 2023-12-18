import {show} from "../Helper/Hiding";
import {cardFieldStyles} from "../Helper/CardFieldsHelper";

class CardFieldsRenderer {

    constructor(defaultConfig, errorHandler, spinner) {
        this.defaultConfig = defaultConfig;
        this.errorHandler = errorHandler;
        this.spinner = spinner;
        this.cardValid = false;
        this.formValid = false;
        this.emptyFields = new Set(['number', 'cvv', 'expirationDate']);
        this.currentHostedFieldsInstance = null;
    }

    render(wrapper, contextConfig) {
        if (
            (
                this.defaultConfig.context !== 'checkout'
                && this.defaultConfig.context !== 'pay-now'
            )
            || wrapper === null
            || document.querySelector(wrapper) === null
        ) {
            return;
        }

        const buttonSelector = wrapper + ' button';

        const gateWayBox = document.querySelector('.payment_box.payment_method_ppcp-credit-card-gateway');
        if (!gateWayBox) {
            return
        }

        const oldDisplayStyle = gateWayBox.style.display;
        gateWayBox.style.display = 'block';

        const hideDccGateway = document.querySelector('#ppcp-hide-dcc');
        if (hideDccGateway) {
            hideDccGateway.parentNode.removeChild(hideDccGateway);
        }

        const cardField = paypal.CardFields({
            createOrder: contextConfig.createOrder,
            onApprove: function (data) {
                return contextConfig.onApprove(data);
            },
            onError: function (error) {
                console.error(error)
                this.spinner.unblock();
            }
        });

        if (cardField.isEligible()) {
            const nameField = document.getElementById('ppcp-credit-card-gateway-card-name');
            if (nameField) {
                let styles = cardFieldStyles(nameField);
                cardField.NameField({style: {'input': styles}}).render(nameField.parentNode);
                nameField.remove();
            }

            const numberField = document.getElementById('ppcp-credit-card-gateway-card-number');
            if (numberField) {
                let styles = cardFieldStyles(numberField);
                cardField.NumberField({style: {'input': styles}}).render(numberField.parentNode);
                numberField.remove();
            }

            const expiryField = document.getElementById('ppcp-credit-card-gateway-card-expiry');
            if (expiryField) {
                let styles = cardFieldStyles(expiryField);
                cardField.ExpiryField({style: {'input': styles}}).render(expiryField.parentNode);
                expiryField.remove();
            }

            const cvvField = document.getElementById('ppcp-credit-card-gateway-card-cvc');
            if (cvvField) {
                let styles = cardFieldStyles(cvvField);
                cardField.CVVField({style: {'input': styles}}).render(cvvField.parentNode);
                cvvField.remove();
            }

            document.dispatchEvent(new CustomEvent("hosted_fields_loaded"));
        }

        gateWayBox.style.display = oldDisplayStyle;

        show(buttonSelector);

        document.querySelector(buttonSelector).addEventListener("click", (event) => {
            event.preventDefault();
            this.spinner.block();
            this.errorHandler.clear();

            const paymentToken = document.querySelector('input[name="wc-ppcp-credit-card-gateway-payment-token"]:checked')?.value
            if(paymentToken && paymentToken !== 'new') {
                fetch(this.defaultConfig.ajax.capture_card_payment.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        nonce: this.defaultConfig.ajax.capture_card_payment.nonce,
                        payment_token: paymentToken
                    })
                }).then((res) => {
                    return res.json();
                }).then((data) => {
                    document.querySelector('#place_order').click();
                });

                return;
            }

            cardField.submit()
                .catch((error) => {
                    this.spinner.unblock();
                    console.error(error)
                    this.errorHandler.message(this.defaultConfig.hosted_fields.labels.fields_not_valid);
                });
        });
    }

    disableFields() {}
    enableFields() {}
}

export default CardFieldsRenderer;
