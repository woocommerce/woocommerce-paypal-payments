import UpdateCart from "../Helper/UpdateCart";
import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";
import {disable, enable} from "../Helper/ButtonDisabler";

class SingleProductBootstap {
    constructor(gateway, renderer, messages, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.errorHandler = errorHandler;
        this.mutationObserver = new MutationObserver(this.handleChange.bind(this));
        this.formSelector = 'form.cart';
    }

    form() {
        return document.querySelector(this.formSelector);
    }

    handleChange() {
        if (!this.shouldRender()) {
            return;
        }

        this.render();
        this.handleButtonStatus();
    }

    handleButtonStatus() {
        if (!this.shouldEnable()) {
            disable(this.gateway.button.wrapper, this.formSelector);
            disable(this.gateway.messages.wrapper);
            return;
        }
        enable(this.gateway.button.wrapper);
        enable(this.gateway.messages.wrapper);
        this.messages.renderWithAmount(this.priceAmount())
    }

    init() {
        const form = this.form();

        if (!form) {
            return;
        }

        form.addEventListener('change', this.handleChange.bind(this));
        this.mutationObserver.observe(form, { childList: true, subtree: true });

        const addToCartButton = form.querySelector('.single_add_to_cart_button');

        if (addToCartButton) {
            (new MutationObserver(this.handleButtonStatus.bind(this)))
                .observe(addToCartButton, { attributes : true });
        }

        if (!this.shouldRender()) {
            return;
        }

        this.render();
        this.handleButtonStatus();
    }

    shouldRender() {
        return this.form() !== null
            && !this.isDisabledReasonExternalPlugins();
    }

    shouldEnable() {
        const form = this.form();
        const addToCartButton = form ? form.querySelector('.single_add_to_cart_button') : null;

        return this.shouldRender()
            && !this.priceAmountIsZero()
            && ((null === addToCartButton) || !addToCartButton.classList.contains('disabled'));
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

    isDisabledReasonExternalPlugins() {
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
            this.form(),
            this.errorHandler,
        );

        if(
            PayPalCommerceGateway.data_client_id.has_subscriptions
            && PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled
        ) {
            this.renderer.render(actionHandler.subscriptionsConfiguration());
            return;
        }

        this.renderer.render(
            actionHandler.configuration()
        );
    }
}

export default SingleProductBootstap;
