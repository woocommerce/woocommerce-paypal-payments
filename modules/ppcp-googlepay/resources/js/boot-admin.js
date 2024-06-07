import GooglepayButton from "./GooglepayButton";
import widgetBuilder from "../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder";
import PreviewButton from "../../../ppcp-button/resources/js/modules/Renderer/PreviewButton";
import PreviewButtonManager from "../../../ppcp-button/resources/js/modules/Renderer/PreviewButtonManager";

/**
 * Accessor that creates and returns a single PreviewButtonManager instance.
 */
const buttonManager = () => {
    if (!GooglePayPreviewButtonManager.instance) {
        GooglePayPreviewButtonManager.instance = new GooglePayPreviewButtonManager();
    }

    return GooglePayPreviewButtonManager.instance;
}


/**
 * Manages all GooglePay preview buttons on this page.
 */
class GooglePayPreviewButtonManager extends PreviewButtonManager {
    constructor() {
        const defaultButton = {
            style: {
                type: 'pay',
                color: 'black',
                language: 'en'
            }
        };

        const args = {
            methodName: 'GooglePay',
            buttonConfig: window.wc_ppcp_googlepay_admin,
            widgetBuilder,
            defaultAttributes: {button: defaultButton}
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
        const apiMethod = this.widgetBuilder?.paypal?.Googlepay()?.config

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
     * @return {GooglePayPreviewButton}
     */
    createButtonInst(wrapperId) {
        return new GooglePayPreviewButton({
            selector: wrapperId,
            configResponse: this.configResponse,
            defaultAttributes: this.defaultAttributes
        });
    }
}


/**
 * A single GooglePay preview button instance.
 */
class GooglePayPreviewButton extends PreviewButton {
    constructor(args) {
        super(args);

        this.selector = `${args.selector}GooglePay`
    }

    createNewWrapper() {
        const element = super.createNewWrapper();
        element.addClass('ppcp-button-googlepay');

        return element;
    }

    createButton() {
        const button = new GooglepayButton(
            'preview',
            null,
            this.buttonConfig,
            this.ppcpConfig,
        );

        button.init(this.configResponse);

        return button;
    }
}

// Initialize the preview button manager.
buttonManager();
