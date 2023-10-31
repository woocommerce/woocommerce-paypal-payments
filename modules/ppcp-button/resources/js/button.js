import MiniCartBootstap from './modules/ContextBootstrap/MiniCartBootstap';
import SingleProductBootstap from './modules/ContextBootstrap/SingleProductBootstap';
import CartBootstrap from './modules/ContextBootstrap/CartBootstap';
import CheckoutBootstap from './modules/ContextBootstrap/CheckoutBootstap';
import PayNowBootstrap from "./modules/ContextBootstrap/PayNowBootstrap";
import Renderer from './modules/Renderer/Renderer';
import ErrorHandler from './modules/ErrorHandler';
import CreditCardRenderer from "./modules/Renderer/CreditCardRenderer";
import MessageRenderer from "./modules/Renderer/MessageRenderer";
import Spinner from "./modules/Helper/Spinner";
import {
    getCurrentPaymentMethod,
    ORDER_BUTTON_SELECTOR,
    PaymentMethods
} from "./modules/Helper/CheckoutMethodState";
import {setVisibleByClass} from "./modules/Helper/Hiding";
import {isChangePaymentPage} from "./modules/Helper/Subscriptions";
import FreeTrialHandler from "./modules/ActionHandler/FreeTrialHandler";
import FormSaver from './modules/Helper/FormSaver';
import FormValidator from "./modules/Helper/FormValidator";
import {loadPaypalScript} from "./modules/Helper/ScriptLoading";
import buttonModuleWatcher from "./modules/ButtonModuleWatcher";
import MessagesBootstrap from "./modules/ContextBootstrap/MessagesBootstap";

// TODO: could be a good idea to have a separate spinner for each gateway,
// but I think we care mainly about the script loading, so one spinner should be enough.
const buttonsSpinner = new Spinner(document.querySelector('.ppc-button-wrapper'));
const cardsSpinner = new Spinner('#ppcp-hosted-fields');

const bootstrap = () => {
    const checkoutFormSelector = 'form.woocommerce-checkout';

    const context = PayPalCommerceGateway.context;

    const errorHandler = new ErrorHandler(
        PayPalCommerceGateway.labels.error.generic,
        document.querySelector(checkoutFormSelector) ?? document.querySelector('.woocommerce-notices-wrapper')
    );
    const spinner = new Spinner();
    const creditCardRenderer = new CreditCardRenderer(PayPalCommerceGateway, errorHandler, spinner);

    const formSaver = new FormSaver(
        PayPalCommerceGateway.ajax.save_checkout_form.endpoint,
        PayPalCommerceGateway.ajax.save_checkout_form.nonce,
    );

    const formValidator = PayPalCommerceGateway.early_checkout_validation_enabled ?
        new FormValidator(
            PayPalCommerceGateway.ajax.validate_checkout.endpoint,
            PayPalCommerceGateway.ajax.validate_checkout.nonce,
    ) : null;

    const freeTrialHandler = new FreeTrialHandler(PayPalCommerceGateway, checkoutFormSelector, formSaver, formValidator, spinner, errorHandler);

    jQuery('form.woocommerce-checkout input').on('keydown', e => {
        if (e.key === 'Enter' && [
            PaymentMethods.PAYPAL,
            PaymentMethods.CARDS,
            PaymentMethods.CARD_BUTTON,
        ].includes(getCurrentPaymentMethod())) {
            e.preventDefault();
        }
    });

    const hasMessages = () => {
        return PayPalCommerceGateway.messages.is_hidden === false
            && document.querySelector(PayPalCommerceGateway.messages.wrapper);
    }

    const onSmartButtonClick = async (data, actions) => {
        window.ppcpFundingSource = data.fundingSource;
        const requiredFields = jQuery('form.woocommerce-checkout .validate-required:visible :input');
        requiredFields.each((i, input) => {
            jQuery(input).trigger('validate');
        });

        if (PayPalCommerceGateway.basic_checkout_validation_enabled) {
            // A quick fix to get the errors about empty form fields before attempting PayPal order,
            // it should solve #513 for most of the users, but it is not a proper solution.
            // Currently it is disabled by default because a better solution is now implemented
            // (see woocommerce_paypal_payments_basic_checkout_validation_enabled,
            // woocommerce_paypal_payments_early_wc_checkout_validation_enabled filters).
            const invalidFields = Array.from(jQuery('form.woocommerce-checkout .validate-required.woocommerce-invalid:visible'));
            if (invalidFields.length) {
                const billingFieldsContainer = document.querySelector('.woocommerce-billing-fields');
                const shippingFieldsContainer = document.querySelector('.woocommerce-shipping-fields');

                const nameMessageMap = PayPalCommerceGateway.labels.error.required.elements;
                const messages = invalidFields.map(el => {
                    const name = el.querySelector('[name]')?.getAttribute('name');
                    if (name && name in nameMessageMap) {
                        return nameMessageMap[name];
                    }
                    let label = el.querySelector('label').textContent
                        .replaceAll('*', '')
                        .trim();
                    if (billingFieldsContainer?.contains(el)) {
                        label = PayPalCommerceGateway.labels.billing_field.replace('%s', label);
                    }
                    if (shippingFieldsContainer?.contains(el)) {
                        label = PayPalCommerceGateway.labels.shipping_field.replace('%s', label);
                    }
                    return PayPalCommerceGateway.labels.error.required.field
                        .replace('%s', `<strong>${label}</strong>`)
                }).filter(s => s.length > 2);

                errorHandler.clear();
                if (messages.length) {
                    errorHandler.messages(messages);
                } else {
                    errorHandler.message(PayPalCommerceGateway.labels.error.required.generic);
                }

                return actions.reject();
            }
        }

        const form = document.querySelector(checkoutFormSelector);
        if (form) {
            jQuery('#ppcp-funding-source-form-input').remove();
            form.insertAdjacentHTML(
                'beforeend',
                `<input type="hidden" name="ppcp-funding-source" value="${data.fundingSource}" id="ppcp-funding-source-form-input">`
            )
        }

        const isFreeTrial = PayPalCommerceGateway.is_free_trial_cart;
        if (isFreeTrial && data.fundingSource !== 'card') {
            freeTrialHandler.handle();
            return actions.reject();
        }

        if (context === 'checkout') {
            try {
                await formSaver.save(form);
            } catch (error) {
                console.error(error);
            }
        }
    };

    const onSmartButtonsInit = () => {
        buttonsSpinner.unblock();
    };
    const renderer = new Renderer(creditCardRenderer, PayPalCommerceGateway, onSmartButtonClick, onSmartButtonsInit);
    const messageRenderer = new MessageRenderer(PayPalCommerceGateway.messages);

    if (PayPalCommerceGateway.mini_cart_buttons_enabled === '1') {
        const miniCartBootstrap = new MiniCartBootstap(
            PayPalCommerceGateway,
            renderer,
            errorHandler,
        );

        miniCartBootstrap.init();
        buttonModuleWatcher.registerContextBootstrap('mini-cart', miniCartBootstrap);
    }

    if (
        context === 'product'
        && (
            PayPalCommerceGateway.single_product_buttons_enabled === '1'
            || hasMessages()
        )
    ) {
        const singleProductBootstrap = new SingleProductBootstap(
            PayPalCommerceGateway,
            renderer,
            errorHandler,
        );

        singleProductBootstrap.init();
        buttonModuleWatcher.registerContextBootstrap('product', singleProductBootstrap);
    }

    if (context === 'cart') {
        const cartBootstrap = new CartBootstrap(
            PayPalCommerceGateway,
            renderer,
            errorHandler,
        );

        cartBootstrap.init();
        buttonModuleWatcher.registerContextBootstrap('cart', cartBootstrap);
    }

    if (context === 'checkout') {
        const checkoutBootstap = new CheckoutBootstap(
            PayPalCommerceGateway,
            renderer,
            spinner,
            errorHandler,
        );

        checkoutBootstap.init();
        buttonModuleWatcher.registerContextBootstrap('checkout', checkoutBootstap);
    }

    if (context === 'pay-now' ) {
        const payNowBootstrap = new PayNowBootstrap(
            PayPalCommerceGateway,
            renderer,
            spinner,
            errorHandler,
        );
        payNowBootstrap.init();
        buttonModuleWatcher.registerContextBootstrap('pay-now', payNowBootstrap);
    }

    const messagesBootstrap = new MessagesBootstrap(
        PayPalCommerceGateway,
        messageRenderer,
    );
    messagesBootstrap.init();
};

