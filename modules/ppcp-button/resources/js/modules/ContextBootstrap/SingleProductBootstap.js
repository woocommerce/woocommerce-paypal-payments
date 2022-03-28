import ErrorHandler from '../ErrorHandler';
import UpdateCart from "../Helper/UpdateCart";
import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";

class SingleProductBootstap {
    constructor(gateway, renderer, messages) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
    }


    handleChange() {
        if (!this.shouldRender()) {
            this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            this.renderer.hideButtons(this.gateway.button.wrapper);
            return;
        }

        this.render();
    }

    init() {

        document.querySelector('form.cart').addEventListener('change', this.handleChange.bind(this))

        if (!this.shouldRender()) {
            this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            return;
        }

        this.render();

    }

    shouldRender() {

        return document.querySelector('form.cart') !== null && !this.priceAmountIsZero();

    }

    priceAmountIsZero() {

        let priceText = "0";
        if (document.querySelector('form.cart ins .woocommerce-Price-amount')) {
            priceText = document.querySelector('form.cart ins .woocommerce-Price-amount').innerText;
        }
        else if (document.querySelector('form.cart .woocommerce-Price-amount')) {
            priceText = document.querySelector('form.cart .woocommerce-Price-amount').innerText;
        }
        else if (document.querySelector('.product .woocommerce-Price-amount')) {
            priceText = document.querySelector('.product .woocommerce-Price-amount').innerText;
        }
        const amount = parseFloat(priceText.replace(/([^\d,\.\s]*)/g, ''));
        return amount === 0;

    }

    render() {
        const actionHandler = new SingleProductActionHandler(
            this.gateway,
            new UpdateCart(
                this.gateway.ajax.change_cart.endpoint,
                this.gateway.ajax.change_cart.nonce,
            ),
            () => {
                this.renderer.showButtons(this.gateway.button.wrapper);
                this.renderer.showButtons(this.gateway.hosted_fields.wrapper);
                let priceText = "0";
                if (document.querySelector('form.cart ins .woocommerce-Price-amount')) {
                    priceText = document.querySelector('form.cart ins .woocommerce-Price-amount').innerText;
                }
                else if (document.querySelector('form.cart .woocommerce-Price-amount')) {
                    priceText = document.querySelector('form.cart .woocommerce-Price-amount').innerText;
                }
                const amount = parseInt(priceText.replace(/([^\d,\.\s]*)/g, ''));
                this.messages.renderWithAmount(amount)
            },
            () => {
                this.renderer.hideButtons(this.gateway.button.wrapper);
                this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            },
            document.querySelector('form.cart'),
            new ErrorHandler(this.gateway.labels.error.generic),
        );

        this.renderer.render(
            this.gateway.button.wrapper,
            this.gateway.hosted_fields.wrapper,
            actionHandler.configuration(),
        );
    }
}

export default SingleProductBootstap;
