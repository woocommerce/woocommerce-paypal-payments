import UpdateCart from "../Helper/UpdateCart";
import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";

class SingleProductBootstap {
    constructor(gateway, renderer, messages, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.errorHandler = errorHandler;
        this.mutationObserver = new MutationObserver(this.handleChange.bind(this));
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

        const form = document.querySelector('form.cart');
        if (!form) {
            return;
        }

        form.addEventListener('change', this.handleChange.bind(this));
        this.mutationObserver.observe(form, {childList: true, subtree: true});

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
        const priceText = [
            () => document.querySelector('form.cart ins .woocommerce-Price-amount')?.innerText,
            () => document.querySelector('form.cart .woocommerce-Price-amount')?.innerText,
            () => {
                const priceEl = document.querySelector('.product .woocommerce-Price-amount');
                // variable products show price like 10.00 - 20.00 here
                if (priceEl && priceEl.parentElement.querySelectorAll('.woocommerce-Price-amount').length === 1) {
                    return priceEl.innerText;
                }
                return null;
            },
        ].map(f => f()).find(val => val);

        if (!priceText) {
            return 0;
        }

        return parseFloat(priceText.replace(/,/g, '.').replace(/([^\d,\.\s]*)/g, ''));
    }

    priceAmountIsZero() {
        const price = this.priceAmount();
        return !price || price === 0;
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
