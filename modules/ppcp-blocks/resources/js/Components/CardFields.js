import {useEffect} from '@wordpress/element';

export function CardFields({config, eventRegistration, emitResponse}) {
    const {onPaymentSetup, onCheckoutFail, onCheckoutValidation} = eventRegistration;
    const {responseTypes} = emitResponse;

    const createOrder = async () => {
        try {
            const res = await fetch(config.scriptData.ajax.create_order.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: config.scriptData.ajax.create_order.nonce,
                    context: config.scriptData.context,
                    payment_method: 'ppcp-credit-card-gateway',
                    createaccount: false
                }),
            });

            const json = await res.json();
            if (!json.success) {
                console.error(json)
            }

            console.log(json.data.id)

            return json.data.id;
        } catch (err) {
            console.error(err);
        }
    };

    const handleApprove = async (data, actions) => {
        try {
            const res = await fetch(config.scriptData.ajax.approve_order.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: config.scriptData.ajax.approve_order.nonce,
                    order_id: data.orderID,
                })
            });

            const json = await res.json();
            if (!json.success) {
                console.error(json)
            }

            console.log(json)
        } catch (err) {
            console.error(err);
        }
    };

    const cardField = paypal.CardFields({
        createOrder: () => {
            return createOrder();
        },
        onApprove: (data, actions) => {
            return handleApprove(data, actions);
        },
        onError: function (error) {
            console.error(error)
        }
    });

    useEffect(() => {
        const unsubscribe = onPaymentSetup(() => {

            cardField.submit()
                .catch((error) => {
                    console.error(error)
                    return {type: responseTypes.ERROR};
                });

            return true;
        });
        return unsubscribe;
    }, [onPaymentSetup]);

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
