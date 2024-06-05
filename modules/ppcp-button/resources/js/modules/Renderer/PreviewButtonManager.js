import {loadCustomScript} from "@paypal/paypal-js";
import merge from "deepmerge";

/**
 * Manages all PreviewButton instances of a certain payment method on the page.
 */
class PreviewButtonManager {
    constructor({buttonConfig, widgetBuilder, defaultAttributes}) {
        // Define the payment method name in the derived class.
        this.methodName = 'UNDEFINED';

        this.buttonConfig = buttonConfig;
        this.widgetBuilder = widgetBuilder;
        this.defaultAttributes = defaultAttributes;

        this.isEnabled = true
        this.buttons = {};
        this.configResponse = null;

        // Empty promise that resolves instantly when called.
        this.bootstrapping = Promise.resolve();

        // Add the bootstrap logic to the Promise chain. More `then`s are added by addButton().
        this.bootstrapping = this.bootstrapping.then(() => this.bootstrap());

        this.registerEventListeners();
    }

    /**
     * Protected method that needs to be implemented by the derived class.
     * Responsible for fetching and returning the PayPal configuration object for this payment
     * method.
     *
     * @return {Promise<{}>}
     */
    async fetchConfig() {
        throw new Error('The "fetchConfig" method must be implemented by the derived class');
    }

    /**
     * Protected method that needs to be implemented by the derived class.
     * This method is responsible for creating a new PreviewButton instance and returning it.
     *
     * @param {string} wrapperId - CSS ID of the wrapper element.
     * @return {PreviewButton}
     */
    createButtonInst(wrapperId) {
        throw new Error('The "createButtonInst" method must be implemented by the derived class');
    }

    registerEventListeners() {
        jQuery(document).on('ppcp_paypal_render_preview', (ev, ppcpConfig) => this.addButton(ppcpConfig));
        jQuery(document).on('DOMContentLoaded', () => this.bootstrapping);
    }

    /**
     * Output an error message to the console, with a module-specific prefix.
     */
    error(message, ...args) {
        console.error(`${this.methodName} ${message}`, ...args)
    }

    /**
     * Load dependencies and bootstrap the module.
     * Returns a Promise that resolves once all dependencies were loaded and the module can be
     * used without limitation.
     *
     * @return {Promise<void>}
     */
    async bootstrap() {
        if (!this.buttonConfig || !this.widgetBuilder) {
            this.error('Button could not be configured.');
            return;
        }

        // Load the custom SDK script.
        const customScriptPromise = loadCustomScript({url: this.buttonConfig.sdk_url});

        // Wait until PayPal is ready.
        const paypalPromise = new Promise(resolve => {
            if (this.widgetBuilder.paypal) {
                resolve();
            } else {
                jQuery(document).on('ppcp-paypal-loaded', resolve);
            }
        });

        await Promise.all([customScriptPromise, paypalPromise]);

        this.configResponse = await this.fetchConfig();
    }

    /**
     * Creates a new preview button, that is rendered once the bootstrapping Promise resolves.
     */
    addButton(ppcpConfig) {
        if (!ppcpConfig.button.wrapper) {
            this.error('Button did not provide a wrapper ID', ppcpConfig)
            return;
        }

        const createOrUpdateButton = () => {
            const id = ppcpConfig.button.wrapper;

            if (!this.buttons[id]) {
                this.buttons[id] = this.createButtonInst(id);
            }

            this.buttons[id].config({
                buttonConfig: this.buttonConfig,
                ppcpConfig
            }).render()
        }

        if (this.bootstrapping) {
            this.bootstrapping.then(createOrUpdateButton);
        } else {
            createOrUpdateButton();
        }
    }

    /**
     * Changes the button configuration and re-renders all buttons.
     *
     * @return {this} Reference to self, for chaining.
     */
    updateConfig(newConfig) {
        if (!newConfig || 'object' !== typeof newConfig) {
            return this;
        }

        this.buttonConfig = merge(this.buttonConfig, newConfig)

        Object.values(this.buttons).forEach(button => button.config({buttonConfig: this.buttonConfig}))
        this.renderButtons();

        return this;
    }


    /**
     * Refreshes all buttons using the latest buttonConfig.
     *
     * @return {this} Reference to self, for chaining.
     */
    renderButtons() {
        if (this.isEnabled) {
            Object.values(this.buttons).forEach(button => button.render())
        } else {
            Object.values(this.buttons).forEach(button => button.remove())
        }

        return this;
    }

    /**
     * Enables this payment method, which re-creates or refreshes all buttons.
     *
     * @return {this} Reference to self, for chaining.
     */
    enable() {
        if (!this.isEnabled) {
            this.isEnabled = true;
            this.renderButtons();
        }

        return this;
    }

    /**
     * Disables this payment method, effectively removing all preview buttons.
     *
     * @return {this} Reference to self, for chaining.
     */
    disable() {
        if (!this.isEnabled) {
            this.isEnabled = false;
            this.renderButtons();
        }

        return this;
    }
}

export default PreviewButtonManager;
