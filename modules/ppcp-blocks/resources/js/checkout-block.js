import {useEffect, useState} from '@wordpress/element';
import {registerExpressPaymentMethod, registerPaymentMethod} from '@woocommerce/blocks-registry';
import {paypalAddressToWc, paypalOrderToWcAddresses} from "./Helper/Address";
import {loadPaypalScript} from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading'

const config = wc.wcSettings.getSetting('ppcp-gateway_data');

window.ppcpFundingSource = config.fundingSource;

const PayPalComponent = ({
                             onClick,
                             onClose,
                             onSubmit,
                             onError,
                             eventRegistration,
                             emitResponse,
                             activePaymentMethod,
                             shippingData,
                             isEditing,
}) => {
    const {onPaymentSetup, onCheckoutAfterProcessingWithError} = eventRegistration;
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
                    context: config.scriptData.context,
                    payment_method: 'ppcp-gateway',
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
                    funding_source: window.ppcpFundingSource ?? 'paypal',
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

            const order = await actions.order.get();

            setPaypalOrder(order);

            if (config.finalReviewEnabled) {
                const addresses = paypalOrderToWcAddresses(order);

                await wp.data.dispatch('wc/store/cart').updateCustomerData({
                    billing_address: addresses.billingAddress,
                    shipping_address: addresses.shippingAddress,
                });
                const checkoutUrl = new URL(config.scriptData.redirect);
                // sometimes some browsers may load some kind of cached version of the page,
                // so adding a parameter to avoid that
                checkoutUrl.searchParams.append('ppcp-continuation-redirect', (new Date()).getTime().toString());

                location.href = checkoutUrl.toString();
            } else {
                onSubmit();
            }
        } catch (err) {
            console.error(err);

            onError(err.message);

            onClose();

            throw err;
        }
    };

    const handleClick = (data, actions) => {
        if (isEditing) {
            return actions.reject();
        }

        window.ppcpFundingSource = data.fundingSource;

        onClick();
    };

    let handleShippingChange = null;
    if (shippingData.needsShipping && !config.finalReviewEnabled) {
        handleShippingChange = async (data, actions) => {
            try {
                const shippingOptionId = data.selected_shipping_option?.id;
                if (shippingOptionId) {
                    await shippingData.setSelectedRates(shippingOptionId);
                }

                const address = paypalAddressToWc(data.shipping_address);

                await wp.data.dispatch('wc/store/cart').updateCustomerData({
                    shipping_address: address,
                });

                await shippingData.setShippingAddress(address);

                const res = await fetch(config.ajax.update_shipping.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        nonce: config.ajax.update_shipping.nonce,
                        order_id: data.orderID,
                    })
                });

                const json = await res.json();

                if (!json.success) {
                    throw new Error(json.data.message);
                }
            } catch (e) {
                console.error(e);

                actions.reject();
            }
        };
    }

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
                            'funding_source': window.ppcpFundingSource ?? 'paypal',
                        }
                    },
                };
            }

            const addresses = paypalOrderToWcAddresses(paypalOrder);

            return {
                type: responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        'paypal_order_id': paypalOrder.id,
                        'funding_source': window.ppcpFundingSource ?? 'paypal',
                    },
                    ...addresses,
                },
            };
        });
        return () => {
            unsubscribeProcessing();
        };
    }, [onPaymentSetup, paypalOrder, activePaymentMethod]);

    useEffect(() => {
        const unsubscribe = onCheckoutAfterProcessingWithError(({ processingResponse }) => {
            if (onClose) {
                onClose();
            }
            if (processingResponse?.paymentDetails?.errorMessage) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: processingResponse.paymentDetails.errorMessage,
                    messageContext: config.scriptData.continuation ? emitResponse.noticeContexts.PAYMENTS : emitResponse.noticeContexts.EXPRESS_PAYMENTS,
                };
            }
            return true;
        });
        return unsubscribe;
    }, [onCheckoutAfterProcessingWithError, onClose]);

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
            onShippingChange={handleShippingChange}
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
    content: <PayPalComponent isEditing={false}/>,
    edit: <PayPalComponent isEditing={true}/>,
    ariaLabel: config.title,
    canMakePayment: () => config.enabled,
    supports: {
        features: features,
    },
});
