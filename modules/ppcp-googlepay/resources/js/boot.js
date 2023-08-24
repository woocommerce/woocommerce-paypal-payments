import {loadCustomScript} from "@paypal/paypal-js";
import {loadPaypalScript} from "../../../ppcp-button/resources/js/modules/Helper/ScriptLoading";
import GooglepayManager from "./GooglepayManager";

(function ({
   buttonConfig,
   ppcpConfig,
   jQuery
}) {

    const bootstrap = function () {
        const manager = new GooglepayManager(buttonConfig, ppcpConfig);
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

            // If button wrapper is not present then there is no need to load the scripts.
            if (!jQuery(buttonConfig.button.wrapper).length) {
                return;
            }

            let bootstrapped = false;
            let paypalLoaded = false;
            let googlePayLoaded = false;

            const tryToBoot = () => {
                if (!bootstrapped && paypalLoaded && googlePayLoaded) {
                    bootstrapped = true;
                    bootstrap();
                }
            }

            // Load GooglePay SDK
            loadCustomScript({ url: buttonConfig.sdk_url }).then(() => {
                googlePayLoaded = true;
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
    buttonConfig: window.wc_ppcp_googlepay,
    ppcpConfig: window.PayPalCommerceGateway,
    jQuery: window.jQuery
});