document.addEventListener(
    'DOMContentLoaded',
    () => {
        if (!typeof (PayPalCommerceGateway)) {
            console.error('PayPal button could not be configured.');
            return;
        }

        if (
            PayPalCommerceGateway.context !== 'checkout'
            && PayPalCommerceGateway.data_client_id.user === 0
            && PayPalCommerceGateway.data_client_id.has_subscriptions
        ) {
            return;
        }

        const paypalButtonGatewayIds = [
            PaymentMethods.PAYPAL,
            ...Object.entries(PayPalCommerceGateway.separate_buttons).map(([k, data]) => data.id),
        ]

        // Sometimes PayPal script takes long time to load,
        // so we additionally hide the standard order button here to avoid failed orders.
        // Normally it is hidden later after the script load.
        const hideOrderButtonIfPpcpGateway = () => {
            // only in checkout and pay now page, otherwise it may break things (e.g. payment via product page),
            // and also the loading spinner may look weird on other pages
            if (
                !['checkout', 'pay-now'].includes(PayPalCommerceGateway.context)
                || isChangePaymentPage()
                || (PayPalCommerceGateway.is_free_trial_cart && PayPalCommerceGateway.vaulted_paypal_email !== '')
            ) {
                return;
            }

            const currentPaymentMethod = getCurrentPaymentMethod();
            const isPaypalButton = paypalButtonGatewayIds.includes(currentPaymentMethod);
            const isCards = currentPaymentMethod === PaymentMethods.CARDS;

            setVisibleByClass(ORDER_BUTTON_SELECTOR, !isPaypalButton && !isCards, 'ppcp-hidden');

            if (isPaypalButton) {
                // stopped after the first rendering of the buttons, in onInit
                buttonsSpinner.block();
            } else {
                buttonsSpinner.unblock();
            }

            if (isCards) {
                cardsSpinner.block();
            } else {
                cardsSpinner.unblock();
            }
        }

        jQuery(document).on('hosted_fields_loaded', () => {
            cardsSpinner.unblock();
        });

        let bootstrapped = false;

        hideOrderButtonIfPpcpGateway();

        jQuery(document.body).on('updated_checkout payment_method_selected', () => {
            if (bootstrapped) {
                return;
            }

            hideOrderButtonIfPpcpGateway();
        });

        loadPaypalScript(PayPalCommerceGateway, () => {
            bootstrapped = true;

            bootstrap();
        });
    },
);
