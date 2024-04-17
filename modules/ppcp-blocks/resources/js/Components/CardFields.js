import {useEffect} from '@wordpress/element';

export function CardFields({config}) {
    const cardField = paypal.CardFields({
        createOrder: () => {},
        onApprove: () => {},
        onError: function (error) {
            console.error(error)
        }
    });

    useEffect(() => {
        if (cardField.isEligible()) {
            const numberField = cardField.NumberField();
            numberField.render("#card-number-field-container");

            const cvvField = cardField.CVVField();
            cvvField.render("#card-cvv-field-container");

            const expiryField = cardField.ExpiryField();
            expiryField.render("#card-expiry-field-container");
        }
    }, []);

    return (
        <>
            <div id="card-number-field-container"></div>
            <div id="card-expiry-field-container"></div>
            <div id="card-cvv-field-container"></div>
        </>
    )
}
