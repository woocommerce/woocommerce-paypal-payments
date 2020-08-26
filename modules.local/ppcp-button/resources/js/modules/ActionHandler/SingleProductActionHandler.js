import ButtonsToggleListener from '../Helper/ButtonsToggleListener';
import Product from '../Entity/Product';
import onApprove from '../OnApproveHandler/onApproveForContinue';
import {payerData} from "../Helper/PayerData";

class SingleProductActionHandler {

    constructor(
        config,
        updateCart,
        showButtonCallback,
        hideButtonCallback,
        formElement,
        errorHandler
    ) {
        this.config = config;
        this.updateCart = updateCart;
        this.showButtonCallback = showButtonCallback;
        this.hideButtonCallback = hideButtonCallback;
        this.formElement = formElement;
        this.errorHandler = errorHandler;
    }

    configuration()
    {

        if ( this.hasVariations() ) {
            const observer = new ButtonsToggleListener(
                this.formElement.querySelector('.single_add_to_cart_button'),
                this.showButtonCallback,
                this.hideButtonCallback
            );
            observer.init();
        }

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
                    body: JSON.stringify({
                        nonce: this.config.ajax.create_order.nonce,
                        purchase_units,
                        payer,
                        bn_code:bnCode,
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
