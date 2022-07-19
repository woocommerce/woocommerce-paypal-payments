import merge from "deepmerge";

class Renderer {
    constructor(creditCardRenderer, defaultSettings, onSmartButtonClick, onSmartButtonsInit) {
        this.defaultSettings = defaultSettings;
        this.creditCardRenderer = creditCardRenderer;
        this.onSmartButtonClick = onSmartButtonClick;
        this.onSmartButtonsInit = onSmartButtonsInit;
    }

    render(contextConfig, settingsOverride = {}) {
        const settings = merge(this.defaultSettings, settingsOverride);

        this.renderButtons(settings.button.wrapper, settings.button.style, contextConfig);
        this.creditCardRenderer.render(settings.hosted_fields.wrapper, contextConfig);
        for (const [fundingSource, data] of Object.entries(settings.separate_buttons)) {
            this.renderButtons(
                data.wrapper,
                data.style,
                {
                    ...contextConfig,
                    fundingSource: fundingSource,
                }
            );
        }
    }

    renderButtons(wrapper, style, contextConfig) {
        if (! document.querySelector(wrapper) || this.isAlreadyRendered(wrapper) || 'undefined' === typeof paypal.Buttons ) {
            return;
        }

        const btn = paypal.Buttons({
            style,
            ...contextConfig,
            onClick: this.onSmartButtonClick,
            onInit: this.onSmartButtonsInit,
        });
        if (!btn.isEligible()) {
            return;
        }

        btn.render(wrapper);
    }

    isAlreadyRendered(wrapper) {
        return document.querySelector(wrapper).hasChildNodes();
    }

    hideButtons(element) {
        const domElement = document.querySelector(element);
        if (! domElement ) {
            return false;
        }
        domElement.style.display = 'none';
        return true;
    }

    showButtons(element) {
        const domElement = document.querySelector(element);
        if (! domElement ) {
            return false;
        }
        domElement.style.display = 'block';
        return true;
    }

    disableCreditCardFields() {
        this.creditCardRenderer.disableFields();
    }

    enableCreditCardFields() {
        this.creditCardRenderer.enableFields();
    }
}

export default Renderer;
