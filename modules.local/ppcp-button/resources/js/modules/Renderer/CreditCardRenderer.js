class CreditCardRenderer {

    constructor(defaultConfig) {
        this.defaultConfig = defaultConfig;
    }

    render(wrapper, contextConfig) {

        if (
            wrapper === null
            || typeof paypal.HostedFields === 'undefined'
            || ! paypal.HostedFields.isEligible()
            || document.querySelector(wrapper) === null
        ) {
            return;
        }

        //ToDo: Styles
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
            document.querySelector(wrapper).addEventListener(
                'submit',
                event => {
                    event.preventDefault();
                    hostedFields.submit().then(payload => {
                        payload.orderID = payload.orderId;
                        return contextConfig.onApprove(payload);
                    });
                }
            );
        });
    }
}
export default CreditCardRenderer;