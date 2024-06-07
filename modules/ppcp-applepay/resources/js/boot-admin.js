import ApplepayButton from "./ApplepayButton";
import widgetBuilder from "../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder";
import PreviewButton from "../../../ppcp-button/resources/js/modules/Renderer/PreviewButton";
import PreviewButtonManager from "../../../ppcp-button/resources/js/modules/Renderer/PreviewButtonManager";

/**
 * Accessor that creates and returns a single PreviewButtonManager instance.
 */
const buttonManager = () => {
    if (!ApplePayPreviewButtonManager.instance) {
        ApplePayPreviewButtonManager.instance = new ApplePayPreviewButtonManager();
    }

    return ApplePayPreviewButtonManager.instance;
}


/**
 * Manages all Apple Pay preview buttons on this page.
 */
class ApplePayPreviewButtonManager extends PreviewButtonManager {
    constructor() {
        const args = {
            methodName: 'ApplePay',
            buttonConfig: window.wc_ppcp_applepay_admin,
            widgetBuilder,
        };

        super(args);
    }

    /**
     * Responsible for fetching and returning the PayPal configuration object for this payment
     * method.
     *
     * @return {Promise<{}>}
     */
    async fetchConfig() {
        const apiMethod = this.widgetBuilder?.paypal?.Applepay()?.config

        if (!apiMethod) {
            this.error('configuration object cannot be retrieved from PayPal');
            return {};
        }

        return await apiMethod();
    }

    /**
     * This method is responsible for creating a new PreviewButton instance and returning it.
     *
     * @param {string} wrapperId - CSS ID of the wrapper element.
     * @return {ApplePayPreviewButton}
     */
    createButtonInst(wrapperId) {
        return new ApplePayPreviewButton({
            selector: wrapperId,
            apiConfig: this.apiConfig
        });
    }
}


/**
 * A single Apple Pay preview button instance.
 */
class ApplePayPreviewButton extends PreviewButton {
    constructor(args) {
        super(args);

        this.selector = `${args.selector}ApplePay`
        this.defaultAttributes = {
            button: {
                type: 'pay',
                color: 'black',
                lang: 'en'
            }
        };
    }

    createNewWrapper() {
        const element = super.createNewWrapper();
        element.addClass('ppcp-button-applepay');

        return element;
    }

    createButton(buttonConfig) {
        const button = new ApplepayButton(
            'preview',
            null,
            buttonConfig,
            this.ppcpConfig,
        );

        button.init(this.apiConfig);
    }

    /**
     * Merge form details into the config object for preview.
     * Mutates the previewConfig object; no return value.
     */
    dynamicPreviewConfig(buttonConfig, ppcpConfig) {
        // The Apple Pay button expects the "wrapper" to be an ID without `#` prefix!
        buttonConfig.button.wrapper = buttonConfig.button.wrapper.replace(/^#/, '');

        // Merge the current form-values into the preview-button configuration.
        if (ppcpConfig.button) {
            buttonConfig.button.type = ppcpConfig.button.style.type;
            buttonConfig.button.color = ppcpConfig.button.style.color;
            buttonConfig.button.lang = ppcpConfig.button.style?.lang || ppcpConfig.button.style.language;
        }
    }
}

// Initialize the preview button manager.
buttonManager();

/*
(function ({
   buttonConfig,
   jQuery
}) {

    let applePayConfig;
    let buttonQueue = [];
    let activeButtons = {};
    let bootstrapped = false;

    // React to PayPal config changes.
    jQuery(document).on('ppcp_paypal_render_preview', (ev, ppcpConfig) => {
        if (bootstrapped) {
            createButton(ppcpConfig);
        } else {
            buttonQueue.push({
                ppcpConfig: JSON.parse(JSON.stringify(ppcpConfig))
            });
        }
    });

    // React to ApplePay config changes.
    jQuery([
        '#ppcp-applepay_button_enabled',
        '#ppcp-applepay_button_type',
        '#ppcp-applepay_button_color',
        '#ppcp-applepay_button_language'
    ].join(',')).on('change', () => {
        for (const [selector, ppcpConfig] of Object.entries(activeButtons)) {
            createButton(ppcpConfig);
        }
    });

    // Maybe we can find a more elegant reload method when transitioning from styling modes.
    jQuery([
        '#ppcp-smart_button_enable_styling_per_location'
    ].join(',')).on('change', () => {
        setTimeout(() => {
            for (const [selector, ppcpConfig] of Object.entries(activeButtons)) {
                createButton(ppcpConfig);
            }
        }, 100);
    });

    const shouldDisplayPreviewButton = function () {
        // TODO - original condition, which is wrong.
        return jQuery('#ppcp-applepay_button_enabled').is(':checked');
    }

    const applyConfigOptions = function (buttonConfig) {
        buttonConfig.button = buttonConfig.button || {};
        buttonConfig.button.type = jQuery('#ppcp-applepay_button_type').val();
        buttonConfig.button.color = jQuery('#ppcp-applepay_button_color').val();
        buttonConfig.button.lang = jQuery('#ppcp-applepay_button_language').val();
    }

    const createButton = function (ppcpConfig) {
        const selector = ppcpConfig.button.wrapper + 'ApplePay';

        if (!shouldDisplayPreviewButton()) {
            jQuery(selector).remove();
            return;
        }

        buttonConfig = JSON.parse(JSON.stringify(buttonConfig));
        buttonConfig.button.wrapper = selector.replace('#', '');
        applyConfigOptions(buttonConfig);

        const wrapperElement = `<div id="${selector.replace('#', '')}" class="ppcp-button-apm ppcp-button-applepay"></div>`;

        if (!jQuery(selector).length) {
            jQuery(ppcpConfig.button.wrapper).after(wrapperElement);
        } else {
            jQuery(selector).replaceWith(wrapperElement);
        }

        const button = new ApplepayButton(
            'preview',
            null,
            buttonConfig,
            ppcpConfig,
        );

        button.init(applePayConfig);

        activeButtons[selector] = ppcpConfig;
    }

    const bootstrap = async function () {
        if (!widgetBuilder.paypal) {
            return;
        }

        applePayConfig = await widgetBuilder.paypal.Applepay().config();

        // We need to set bootstrapped here otherwise applePayConfig may not be set.
        bootstrapped = true;

        let options;
        while (options = buttonQueue.pop()) {
            createButton(options.ppcpConfig);
        }

        if (!window.ApplePaySession) {
            jQuery('body').addClass('ppcp-non-ios-device')
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
            let applePayLoaded = false;

            const tryToBoot = () => {
                if (!bootstrapped && paypalLoaded && applePayLoaded) {
                    bootstrap();
                }
            }

            // Load ApplePay SDK
            loadCustomScript({url: buttonConfig.sdk_url}).then(() => {
                applePayLoaded = true;
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
    buttonConfig: window.wc_ppcp_applepay_admin,
    jQuery: window.jQuery
});
*/
