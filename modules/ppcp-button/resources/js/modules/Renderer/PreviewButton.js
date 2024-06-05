import merge from "deepmerge";

/**
 * Base class for APM button previews, used on the plugin's settings page.
 */
class PreviewButton {
    /**
     * @param {string} selector - CSS ID of the wrapper, including the `#`
     * @param {object} configResponse - PayPal configuration object; retrieved via a
     * widgetBuilder API method
     * @param {object} defaultAttributes - Optional.
     */
    constructor({selector, configResponse, defaultAttributes = {}}) {
        this.configResponse = configResponse;
        this.defaultAttributes = defaultAttributes;
        this.buttonConfig = {};
        this.ppcpConfig = {};

        // Usually overwritten in constructor of derived class.
        this.selector = selector;

        this.domWrapper = null;
        this.payButton = null;
    }

    /**
     * Creates a new DOM node to contain the preview button.
     *
     * @return {jQuery} Always a single jQuery element with the new DOM node.
     */
    createNewWrapper() {
        const previewId = this.selector.replace('#', '')
        const previewClass = 'ppcp-button-apm';

        return jQuery(`<div id="${previewId}" class="${previewClass}">`)
    }

    /**
     * Updates the internal button configuration. Does not trigger a redraw.
     *
     * @return {this} Reference to self, for chaining.
     */
    config({buttonConfig, ppcpConfig}) {
        if (ppcpConfig) {
            this.ppcpConfig = merge({}, ppcpConfig);
        }

        if (buttonConfig) {
            this.buttonConfig = merge(this.defaultAttributes, buttonConfig)
            this.buttonConfig.button.wrapper = this.selector
        }

        return this;
    }

    /**
     * Responsible for creating the actual payment button preview.
     * Called by the `render()` method, after the wrapper DOM element is ready.
     *
     * @return {any} Return value is assigned to `this.payButton`
     */
    createButton() {
        throw new Error('The "createButton" method must be implemented by the derived class');
    }

    /**
     * Refreshes the button in the DOM.
     * Will always create a new button in the DOM.
     */
    render() {
        this.remove();

        if (!this.buttonConfig?.button?.wrapper) {
            console.error('Skip render, button is not configured yet');
            return;
        }

        this.isVisible = true;

        const newDomWrapper = this.createNewWrapper();

        if (this.domWrapper?.length) {
            this.domWrapper.replaceWith(newDomWrapper);
        } else {
            jQuery(this.ppcpConfig.button.wrapper).after(newDomWrapper);
        }
        this.domWrapper = newDomWrapper;

        this.payButton = this.createButton();
    }

    remove() {
        this.isVisible = false;

        // The current payButtons have no remove/cleanup function.
        this.payButton = null;

        if (this.domWrapper?.remove) {
            this.domWrapper.remove();
        }

        this.domWrapper = null;
    }
}

export default PreviewButton;
