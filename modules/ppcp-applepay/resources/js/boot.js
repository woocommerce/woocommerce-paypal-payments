import {loadCustomScript} from "@paypal/paypal-js";
import {loadPaypalScript} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";
import ApplepayManager from "./ApplepayManager";

(function ({
               buttonConfig,
               ppcpConfig,
               jQuery
           }) {

    let manager;

    const bootstrap = function () {
        manager = new ApplepayManager(buttonConfig, ppcpConfig);
        manager.init();
    };

    jQuery(document.body).on('updated_cart_totals updated_checkout', () => {
        if (manager) {
            manager.reinit();
        }
    });

    document.addEventListener(
        'DOMContentLoaded',
        () => {
            if (
                (typeof (buttonConfig) === 'undefined') ||
                (typeof (ppcpConfig) === 'undefined')
            ) {
                return;
            }
            const isMiniCart = ppcpConfig.mini_cart_buttons_enabled;
            const isButton = jQuery('#' + buttonConfig.button.wrapper).length > 0;
            // If button wrapper is not present then there is no need to load the scripts.
            // minicart loads later?
            if (!isMiniCart && !isButton) {
                return;
            }

            let bootstrapped = false;
            let paypalLoaded = false;
            let applePayLoaded = false;

            const tryToBoot = () => {
                if (!bootstrapped && paypalLoaded && applePayLoaded) {
                    bootstrapped = true;
                    bootstrap();
                }
            }

            // Load ApplePay SDK
            loadCustomScript({ url: buttonConfig.sdk_url }).then(() => {
                applePayLoaded = true;
                tryToBoot();
            });

            // Load PayPal
            loadPaypalScript(ppcpConfig, () => {
                paypalLoaded = true;
                tryToBoot();
            });
        },
    );

})({
    buttonConfig: window.wc_ppcp_applepay,
    ppcpConfig: window.PayPalCommerceGateway,
    jQuery: window.jQuery
});
