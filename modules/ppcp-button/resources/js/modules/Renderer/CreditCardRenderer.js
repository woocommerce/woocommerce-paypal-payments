import dccInputFactory from "../Helper/DccInputFactory";

class CreditCardRenderer {

    constructor(defaultConfig, errorHandler, spinner) {
        this.defaultConfig = defaultConfig;
        this.errorHandler = errorHandler;
        this.spinner = spinner;
        this.cardValid = false;
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
            const submitEvent = (event) => {
                this.spinner.block();
                if (event) {
                    event.preventDefault();
                }
                this.errorHandler.clear();
                const state = hostedFields.getState();
                const formValid = Object.keys(state.fields).every(function (key) {
                    return state.fields[key].isValid;
                });

                if (formValid && this.cardValid) {
                    const save_card = this.defaultConfig.save_card ? true : false;
                    const vault = document.getElementById('ppcp-credit-card-vault') ?
                      document.getElementById('ppcp-credit-card-vault').checked : save_card;
                    hostedFields.submit({
                        contingencies: ['3D_SECURE'],
                        vault: vault
                    }).then((payload) => {
                        payload.orderID = payload.orderId;
                        this.spinner.unblock();
                        return contextConfig.onApprove(payload);
                    }).catch(() => {
                        this.errorHandler.genericError();
                        this.spinner.unblock();
                    });
                } else {
                    this.spinner.unblock();
                    const message = ! this.cardValid ? this.defaultConfig.hosted_fields.labels.card_not_supported : this.defaultConfig.hosted_fields.labels.fields_not_valid;
                    this.errorHandler.message(message);
                }
            }
            hostedFields.on('inputSubmitRequest', function () {
                submitEvent(null);
            });
            hostedFields.on('cardTypeChange', (event) => {
                if ( ! event.cards.length ) {
                    this.cardValid = false;
                    return;
                }
                const validCards = this.defaultConfig.hosted_fields.valid_cards;
                this.cardValid = validCards.indexOf(event.cards[0].type) !== -1;
            })
            document.querySelector(wrapper + ' button').addEventListener(
                'click',
                submitEvent
            );
        });

        document.querySelector('#payment_method_ppcp-credit-card-gateway').addEventListener(
            'click',
            () => {
                document.querySelector('label[for=ppcp-credit-card-gateway-card-number]').click();
            }
        )
    }
}
export default CreditCardRenderer;
