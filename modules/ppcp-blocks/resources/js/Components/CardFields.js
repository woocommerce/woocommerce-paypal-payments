import {
    PayPalScriptProvider,
    PayPalCardFieldsProvider,
    PayPalCardFieldsForm,
} from "@paypal/react-paypal-js";

import {CheckoutHandler} from "./checkout-handler";

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

    const onApprove = async (data, actions) => {
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

    return (
        <>
            <PayPalScriptProvider
                options={{
                    clientId:
                        "abc123",
                    components: "card-fields",
                    dataNamespace: 'custom-namespace',
                }}
            >
                <PayPalCardFieldsProvider
                    createOrder={createOrder}
                    onApprove={onApprove}
                    onError={(err) => {
                        console.log(err);
                    }}
                >
                    <PayPalCardFieldsForm/>
                    <CheckoutHandler onPaymentSetup={onPaymentSetup} responseTypes={responseTypes}/>
                </PayPalCardFieldsProvider>
            </PayPalScriptProvider>
        </>
    )
}
