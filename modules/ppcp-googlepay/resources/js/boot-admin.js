import {loadCustomScript} from "@paypal/paypal-js";
import GooglepayButton from "./GooglepayButton";
import widgetBuilder from "../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder";

(function ({
   buttonConfig,
   jQuery
}) {

    let googlePayConfig;
    let buttonQueue = [];
    let bootstrapped = false;

    jQuery(document).on('ppcp_paypal_render_preview', (ev, ppcpConfig) => {
        if (bootstrapped) {
            createButton(ppcpConfig);
        } else {
            buttonQueue.push({
                ppcpConfig: JSON.parse(JSON.stringify(ppcpConfig))
            });
        }
    });

    const createButton = function (ppcpConfig) {
        const selector = ppcpConfig.button.wrapper + 'GooglePay';

        buttonConfig = JSON.parse(JSON.stringify(buttonConfig));
        buttonConfig.button.wrapper = selector;

        const wrapperElement = `<div id="${selector.replace('#', '')}" class="ppcp-button-googlepay"></div>`;

        if (!jQuery(selector).length) {
            jQuery(ppcpConfig.button.wrapper).after(wrapperElement);
        } else {
            jQuery(selector).replaceWith(wrapperElement);
        }

        const button = new GooglepayButton(
            'preview',
            null,
            buttonConfig,
            ppcpConfig,
        );

        button.init(googlePayConfig);
    }

    const bootstrap = async function () {
        googlePayConfig = await widgetBuilder.paypal.Googlepay().config();

        let options;
        while (options = buttonQueue.pop()) {
            createButton(options.ppcpConfig);
        }
    };

    document.addEventListener(
        'DOMContentLoaded',
        () => {

            if (typeof (buttonConfig) === 'undefined') {
                console.error('PayPal button could not be configured.');
                return;
            }

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

            // Wait for PayPal to be loaded externally
            if (typeof widgetBuilder.paypal !== 'undefined') {
                paypalLoaded = true;
                tryToBoot();
            }

            jQuery(document).on('ppcp-paypal-loaded', () => {
                paypalLoaded = true;
                tryToBoot();
            });
        },
    );

})({
    buttonConfig: window.wc_ppcp_googlepay_admin,
    jQuery: window.jQuery
});
