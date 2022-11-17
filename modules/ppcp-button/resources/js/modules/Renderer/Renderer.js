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
                contextConfig,
                hasEnabledSeparateGateways
            );
        } else {
            // render each button separately
            for (const fundingSource of paypal.getFundingSources().filter(s => !(s in enabledSeparateGateways))) {
                let style = settings.button.style;
                if (fundingSource !== 'paypal') {
                    style = {
                        shape: style.shape,
                        color: style.color,
                    };
                    if (fundingSource !== 'paylater') {
                        delete style.color;
                    }
                }

                this.renderButtons(
                    settings.button.wrapper,
                    style,
                    contextConfig,
                    hasEnabledSeparateGateways,
                    fundingSource
                );
            }
        }

        if (this.creditCardRenderer) {
            this.creditCardRenderer.render(settings.hosted_fields.wrapper, contextConfig);
        }

        for (const [fundingSource, data] of Object.entries(enabledSeparateGateways)) {
            this.renderButtons(
                data.wrapper,
                data.style,
                contextConfig,
                hasEnabledSeparateGateways,
                fundingSource
            );
        }
    }

    renderButtons(wrapper, style, contextConfig, hasEnabledSeparateGateways, fundingSource = null) {
        if (! document.querySelector(wrapper) || this.isAlreadyRendered(wrapper, fundingSource, hasEnabledSeparateGateways) || 'undefined' === typeof paypal.Buttons ) {
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

    isAlreadyRendered(wrapper, fundingSource, hasEnabledSeparateGateways) {
        // Simply check that has child nodes when we do not need to render buttons separately,
        // this will reduce the risk of breaking with different themes/plugins
        // and on the cart page (where we also do not need to render separately), which may fully reload this part of the page.
        // Ideally we should also find a way to detect such full reloads and remove the corresponding keys from the set.
        if (!hasEnabledSeparateGateways) {
            return document.querySelector(wrapper).hasChildNodes();
        }
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
