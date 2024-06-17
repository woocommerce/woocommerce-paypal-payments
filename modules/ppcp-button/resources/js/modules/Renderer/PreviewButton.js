import merge from 'deepmerge';

/**
 * Base class for APM button previews, used on the plugin's settings page.
 */
class PreviewButton {
    /**
     * @param {string} selector - CSS ID of the wrapper, including the `#`
     * @param {object} apiConfig - PayPal configuration object; retrieved via a
     * widgetBuilder API method
     */
    constructor({
        selector,
        apiConfig,
    }) {
        this.apiConfig = apiConfig;
        this.defaultAttributes = {};
        this.buttonConfig = {};
        this.ppcpConfig = {};
        this.isDynamic = true;

        // The selector is usually overwritten in constructor of derived class.
        this.selector = selector;
        this.wrapper = selector;

        this.domWrapper = null;
    }

    /**
     * Creates a new DOM node to contain the preview button.
     *
     * @return {jQuery} Always a single jQuery element with the new DOM node.
     */
    createNewWrapper() {
        const previewId = this.selector.replace('#', '');
        const previewClass = 'ppcp-button-apm';

        return jQuery(`<div id='${previewId}' class='${previewClass}'>`);
    }

    /**
     * Toggle the "dynamic" nature of the preview.
     * When the button is dynamic, it will reflect current form values. A static button always
     * uses the settings that were provided via PHP.
     *
     * @return {this} Reference to self, for chaining.
     */
    setDynamic(state) {
        this.isDynamic = state;
        return this;
    }

    /**
     * Sets server-side configuration for the button.
     *
     * @return {this} Reference to self, for chaining.
     */
    setButtonConfig(config) {
        this.buttonConfig = merge(this.defaultAttributes, config);
        this.buttonConfig.button.wrapper = this.selector;

        return this;
    }

    /**
     * Updates the button configuration with current details from the form.
     *
     * @return {this} Reference to self, for chaining.
     */
    setPpcpConfig(config) {
        this.ppcpConfig = merge({}, config);

        return this;
    }

    /**
     * Merge form details into the config object for preview.
     * Mutates the previewConfig object; no return value.
     */
    dynamicPreviewConfig(previewConfig, formConfig) {
        // Implement in derived class.
    }

    /**
     * Responsible for creating the actual payment button preview.
     * Called by the `render()` method, after the wrapper DOM element is ready.
     */
    createButton(previewConfig) {
        throw new Error('The "createButton" method must be implemented by the derived class');
    }

    /**
     * Refreshes the button in the DOM.
     * Will always create a new button in the DOM.
     */
    render() {
        // The APM button is disabled and cannot be enabled on the current page: Do not render it.
        if (!this.isDynamic && !this.buttonConfig.is_enabled) {
            return;
        }

        if (!this.domWrapper) {
            if (!this.wrapper) {
                console.error('Skip render, button is not configured yet');
                return;
            }
            this.domWrapper = this.createNewWrapper();
            this.domWrapper.insertAfter(this.wrapper);
        } else {
            this.domWrapper.empty().show();
        }

        this.isVisible = true;
        const previewButtonConfig = merge({}, this.buttonConfig);
        const previewPpcpConfig = this.isDynamic ? merge({}, this.ppcpConfig) : {};
        previewButtonConfig.button.wrapper = this.selector;

        this.dynamicPreviewConfig(previewButtonConfig, previewPpcpConfig);

        /*
         * previewButtonConfig.button.wrapper must be different from this.ppcpConfig.button.wrapper!
         * If both selectors point to the same element, an infinite loop is triggered.
         */
        const buttonWrapper = previewButtonConfig.button.wrapper.replace(/^#/, '');
        const ppcpWrapper = this.ppcpConfig.button.wrapper.replace(/^#/, '');

        if (buttonWrapper === ppcpWrapper) {
            throw new Error(`[APM Preview Button] Infinite loop detected. Provide different selectors for the button/ppcp wrapper elements! Selector: "#${buttonWrapper}"`);
        }

        this.createButton(previewButtonConfig);

        /*
         * Unfortunately, a hacky way that is required to guarantee that this preview button is
         * actually visible after calling the `render()` method. On some sites, we've noticed that
         * certain JS events (like `ppcp-hidden`) do not fire in the expected order. This causes
         * problems with preview buttons not being displayed instantly.
         *
         * Using a timeout here will make the button visible again at the end of the current
         * event queue.
         */
        setTimeout(() => this.domWrapper.show());
    }

    remove() {
        this.isVisible = false;

        if (this.domWrapper) {
            this.domWrapper.hide().empty();
        }
    }
}

export default PreviewButton;
