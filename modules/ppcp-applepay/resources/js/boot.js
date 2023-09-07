import {loadCustomScript} from "@paypal/paypal-js";
import {loadPaypalScript} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";
import ApplepayManager from "./ApplepayManager";

(function ({
               buttonConfig,
               ppcpConfig,
               jQuery
           }) {

    const bootstrap = function () {
        const manager = new ApplepayManager(buttonConfig, ppcpConfig);
        manager.init();
    };

    document.addEventListener(
        'DOMContentLoaded',
        () => {
            if (
                (typeof (buttonConfig) === 'undefined') ||
                (typeof (ppcpConfig) === 'undefined')
            ) {
                console.error('PayPal button could not be configured.');
                return;
            }
            console.log(buttonConfig.button.wrapper)
            console.log(jQuery(buttonConfig.button.wrapper).length)
            // If button wrapper is not present then there is no need to load the scripts.
            if (!jQuery(buttonConfig.button.wrapper).length) {
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
