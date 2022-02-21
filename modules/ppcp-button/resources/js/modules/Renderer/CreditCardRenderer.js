import dccInputFactory from "../Helper/DccInputFactory";

class CreditCardRenderer {

    constructor(defaultConfig, errorHandler, spinner) {
        this.defaultConfig = defaultConfig;
        this.errorHandler = errorHandler;
        this.spinner = spinner;
        this.cardValid = false;
        this.formValid = false;
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
        if (
            typeof paypal.HostedFields === 'undefined'
            || ! paypal.HostedFields.isEligible()
        ) {
            const wrapperElement = document.querySelector(wrapper);
            wrapperElement.parentNode.removeChild(wrapperElement);
            return;
        }

        if (this.currentHostedFieldsInstance) {
            this.currentHostedFieldsInstance.teardown()
                .catch(err => console.error(`Hosted fields teardown error: ${err}`));
            this.currentHostedFieldsInstance = null;
        }

        const gateWayBox = document.querySelector('.payment_box.payment_method_ppcp-credit-card-gateway');
        const oldDisplayStyle = gateWayBox.style.display;
        gateWayBox.style.display = 'block';

        const hideDccGateway = document.querySelector('#ppcp-hide-dcc');
        if (hideDccGateway) {
            hideDccGateway.parentNode.removeChild(hideDccGateway);
        }

        const cardNumberField = document.querySelector('#ppcp-credit-card-gateway-card-number');

        const stylesRaw = window.getComputedStyle(cardNumberField);
        let styles = {};
        Object.values(stylesRaw).forEach( (prop) => {
            if (! stylesRaw[prop]) {
                return;
            }
            styles[prop] = '' + stylesRaw[prop];
        });

        const cardNumber = dccInputFactory(cardNumberField);
        cardNumberField.parentNode.replaceChild(cardNumber, cardNumberField);

        const cardExpiryField = document.querySelector('#ppcp-credit-card-gateway-card-expiry');
        const cardExpiry = dccInputFactory(cardExpiryField);
        cardExpiryField.parentNode.replaceChild(cardExpiry, cardExpiryField);

        const cardCodeField = document.querySelector('#ppcp-credit-card-gateway-card-cvc');
        const cardCode = dccInputFactory(cardCodeField);
        cardCodeField.parentNode.replaceChild(cardCode, cardCodeField);

        gateWayBox.style.display = oldDisplayStyle;

        const formWrapper = '.payment_box payment_method_ppcp-credit-card-gateway';
        if (
            this.defaultConfig.enforce_vault
            && document.querySelector(formWrapper + ' .ppcp-credit-card-vault')
        ) {
            document.querySelector(formWrapper + ' .ppcp-credit-card-vault').checked = true;
            document.querySelector(formWrapper + ' .ppcp-credit-card-vault').setAttribute('disabled', true);
        }
        paypal.HostedFields.render({
            createOrder: contextConfig.createOrder,
            styles: {
                'input': styles
            },
            fields: {
                number: {
                    selector: '#ppcp-credit-card-gateway-card-number',
                    placeholder: this.defaultConfig.hosted_fields.labels.credit_card_number,
                },
                cvv: {
                    selector: '#ppcp-credit-card-gateway-card-cvc',
                    placeholder: this.defaultConfig.hosted_fields.labels.cvv,
                },
                expirationDate: {
                    selector: '#ppcp-credit-card-gateway-card-expiry',
                    placeholder: this.defaultConfig.hosted_fields.labels.mm_yy,
                }
            }
        }).then(hostedFields => {
            document.dispatchEvent(new CustomEvent("hosted_fields_loaded"));
            this.currentHostedFieldsInstance = hostedFields;

            hostedFields.on('inputSubmitRequest', () => {
                this._submit(contextConfig);
            });
            hostedFields.on('cardTypeChange', (event) => {
                if ( ! event.cards.length ) {
                    this.cardValid = false;
                    return;
                }
                const validCards = this.defaultConfig.hosted_fields.valid_cards;
                this.cardValid = validCards.indexOf(event.cards[0].type) !== -1;
            })
            hostedFields.on('validityChange', (event) => {
                const formValid = Object.keys(event.fields).every(function (key) {
                    return event.fields[key].isValid;
                });
               this.formValid = formValid;

            });

            if (document.querySelector(wrapper).getAttribute('data-ppcp-subscribed') !== true) {
                document.querySelector(wrapper + ' button').addEventListener(
                    'click',
                    event => {
                        event.preventDefault();
                        this._submit(contextConfig);
                    }
                );

                document.querySelector(wrapper).setAttribute('data-ppcp-subscribed', true);
            }
        });

        document.querySelector('#payment_method_ppcp-credit-card-gateway').addEventListener(
            'click',
            () => {
                document.querySelector('label[for=ppcp-credit-card-gateway-card-number]').click();
            }
        )
    }

