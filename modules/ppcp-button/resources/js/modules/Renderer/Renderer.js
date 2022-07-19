import merge from "deepmerge";

class Renderer {
    constructor(creditCardRenderer, defaultSettings, onSmartButtonClick, onSmartButtonsInit) {
        this.defaultSettings = defaultSettings;
        this.creditCardRenderer = creditCardRenderer;
        this.onSmartButtonClick = onSmartButtonClick;
        this.onSmartButtonsInit = onSmartButtonsInit;

        this.renderedSources = new Set();
    }

    render(contextConfig, settingsOverride = {}) {
        const settings = merge(this.defaultSettings, settingsOverride);

        const enabledSeparateGateways = Object.fromEntries(Object.entries(
            settings.separate_buttons).filter(([s, data]) => document.querySelector(data.wrapper)
        ));
        const hasEnabledSeparateGateways = Object.keys(enabledSeparateGateways).length !== 0;

        if (!hasEnabledSeparateGateways) {
            this.renderButtons(
                settings.button.wrapper,
                settings.button.style,
                contextConfig
            );
        } else {
            // render each button separately
            for (const fundingSource of paypal.getFundingSources().filter(s => !(s in enabledSeparateGateways))) {
                let style = settings.button.style;
                if (fundingSource !== 'paypal') {
                    style = {
                        shape: style.shape,
                    };
                }

                this.renderButtons(
                    settings.button.wrapper,
                    style,
                    contextConfig,
                    fundingSource
                );
            }
        }

        this.creditCardRenderer.render(settings.hosted_fields.wrapper, contextConfig);

        for (const [fundingSource, data] of Object.entries(enabledSeparateGateways)) {
            this.renderButtons(
                data.wrapper,
                data.style,
                contextConfig,
                fundingSource
            );
        }
    }

    renderButtons(wrapper, style, contextConfig, fundingSource = null) {
        if (! document.querySelector(wrapper) || this.isAlreadyRendered(wrapper, fundingSource) || 'undefined' === typeof paypal.Buttons ) {
            return;
        }

        if (fundingSource) {
            contextConfig.fundingSource = fundingSource;
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

        this.renderedSources.add(wrapper + fundingSource ?? '');
    }

    isAlreadyRendered(wrapper, fundingSource) {
        return this.renderedSources.has(wrapper + fundingSource ?? '');
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
