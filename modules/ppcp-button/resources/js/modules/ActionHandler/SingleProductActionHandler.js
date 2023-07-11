import Product from '../Entity/Product';
import onApprove from '../OnApproveHandler/onApproveForContinue';
import {payerData} from "../Helper/PayerData";
import {PaymentMethods} from "../Helper/CheckoutMethodState";

class SingleProductActionHandler {

    constructor(
        config,
        updateCart,
        formElement,
        errorHandler
    ) {
        this.config = config;
        this.updateCart = updateCart;
        this.formElement = formElement;
        this.errorHandler = errorHandler;
    }

    subscriptionsConfiguration() {
        return {
            createSubscription: (data, actions) => {
                return actions.subscription.create({
                    'plan_id': this.config.subscription_plan_id
                });
            },
            onApprove: (data, actions) => {
                fetch(this.config.ajax.approve_subscription.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        nonce: this.config.ajax.approve_subscription.nonce,
                        order_id: data.orderID,
                        subscription_id: data.subscriptionID
                    })
                }).then((res)=>{
                    return res.json();
                }).then(() => {
                    const id = document.querySelector('[name="add-to-cart"]').value;
                    const products =  [new Product(id, 1, null)];

                    fetch(this.config.ajax.change_cart.endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            nonce: this.config.ajax.change_cart.nonce,
                            products,
                        })
                    }).then((result) => {
                        return result.json();
                    }).then((result) => {
                        if (!result.success) {
                            console.log(result)
                            throw Error(result.data.message);
                        }

                        location.href = this.config.redirect;
                    })
                });
            },
            onError: (err) => {
                console.error(err);
            }
        }
    }

    configuration()
    {
        return {
            createOrder: this.createOrder(),
            onApprove: onApprove(this, this.errorHandler),
            onError: (error) => {
                this.errorHandler.genericError();
            }
        }
    }

    createOrder()
    {
        var getProducts = null;
        if (! this.isGroupedProduct() ) {
            getProducts = () => {
                const id = document.querySelector('[name="add-to-cart"]').value;
                const qty = document.querySelector('[name="quantity"]').value;
                const variations = this.variations();
                return [new Product(id, qty, variations)];
            }
        } else {
            getProducts = () => {
                const products = [];
                this.formElement.querySelectorAll('input[type="number"]').forEach((element) => {
                    if (! element.value) {
                        return;
                    }
                    const elementName = element.getAttribute('name').match(/quantity\[([\d]*)\]/);
                    if (elementName.length !== 2) {
                        return;
                    }
                    const id = parseInt(elementName[1]);
                    const quantity = parseInt(element.value);
                    products.push(new Product(id, quantity, null));
                })
                return products;
            }
        }
        const createOrder = (data, actions) => {
            this.errorHandler.clear();

            const onResolve = (purchase_units) => {
                const payer = payerData();
                const bnCode = typeof this.config.bn_codes[this.config.context] !== 'undefined' ?
                    this.config.bn_codes[this.config.context] : '';
                return fetch(this.config.ajax.create_order.endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        nonce: this.config.ajax.create_order.nonce,
                        purchase_units,
                        payer,
                        bn_code:bnCode,
                        payment_method: PaymentMethods.PAYPAL,
                        funding_source: window.ppcpFundingSource,
                        context:this.config.context
                    })
                }).then(function (res) {
                    return res.json();
                }).then(function (data) {
                    if (!data.success) {
                        console.error(data);
                        throw Error(data.data.message);
                    }
                    return data.data.id;
                });
            };

            const promise = this.updateCart.update(onResolve, getProducts());
            return promise;
        };
        return createOrder;
    }

    variations()
    {

        if (! this.hasVariations()) {
            return null;
        }
        const attributes = [...this.formElement.querySelectorAll("[name^='attribute_']")].map(
            (element) => {
            return {
                    value:element.value,
                    name:element.name
                }
            }
        );
        return attributes;
    }

    hasVariations()
    {
        return this.formElement.classList.contains('variations_form');
    }

    isGroupedProduct()
    {
        return this.formElement.classList.contains('grouped_form');
    }
}
export default SingleProductActionHandler;
