import UpdateCart from "../Helper/UpdateCart";
import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";

class SingleProductBootstap {
    constructor(gateway, renderer, messages, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.errorHandler = errorHandler;
    }


    handleChange() {
        if (!this.shouldRender()) {
            this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            this.renderer.hideButtons(this.gateway.button.wrapper);
            this.messages.hideMessages();
            return;
        }

        this.render();
    }

    init() {

        document.querySelector('form.cart').addEventListener('change', this.handleChange.bind(this))

        if (!this.shouldRender()) {
            this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
            this.messages.hideMessages();
            return;
        }

        this.render();

    }

    shouldRender() {

        return document.querySelector('form.cart') !== null && !this.priceAmountIsZero();

    }

    priceAmount() {

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

        priceText = priceText.replace(/,/g, '.');

        return  parseFloat(priceText.replace(/([^\d,\.\s]*)/g, ''));
    }

    priceAmountIsZero() {
        return this.priceAmount() === 0;
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
                this.messages.renderWithAmount(this.priceAmount())
            },
            () => {
                this.renderer.hideButtons(this.gateway.button.wrapper);
                this.renderer.hideButtons(this.gateway.hosted_fields.wrapper);
                this.messages.hideMessages();
            },
            document.querySelector('form.cart'),
            this.errorHandler,
        );

        this.renderer.render(
            actionHandler.configuration()
        );
    }
}

export default SingleProductBootstap;
