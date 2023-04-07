import {useEffect, useState} from '@wordpress/element';
import {registerExpressPaymentMethod, registerPaymentMethod} from '@woocommerce/blocks-registry';
import {paypalOrderToWcShippingAddress, paypalPayerToWc} from "./Helper/Address";
import {loadPaypalScript} from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading'

const config = wc.wcSettings.getSetting('ppcp-gateway_data');

const PayPalComponent = ({
                             onClick,
                             onClose,
                             onSubmit,
                             onError,
                             eventRegistration,
                             emitResponse,
                             activePaymentMethod,
}) => {
    const {onPaymentSetup} = eventRegistration;
    const {responseTypes} = emitResponse;

    const [paypalOrder, setPaypalOrder] = useState(null);

    const [loaded, setLoaded] = useState(false);
    useEffect(() => {
        if (!loaded) {
            loadPaypalScript(config.scriptData, () => {
                setLoaded(true);
            });
        }
    }, [loaded]);

    const createOrder = async () => {
        try {
            const res = await fetch(config.scriptData.ajax.create_order.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: config.scriptData.ajax.create_order.nonce,
                    bn_code: '',
                    context: 'express',
                    order_id: config.scriptData.order_id,
                    payment_method: 'ppcp-gateway',
                    funding_source: 'paypal',
                    createaccount: false
                }),
            });

            const json = await res.json();

            if (!json.success) {
                if (json.data?.details?.length > 0) {
                    throw new Error(json.data.details.map(d => `${d.issue} ${d.description}`).join('<br/>'));
                } else if (json.data?.message) {
                    throw new Error(json.data.message);
                }

                throw new Error(config.scriptData.labels.error.generic);
            }
            return json.data.id;
        } catch (err) {
            console.error(err);

            onError(err.message);

            onClose();

            throw err;
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
                    //funding_source: ,
                })
            });

            const json = await res.json();

            if (!json.success) {
                if (typeof actions !== 'undefined' && typeof actions.restart !== 'undefined') {
                    return actions.restart();
                }
                if (json.data?.message) {
                    throw new Error(json.data.message);
                }

                throw new Error(config.scriptData.labels.error.generic)
            }

            setPaypalOrder(json.data);

            onSubmit();
        } catch (err) {
            console.error(err);

            onError(err.message);

            onClose();

            throw err;
        }
    };

    const handleClick = () => {
        onClick();
    };

    useEffect(() => {
        if (activePaymentMethod !== config.id) {
            return;
        }

        const unsubscribeProcessing = onPaymentSetup(() => {
            if (config.scriptData.continuation) {
                return {
                    type: responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            'paypal_order_id': config.scriptData.continuation.order_id,
                        },
                    },
                };
            }

            const shippingAddress = paypalOrderToWcShippingAddress(paypalOrder);
            let billingAddress = paypalPayerToWc(paypalOrder.payer);
            // no billing address, such as if billing address retrieval is not allowed in the merchant account
            if (!billingAddress.address_line_1) {
                billingAddress = {...shippingAddress, ...paypalPayerToWc(paypalOrder.payer)};
            }

            return {
                type: responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        'paypal_order_id': paypalOrder.id,
                    },
                    shippingAddress,
                    billingAddress,
                },
            };
        });
        return () => {
            unsubscribeProcessing();
        };
    }, [onPaymentSetup, paypalOrder, activePaymentMethod]);

    if (config.scriptData.continuation) {
        return (
            <div dangerouslySetInnerHTML={{__html: config.scriptData.continuation.cancel.html}}>

            </div>
        )
    }

    if (!loaded) {
        return null;
    }

    const PayPalButton = window.paypal.Buttons.driver("react", { React, ReactDOM });

    return (
        <PayPalButton
            style={config.scriptData.button.style}
            onClick={handleClick}
            onCancel={onClose}
            onError={onClose}
            createOrder={createOrder}
            onApprove={handleApprove}
        />
    );
}

const features = ['products'];
let registerMethod = registerExpressPaymentMethod;
if (config.scriptData.continuation) {
    features.push('ppcp_continuation');
    registerMethod = registerPaymentMethod;
}

registerMethod({
    name: config.id,
    label: <div dangerouslySetInnerHTML={{__html: config.title}}/>,
    content: <PayPalComponent/>,
    edit: <b>TODO: editing</b>,
    ariaLabel: config.title,
    canMakePayment: () => config.enabled,
    supports: {
        features: features,
    },
});
