import {
    PayPalScriptProvider,
    PayPalCardFieldsProvider,
    PayPalCardFieldsForm,
} from "@paypal/react-paypal-js";

import {CheckoutHandler} from "./checkout-handler";

export function CardFields({config, eventRegistration, emitResponse}) {
    const {onPaymentSetup, onCheckoutFail, onCheckoutValidation} = eventRegistration;
    const {responseTypes} = emitResponse;

    async function createOrder() {
        return fetch(config.scriptData.ajax.create_order.endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                nonce: config.scriptData.ajax.create_order.nonce,
                context: config.scriptData.context,
                payment_method: 'ppcp-credit-card-gateway',
                createaccount: false
            }),
        })
            .then((response) => response.json())
            .then((order) => {
                return order.data.id;
            })
            .catch((err) => {
                console.error(err);
            });
    }

    function onApprove(data) {
        fetch(config.scriptData.ajax.approve_order.endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                order_id: data.orderID,
                nonce: config.scriptData.ajax.approve_order.nonce,
            }),
        })
            .then((response) => response.json())
            .then((data) => {
                console.log(data)
                return {
                    type: responseTypes.SUCCESS,
                };
            })
            .catch((err) => {
                console.error(err);
            });
    }

    return (
        <>
            <PayPalScriptProvider
                options={{
                    clientId: config.scriptData.client_id,
                    components: "card-fields",
                    dataNamespace: 'ppcp-block-card-fields',
                }}
            >
                <PayPalCardFieldsProvider
                    createOrder={createOrder}
                    onApprove={onApprove}
                    onError={(err) => {
                        console.error(err);
                    }}
                >
                    <PayPalCardFieldsForm/>
                    <CheckoutHandler onPaymentSetup={onPaymentSetup} responseTypes={responseTypes}/>
                </PayPalCardFieldsProvider>
            </PayPalScriptProvider>
        </>
    )
}
