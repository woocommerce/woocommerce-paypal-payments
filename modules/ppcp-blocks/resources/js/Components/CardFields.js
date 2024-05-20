import {useEffect, useState} from '@wordpress/element';

import {
    PayPalScriptProvider,
    PayPalCardFieldsProvider,
    PayPalCardFieldsForm,
} from "@paypal/react-paypal-js";

import {CheckoutHandler} from "./checkout-handler";

export function CardFields({config, eventRegistration, emitResponse}) {
    const {onPaymentSetup} = eventRegistration;
    const {responseTypes} = emitResponse;

    const [cardFieldsForm, setCardFieldsForm] = useState();

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
            })
            .catch((err) => {
                console.error(err);
            });
    }

    const getCardFieldsForm = (cardFieldsForm) => {
        setCardFieldsForm(cardFieldsForm)
    }

    const wait = (milliseconds) => {
        return new Promise((resolve) => {
            console.log('start...')
            setTimeout(() => {
                console.log('resolve')
                resolve()
            }, milliseconds)
        })
    }

    useEffect(
        () =>
            onPaymentSetup(() => {
                async function handlePaymentProcessing() {
                    await cardFieldsForm.submit();

                    // TODO temporary workaround to wait for PayPal order in the session
                    await wait(3000)

                    return {
                        type: responseTypes.SUCCESS,
                    }
                }

                return handlePaymentProcessing();
            }),
        [onPaymentSetup, cardFieldsForm]
    );

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
                    <CheckoutHandler getCardFieldsForm={getCardFieldsForm}/>
                </PayPalCardFieldsProvider>
            </PayPalScriptProvider>
        </>
    )
}
