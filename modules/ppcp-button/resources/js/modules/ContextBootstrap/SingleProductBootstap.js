import UpdateCart from "../Helper/UpdateCart";
import SingleProductActionHandler from "../ActionHandler/SingleProductActionHandler";
import {hide, show} from "../Helper/Hiding";
import BootstrapHelper from "../Helper/BootstrapHelper";
import {loadPaypalJsScript} from "../Helper/ScriptLoading";
import {getPlanIdFromVariation} from "../Helper/Subscriptions"
import SimulateCart from "../Helper/SimulateCart";
import {strRemoveWord, strAddWord, throttle} from "../Helper/Utils";

class SingleProductBootstap {
    constructor(gateway, renderer, messages, errorHandler) {
        this.gateway = gateway;
        this.renderer = renderer;
        this.messages = messages;
        this.errorHandler = errorHandler;
        this.mutationObserver = new MutationObserver(this.handleChange.bind(this));
        this.formSelector = 'form.cart';

        // Prevent simulate cart being called too many times in a burst.
        this.simulateCartThrottled = throttle(this.simulateCart, 5000);

        this.renderer.onButtonsInit(this.gateway.button.wrapper, () => {
            this.handleChange();
        }, true);

        this.subscriptionButtonsLoaded = false
    }

    form() {
        return document.querySelector(this.formSelector);
    }

    handleChange() {
        this.subscriptionButtonsLoaded = false

        if (!this.shouldRender()) {
            this.renderer.disableSmartButtons(this.gateway.button.wrapper);
            hide(this.gateway.button.wrapper, this.formSelector);
            hide(this.gateway.messages.wrapper);
            return;
        }

        this.render();

        this.renderer.enableSmartButtons(this.gateway.button.wrapper);
        show(this.gateway.button.wrapper);
        show(this.gateway.messages.wrapper);

        this.handleButtonStatus();
    }

    handleButtonStatus(simulateCart = true) {
        BootstrapHelper.handleButtonStatus(this, {
            formSelector: this.formSelector
        });

        if (simulateCart) {
            this.simulateCartThrottled();
        }
    }

    init() {
        const form = this.form();

        if (!form) {
            return;
        }

        jQuery(document).on('change', this.formSelector, () => {
            this.handleChange();
        });
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
        this.handleChange();
    }

    shouldRender() {
        return this.form() !== null
            && !this.isWcsattSubscriptionMode();
    }

    shouldEnable() {
        const form = this.form();
        const addToCartButton = form ? form.querySelector('.single_add_to_cart_button') : null;

        return BootstrapHelper.shouldEnable(this)
            && !this.priceAmountIsZero()
            && ((null === addToCartButton) || !addToCartButton.classList.contains('disabled'));
    }

    priceAmount(returnOnUndefined = 0) {
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

        if (typeof priceText === 'undefined') {
            return returnOnUndefined;
        }

        if (!priceText) {
            return 0;
        }

        return parseFloat(priceText.replace(/,/g, '.').replace(/([^\d,\.\s]*)/g, ''));
    }

    priceAmountIsZero() {
        const price = this.priceAmount(-1);

        // if we can't find the price in the DOM we want to return true so the button is visible.
        if (price === -1) {
            return false;
        }

        return !price || price === 0;
    }

    isWcsattSubscriptionMode() {
        // Check "All products for subscriptions" plugin.
        return document.querySelector('.wcsatt-options-product:not(.wcsatt-options-product--hidden) .subscription-option input[type="radio"]:checked') !== null
            || document.querySelector('.wcsatt-options-prompt-label-subscription input[type="radio"]:checked') !== null; // grouped
    }

    variations() {
        if (!this.hasVariations()) {
            return null;
        }

        return [...document.querySelector('form.cart')?.querySelectorAll("[name^='attribute_']")].map(
            (element) => {
                return {
                    value: element.value,
                    name: element.name
                }
            }
        );
    }

    hasVariations() {
        return document.querySelector('form.cart')?.classList.contains('variations_form');
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
            const buttonWrapper = document.getElementById('ppc-button-ppcp-gateway');
            buttonWrapper.innerHTML = '';

            const subscription_plan = this.variations() !== null
                ? getPlanIdFromVariation(this.variations())
                : PayPalCommerceGateway.subscription_plan_id
            if(!subscription_plan) {
                return;
            }

            if(this.subscriptionButtonsLoaded) return
            loadPaypalJsScript(
                {
                    clientId: PayPalCommerceGateway.client_id,
                    currency: PayPalCommerceGateway.currency,
                    intent: 'subscription',
                    vault: true
                },
                actionHandler.subscriptionsConfiguration(subscription_plan),
                this.gateway.button.wrapper
            );

            this.subscriptionButtonsLoaded = true
            return;
        }

        this.renderer.render(
            actionHandler.configuration()
        );
    }

    simulateCart() {
        const actionHandler = new SingleProductActionHandler(
            null,
            null,
            this.form(),
            this.errorHandler,
        );

        const hasSubscriptions = PayPalCommerceGateway.data_client_id.has_subscriptions
            && PayPalCommerceGateway.data_client_id.paypal_subscriptions_enabled;

        const products = hasSubscriptions
            ? actionHandler.getSubscriptionProducts()
            : actionHandler.getProducts();

        (new SimulateCart(
            this.gateway.ajax.simulate_cart.endpoint,
            this.gateway.ajax.simulate_cart.nonce,
        )).simulate((data) => {

            this.messages.renderWithAmount(data.total);

            if ( this.gateway.single_product_buttons_enabled !== '1' ) {
                return;
            }

            let enableFunding = this.gateway.url_params['enable-funding'];
            let disableFunding = this.gateway.url_params['disable-funding'];

            for (const [fundingSource, funding] of Object.entries(data.funding)) {
                if (funding.enabled === true) {
                    enableFunding = strAddWord(enableFunding, fundingSource);
                    disableFunding = strRemoveWord(disableFunding, fundingSource);
                } else if (funding.enabled === false) {
                    enableFunding = strRemoveWord(enableFunding, fundingSource);
                    disableFunding = strAddWord(disableFunding, fundingSource);
                }
            }

            if (
                (enableFunding !== this.gateway.url_params['enable-funding']) ||
                (disableFunding !== this.gateway.url_params['disable-funding'])
            ) {
                this.gateway.url_params['enable-funding'] = enableFunding;
                this.gateway.url_params['disable-funding'] = disableFunding;
                jQuery(this.gateway.button.wrapper).trigger('ppcp-reload-buttons');
            }

            if (typeof data.button.is_disabled === 'boolean') {
                this.gateway.button.is_disabled = data.button.is_disabled;
            }
            if (typeof data.messages.is_hidden === 'boolean') {
                this.gateway.messages.is_hidden = data.messages.is_hidden;
            }

            this.handleButtonStatus(false);

        }, products);
    }
}

export default SingleProductBootstap;
