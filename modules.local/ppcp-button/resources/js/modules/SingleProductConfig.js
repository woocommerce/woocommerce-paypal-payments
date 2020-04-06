import ButtonsToggleListener from "./ButtonsToggleListener";

class SingleProductConfig {

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
        const onApprove = (data, actions) => {
            return actions.redirect(this.config.redirect);
        }
        return {
            createOrder: this.createOrder(),
            onApprove,
            onError: (error) => {
                this.errorHandler.message(error);
            }
        }
    }

    createOrder()
    {
        const createOrder = (data, actions) => {
            this.errorHandler.clear();
            const product = document.querySelector('[name="add-to-cart"]').value;
            const qty = document.querySelector('[name="quantity"]').value;
            const variations = this.variations();

            const onResolve = (purchase_units) => {
                return fetch(this.config.ajax.create_order.endpoint, {
                    method: 'POST',
                    body: JSON.stringify({
                        nonce:this.config.ajax.create_order.nonce,
                        purchase_units
                    })
                }).then(function (res) {
                    return res.json();
                }).then(function (data) {
                    if (! data.success) {
                        //Todo: Error handling
                        return;
                    }
                    return data.data.id;
                });
            };

            const promise = this.updateCart.update(onResolve, product, qty, variations);
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
}

export default SingleProductConfig;