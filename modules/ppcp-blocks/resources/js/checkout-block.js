import {useEffect, useState} from '@wordpress/element';
import {registerExpressPaymentMethod, registerPaymentMethod} from '@woocommerce/blocks-registry';
import {
    mergeWcAddress,
    paypalAddressToWc,
    paypalOrderToWcAddresses,
    paypalSubscriptionToWcAddresses
} from "./Helper/Address";
import {
    cartHasSubscriptionProducts,
    isPayPalSubscription
} from "./Helper/Subscription";
import {
    loadPaypalScriptPromise
} from '../../../ppcp-button/resources/js/modules/Helper/ScriptLoading'
import {
    normalizeStyleForFundingSource
} from '../../../ppcp-button/resources/js/modules/Helper/Style'
import buttonModuleWatcher from "../../../ppcp-button/resources/js/modules/ButtonModuleWatcher";
import BlockCheckoutMessagesBootstrap from "./Bootstrap/BlockCheckoutMessagesBootstrap";
const config = wc.wcSettings.getSetting('ppcp-gateway_data');

window.ppcpFundingSource = config.fundingSource;

let registeredContext = false;

let paypalScriptPromise = null;

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
                             fundingSource,
}) => {
    const {onPaymentSetup, onCheckoutFail, onCheckoutValidation} = eventRegistration;
    const {responseTypes} = emitResponse;

    const [paypalOrder, setPaypalOrder] = useState(null);
    const [gotoContinuationOnError, setGotoContinuationOnError] = useState(false);

    const [paypalScriptLoaded, setPaypalScriptLoaded] = useState(false);

    if (!paypalScriptLoaded) {
        if (!paypalScriptPromise) {
            // for editor, since canMakePayment was not called
            paypalScriptPromise = loadPaypalScriptPromise(config.scriptData)
        }
        paypalScriptPromise.then(() => setPaypalScriptLoaded(true));
    }

    const methodId = fundingSource ? `${config.id}-${fundingSource}` : config.id;

    useEffect(() => {
        // fill the form if in continuation (for product or mini-cart buttons)
        if (!config.scriptData.continuation || !config.scriptData.continuation.order || window.ppcpContinuationFilled) {
            return;
        }
        try {
            const paypalAddresses = paypalOrderToWcAddresses(config.scriptData.continuation.order);
            const wcAddresses = wp.data.select('wc/store/cart').getCustomerData();
            const addresses = mergeWcAddress(wcAddresses, paypalAddresses);
            wp.data.dispatch('wc/store/cart').setBillingAddress(addresses.billingAddress);
            if (shippingData.needsShipping) {
                wp.data.dispatch('wc/store/cart').setShippingAddress(addresses.shippingAddress);
            }
        } catch (err) {
            // sometimes the PayPal address is missing, skip in this case.
            console.log(err);
        }
        // this useEffect should run only once, but adding this in case of some kind of full re-rendering
        window.ppcpContinuationFilled = true;
    }, [])

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

    const createSubscription = async (data, actions) => {
        return actions.subscription.create({
            'plan_id': config.scriptData.subscription_plan_id
        });
    };

    const handleApproveSubscription = async (data, actions) => {
        try {
            const subscription = await actions.subscription.get();

            if (subscription) {
                const addresses = paypalSubscriptionToWcAddresses(subscription);

                let promises = [
                    // save address on server
                    wp.data.dispatch('wc/store/cart').updateCustomerData({
                        billing_address: addresses.billingAddress,
                        shipping_address: addresses.shippingAddress,
                    }),
                ];
                if (!config.finalReviewEnabled) {
                    // set address in UI
                    promises.push(wp.data.dispatch('wc/store/cart').setBillingAddress(addresses.billingAddress));
                    if (shippingData.needsShipping) {
                        promises.push(wp.data.dispatch('wc/store/cart').setShippingAddress(addresses.shippingAddress))
                    }
                }
                await Promise.all(promises);
            }

            setPaypalOrder(subscription);

            const res = await fetch(config.scriptData.ajax.approve_subscription.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: config.scriptData.ajax.approve_subscription.nonce,
                    order_id: data.orderID,
                    subscription_id: data.subscriptionID
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

            if (config.finalReviewEnabled) {
                location.href = getCheckoutRedirectUrl();
            } else {
                setGotoContinuationOnError(true);
                onSubmit();
            }
        } catch (err) {
            console.error(err);

            onError(err.message);

            onClose();

            throw err;
        }
    };

    const getCheckoutRedirectUrl = () => {
        const checkoutUrl = new URL(config.scriptData.redirect);
        // sometimes some browsers may load some kind of cached version of the page,
        // so adding a parameter to avoid that
        checkoutUrl.searchParams.append('ppcp-continuation-redirect', (new Date()).getTime().toString());
        return checkoutUrl.toString();
    }

    const handleApprove = async (data, actions) => {
        try {
            const order = await actions.order.get();

            if (order) {
                const addresses = paypalOrderToWcAddresses(order);

                let promises = [
                    // save address on server
                    wp.data.dispatch('wc/store/cart').updateCustomerData({
                        billing_address: addresses.billingAddress,
                        shipping_address: addresses.shippingAddress,
                    }),
                ];
                if (!config.finalReviewEnabled) {
                    // set address in UI
                    promises.push(wp.data.dispatch('wc/store/cart').setBillingAddress(addresses.billingAddress));
                    if (shippingData.needsShipping) {
                        promises.push(wp.data.dispatch('wc/store/cart').setShippingAddress(addresses.shippingAddress))
                    }
                }
                await Promise.all(promises);
            }

            setPaypalOrder(order);

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

            if (config.finalReviewEnabled) {
                location.href = getCheckoutRedirectUrl();
            } else {
                setGotoContinuationOnError(true);
                onSubmit();
            }
        } catch (err) {
            console.error(err);

            onError(err.message);

            onClose();

            throw err;
        }
    };

    useEffect(() => {
        const unsubscribe = onCheckoutValidation(() => {
            if (config.scriptData.continuation) {
                return true;
            }
            if (gotoContinuationOnError && wp.data.select('wc/store/validation').hasValidationErrors()) {
                location.href = getCheckoutRedirectUrl();
                return { type: responseTypes.ERROR };
            }

            return true;
        });
        return unsubscribe;
    }, [onCheckoutValidation, gotoContinuationOnError] );

    const handleClick = (data, actions) => {
        if (isEditing) {
            return actions.reject();
        }

        window.ppcpFundingSource = data.fundingSource;

        onClick();
    };

    let handleShippingChange = null;
    let handleSubscriptionShippingChange = null;
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

        handleSubscriptionShippingChange = async (data, actions) => {
            console.log('--- handleSubscriptionShippingChange', data, actions);

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

            } catch (e) {
                console.error(e);

                actions.reject();
            }
        };
    }

    useEffect(() => {
        if (activePaymentMethod !== methodId) {
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
        if (activePaymentMethod !== methodId) {
            return;
        }
        const unsubscribe = onCheckoutFail(({ processingResponse }) => {
            console.error(processingResponse)
            if (onClose) {
                onClose();
            }
            if (config.scriptData.continuation) {
                return true;
            }
            if (!config.finalReviewEnabled) {
                location.href = getCheckoutRedirectUrl();
            }
            return true;
        });
        return unsubscribe;
    }, [onCheckoutFail, onClose, activePaymentMethod]);

    if (config.scriptData.continuation) {
        return (
            <div dangerouslySetInnerHTML={{__html: config.scriptData.continuation.cancel.html}}>

            </div>
        )
    }

    if (!registeredContext) {
        buttonModuleWatcher.registerContextBootstrap(config.scriptData.context, {
            createOrder: () => {
                return createOrder();
            },
            onApprove: (data, actions) => {
                return handleApprove(data, actions);
            },
        });
        registeredContext = true;
    }

    const style = normalizeStyleForFundingSource(config.scriptData.button.style, fundingSource);

    if (!paypalScriptLoaded) {
        return null;
    }

    const PayPalButton = paypal.Buttons.driver("react", { React, ReactDOM });

    if(isPayPalSubscription(config.scriptData)) {
        return (
            <PayPalButton
                fundingSource={fundingSource}
                style={style}
                onClick={handleClick}
                onCancel={onClose}
                onError={onClose}
                createSubscription={createSubscription}
                onApprove={handleApproveSubscription}
                onShippingChange={handleSubscriptionShippingChange}
            />
        );
    }

    return (
        <PayPalButton
            fundingSource={fundingSource}
            style={style}
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
let block_enabled = true;

if(cartHasSubscriptionProducts(config.scriptData)) {
    // Don't show buttons on block cart page if using vault v2 and user is not logged in
    if (
        ! config.scriptData.user.is_logged
        && config.scriptData.context === "cart-block"
        && ! isPayPalSubscription(config.scriptData) // using vaulting
        && ! config.scriptData?.save_payment_methods?.id_token // not vault v3
    ) {
        block_enabled = false;
    }

    // Don't render buttons if in subscription mode and product not associated with a PayPal subscription
    if(
        isPayPalSubscription(config.scriptData)
        && !config.scriptData.subscription_product_allowed
    ) {
        block_enabled = false;
    }

    features.push('subscriptions');
}

if (block_enabled) {
    if ((config.addPlaceOrderMethod || config.usePlaceOrder) && !config.scriptData.continuation) {
        let descriptionElement = <div dangerouslySetInnerHTML={{__html: config.description}}></div>;
        if (config.placeOrderButtonDescription) {
            descriptionElement = <div>
                <p dangerouslySetInnerHTML={{__html: config.description}}></p>
                <p style={{textAlign: 'center'}} className={'ppcp-place-order-description'} dangerouslySetInnerHTML={{__html: config.placeOrderButtonDescription}}></p>
            </div>;
        }

        registerPaymentMethod({
            name: config.id,
            label: <div dangerouslySetInnerHTML={{__html: config.title}}/>,
            content: descriptionElement,
            edit: descriptionElement,
            placeOrderButtonLabel: config.placeOrderButtonText,
            ariaLabel: config.title,
            canMakePayment: () => {
                return config.enabled;
            },
            supports: {
                features: features,
            },
        });
    }

    if (config.scriptData.continuation) {
        registerPaymentMethod({
            name: config.id,
            label: <div dangerouslySetInnerHTML={{__html: config.title}}/>,
            content: <PayPalComponent isEditing={false}/>,
            edit: <PayPalComponent isEditing={true}/>,
            ariaLabel: config.title,
            canMakePayment: () => {
                return true;
            },
            supports: {
                features: [...features, 'ppcp_continuation'],
            },
        });
    } else if (!config.usePlaceOrder) {
        for (const fundingSource of ['paypal', ...config.enabledFundingSources]) {
            registerExpressPaymentMethod({
                name: `${config.id}-${fundingSource}`,
                paymentMethodId: config.id,
                label: <div dangerouslySetInnerHTML={{__html: config.title}}/>,
                content: <PayPalComponent isEditing={false} fundingSource={fundingSource}/>,
                edit: <PayPalComponent isEditing={true} fundingSource={fundingSource}/>,
                ariaLabel: config.title,
                canMakePayment: async () => {
                    if (!paypalScriptPromise) {
                        paypalScriptPromise = loadPaypalScriptPromise(config.scriptData);
                        paypalScriptPromise.then(() => {
                            const messagesBootstrap = new BlockCheckoutMessagesBootstrap(config.scriptData);
                            messagesBootstrap.init();
                        });
                    }
                    await paypalScriptPromise;

                    return paypal.Buttons({fundingSource}).isEligible();
                },
                supports: {
                    features: features,
                },
            });
        }
    }
}
