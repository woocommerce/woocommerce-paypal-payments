class CardFieldsRenderer {

    render(wrapper, contextConfig) {
        const cardField = paypal.CardFields({
            createOrder: function (data) {},
            onApprove: function (data) {},
            onError: function (error) {
                console.error(error)
            }
        });

        if (cardField.isEligible()) {
            const numberField = cardField.NumberField();
            numberField.render('#ppcp-credit-card-gateway-card-number');

            const cvvField = cardField.CVVField();
            cvvField.render('#ppcp-credit-card-gateway-card-cvc');

            const expiryField = cardField.ExpiryField();
            expiryField.render('#ppcp-credit-card-gateway-card-expiry');
        };
    }

    enableFields() {}
}

export default CardFieldsRenderer;
