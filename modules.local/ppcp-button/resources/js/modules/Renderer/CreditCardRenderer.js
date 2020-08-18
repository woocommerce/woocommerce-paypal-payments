class CreditCardRenderer {

    constructor(defaultConfig, errorHandler) {
        this.defaultConfig = defaultConfig;
        this.errorHandler = errorHandler;
    }

    render(wrapper, contextConfig) {

        if (
            this.defaultConfig !== 'checkout'
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
        if (
            this.defaultConfig.enforce_vault
            && document.querySelector(wrapper + ' .ppcp-credit-card-vault')
        ) {
            document.querySelector(wrapper + ' .ppcp-credit-card-vault').checked = true;
            document.querySelector(wrapper + ' .ppcp-credit-card-vault').setAttribute('disabled', true);
        }
        paypal.HostedFields.render({
            createOrder: contextConfig.createOrder,
            fields: {
                number: {
                    selector: wrapper + ' .ppcp-credit-card',
                    placeholder: this.defaultConfig.hosted_fields.labels.credit_card_number,
                },
                cvv: {
                    selector: wrapper + ' .ppcp-cvv',
                    placeholder: this.defaultConfig.hosted_fields.labels.cvv,
                },
                expirationDate: {
                    selector: wrapper + ' .ppcp-expiration-date',
                    placeholder: this.defaultConfig.hosted_fields.labels.mm_yyyy,
                }
            }
        }).then(hostedFields => {
            const submitEvent = (event) => {
                if (event) {
                    event.preventDefault();
                }
                this.errorHandler.clear();
                const state = hostedFields.getState();
                const formValid = Object.keys(state.fields).every(function (key) {
                    return state.fields[key].isValid;
                });

                if (formValid) {

                    let vault = document.querySelector(wrapper + ' .ppcp-credit-card-vault') ?
                        document.querySelector(wrapper + ' .ppcp-credit-card-vault').checked : false;
                    vault = this.defaultConfig.enforce_vault || vault;

                    hostedFields.submit({
                        contingencies: ['3D_SECURE'],
                        vault
                    }).then((payload) => {
                        payload.orderID = payload.orderId;
                        return contextConfig.onApprove(payload);
                    });
                } else {
                    this.errorHandler.message(this.defaultConfig.hosted_fields.labels.fields_not_valid);
                }
            }
            hostedFields.on('inputSubmitRequest', function () {
                submitEvent(null);
            });
            document.querySelector(wrapper).addEventListener(
                'submit',
                submitEvent
            );
        });
    }
}
export default CreditCardRenderer;