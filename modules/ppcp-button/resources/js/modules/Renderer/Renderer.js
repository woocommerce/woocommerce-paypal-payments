import merge from "deepmerge";

class Renderer {
    constructor(creditCardRenderer, defaultSettings, onSmartButtonClick, onSmartButtonsInit) {
        this.defaultSettings = defaultSettings;
        this.creditCardRenderer = creditCardRenderer;
        this.onSmartButtonClick = onSmartButtonClick;
        this.onSmartButtonsInit = onSmartButtonsInit;

        this.buttonsOptions = {};
        this.onButtonsInitListeners = {};

        this.renderedSources = new Set();
    }

    render(contextConfig, settingsOverride = {}, contextConfigOverride = () => {}) {
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
            this.creditCardRenderer.render(settings.hosted_fields.wrapper, contextConfigOverride);
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
            onInit: (data, actions) => {
                if (this.onSmartButtonsInit) {
                    this.onSmartButtonsInit(data, actions);
                }
                this.handleOnButtonsInit(wrapper, data, actions);
            },
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

    disableCreditCardFields() {
        this.creditCardRenderer.disableFields();
    }

    enableCreditCardFields() {
        this.creditCardRenderer.enableFields();
    }

    onButtonsInit(wrapper, handler, reset) {
        this.onButtonsInitListeners[wrapper] = reset ? [] : (this.onButtonsInitListeners[wrapper] || []);
        this.onButtonsInitListeners[wrapper].push(handler);
    }

    handleOnButtonsInit(wrapper, data, actions) {

        this.buttonsOptions[wrapper] = {
            data: data,
            actions: actions
        }

        if (this.onButtonsInitListeners[wrapper]) {
            for (let handler of this.onButtonsInitListeners[wrapper]) {
                if (typeof handler === 'function') {
                    handler({
                        wrapper: wrapper,
                        ...this.buttonsOptions[wrapper]
                    });
                }
            }
        }
    }

    disableSmartButtons(wrapper) {
        if (!this.buttonsOptions[wrapper]) {
            return;
        }
        this.buttonsOptions[wrapper].actions.disable();
    }

    enableSmartButtons(wrapper) {
        if (!this.buttonsOptions[wrapper]) {
            return;
        }
        this.buttonsOptions[wrapper].actions.enable();
    }
}

export default Renderer;
