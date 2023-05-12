import UpdateCart from "../Helper/UpdateCart";
import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";
import {hide, show, setVisible} from "../Helper/Hiding";
import ButtonsToggleListener from "../Helper/ButtonsToggleListener";

class SingleProductBootstap {
    constructor(gateway, renderer, messages, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.errorHandler = errorHandler;
        this.mutationObserver = new MutationObserver(this.handleChange.bind(this));
    }


    handleChange() {
        const shouldRender = this.shouldRender();
        setVisible(this.gateway.button.wrapper, shouldRender);
        setVisible(this.gateway.messages.wrapper, shouldRender);
        if (!shouldRender) {
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

        const buttonObserver = new ButtonsToggleListener(
            form.querySelector('.single_add_to_cart_button'),
            () => {
                show(this.gateway.button.wrapper);
                show(this.gateway.messages.wrapper);
                this.messages.renderWithAmount(this.priceAmount())
            },
            () => {
                hide(this.gateway.button.wrapper);
                hide(this.gateway.messages.wrapper);
            },
        );
        buttonObserver.init();

        if (!this.shouldRender()) {
            hide(this.gateway.button.wrapper);
            hide(this.gateway.messages.wrapper);
            return;
        }

        this.render();
    }

    shouldRender() {
        return document.querySelector('form.cart') !== null
            && !this.priceAmountIsZero()
            && !this.isSubscriptionMode();
    }

    priceAmount() {
        const priceText = [
            () => document.querySelector('form.cart ins .woocommerce-Price-amount')?.innerText,
            () => document.querySelector('form.cart .woocommerce-Price-amount')?.innerText,
            () => {
                const priceEl = document.querySelector('.product .woocommerce-Price-amount');
                // variable products show price like 10.00 - 20.00 here
                // but the second price also can be the suffix with the price incl/excl tax
                if (priceEl) {
                    const allPriceElements = Array.from(priceEl.parentElement.querySelectorAll('.woocommerce-Price-amount'))
                        .filter(el => !el.parentElement.classList.contains('woocommerce-price-suffix'));
                    if (allPriceElements.length === 1) {
                        return priceEl.innerText;
                    }
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

    isSubscriptionMode() {
        // Check "All products for subscriptions" plugin.
        return document.querySelector('.wcsatt-options-product:not(.wcsatt-options-product--hidden) .subscription-option input[type="radio"]:checked') !== null
            || document.querySelector('.wcsatt-options-prompt-label-subscription input[type="radio"]:checked') !== null; // grouped
    }

    render() {
        const actionHandler = new SingleProductActionHandler(
            this.gateway,
            new UpdateCart(
                this.gateway.ajax.change_cart.endpoint,
                this.gateway.ajax.change_cart.nonce,
            ),
            document.querySelector('form.cart'),
            this.errorHandler,
        );

        this.renderer.render(
            actionHandler.configuration()
        );
    }
}

export default SingleProductBootstap;
