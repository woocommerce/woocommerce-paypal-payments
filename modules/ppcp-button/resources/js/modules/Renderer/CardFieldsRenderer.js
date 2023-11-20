import {show} from "../Helper/Hiding";

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
                let styles = this.cardFieldStyles(nameField);
                cardField.NameField({style: {'input': styles}}).render(nameField.parentNode);
                nameField.remove();
            }

            const numberField = document.getElementById('ppcp-credit-card-gateway-card-number');
            if (numberField) {
                let styles = this.cardFieldStyles(numberField);
                cardField.NumberField({style: {'input': styles}}).render(numberField.parentNode);
                numberField.remove();
            }

            const expiryField = document.getElementById('ppcp-credit-card-gateway-card-expiry');
            if (expiryField) {
                let styles = this.cardFieldStyles(expiryField);
                cardField.ExpiryField({style: {'input': styles}}).render(expiryField.parentNode);
                expiryField.remove();
            }

            const cvvField = document.getElementById('ppcp-credit-card-gateway-card-cvc');
            if (cvvField) {
                let styles = this.cardFieldStyles(cvvField);
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

            cardField.submit()
                .catch((error) => {
                    this.spinner.unblock();
                    console.error(error)
                    this.errorHandler.message(this.defaultConfig.hosted_fields.labels.fields_not_valid);
                })
        });
    }

    cardFieldStyles(field) {
        const allowedProperties = [
            'appearance',
            'color',
            'direction',
            'font',
            'font-family',
            'font-size',
            'font-size-adjust',
            'font-stretch',
            'font-style',
            'font-variant',
            'font-variant-alternates',
            'font-variant-caps',
            'font-variant-east-asian',
            'font-variant-ligatures',
            'font-variant-numeric',
            'font-weight',
            'letter-spacing',
            'line-height',
            'opacity',
            'outline',
            'padding',
            'padding-bottom',
            'padding-left',
            'padding-right',
            'padding-top',
            'text-shadow',
            'transition',
            '-moz-appearance',
            '-moz-osx-font-smoothing',
            '-moz-tap-highlight-color',
            '-moz-transition',
            '-webkit-appearance',
            '-webkit-osx-font-smoothing',
            '-webkit-tap-highlight-color',
            '-webkit-transition',
        ];

        const stylesRaw = window.getComputedStyle(field);
        const styles = {};
        Object.values(stylesRaw).forEach((prop) => {
            if (!stylesRaw[prop] || !allowedProperties.includes(prop)) {
                return;
            }
            styles[prop] = '' + stylesRaw[prop];
        });

        return styles;
    }
}

export default CardFieldsRenderer;