    disableFields() {
        if (this.currentHostedFieldsInstance) {
            this.currentHostedFieldsInstance.setAttribute({
                field: 'number',
                attribute: 'disabled'
            })
            this.currentHostedFieldsInstance.setAttribute({
                field: 'cvv',
                attribute: 'disabled'
            })
            this.currentHostedFieldsInstance.setAttribute({
                field: 'expirationDate',
                attribute: 'disabled'
            })
        }
    }

    enableFields() {
        if (this.currentHostedFieldsInstance) {
            this.currentHostedFieldsInstance.removeAttribute({
                field: 'number',
                attribute: 'disabled'
            })
            this.currentHostedFieldsInstance.removeAttribute({
                field: 'cvv',
                attribute: 'disabled'
            })
            this.currentHostedFieldsInstance.removeAttribute({
                field: 'expirationDate',
                attribute: 'disabled'
            })
        }
    }

    _submit(contextConfig) {
        this.spinner.block();
        this.errorHandler.clear();

        if (this.formValid && this.cardValid) {
            const save_card = this.defaultConfig.save_card ? true : false;
            let vault = document.getElementById('ppcp-credit-card-vault') ?
                document.getElementById('ppcp-credit-card-vault').checked : save_card;
            if (this.defaultConfig.enforce_vault) {
                vault = true;
            }
            const contingency = this.defaultConfig.hosted_fields.contingency;
            const hostedFieldsData = {
                vault: vault
            };
            if (contingency !== 'NO_3D_SECURE') {
                hostedFieldsData.contingencies = [contingency];
            }

            if (this.defaultConfig.payer) {
                hostedFieldsData.cardholderName = this.defaultConfig.payer.name.given_name + ' ' + this.defaultConfig.payer.name.surname;
            }
            if (!hostedFieldsData.cardholderName) {
                const firstName = document.getElementById('billing_first_name') ? document.getElementById('billing_first_name').value : '';
                const lastName = document.getElementById('billing_last_name') ? document.getElementById('billing_last_name').value : '';

                if (!firstName || !lastName) {
                    this.spinner.unblock();
                    this.errorHandler.message(this.defaultConfig.hosted_fields.labels.cardholder_name_required);
                    return;
                }

                hostedFieldsData.cardholderName = firstName + ' ' + lastName;
            }

            this.currentHostedFieldsInstance.submit(hostedFieldsData).then((payload) => {
                payload.orderID = payload.orderId;
                this.spinner.unblock();
                return contextConfig.onApprove(payload);
            }).catch(err => {
                console.error(err);
                this.spinner.unblock();
            });
        } else {
            this.spinner.unblock();
            const message = ! this.cardValid ? this.defaultConfig.hosted_fields.labels.card_not_supported : this.defaultConfig.hosted_fields.labels.fields_not_valid;
            this.errorHandler.message(message);
        }
    }
}
export default CreditCardRenderer;
