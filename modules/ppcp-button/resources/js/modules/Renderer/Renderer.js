import merge from "deepmerge";
import {loadScript} from "@paypal/paypal-js";
import {keysToCamelCase} from "../Helper/Utils";
import widgetBuilder from "./WidgetBuilder";

class Renderer {
    constructor(creditCardRenderer, defaultSettings, onSmartButtonClick, onSmartButtonsInit) {
        this.defaultSettings = defaultSettings;
        this.creditCardRenderer = creditCardRenderer;
        this.onSmartButtonClick = onSmartButtonClick;
        this.onSmartButtonsInit = onSmartButtonsInit;

        this.buttonsOptions = {};
        this.onButtonsInitListeners = {};

        this.renderedSources = new Set();

        this.reloadEventName = 'ppcp-reload-buttons';
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
        if (! document.querySelector(wrapper) || this.isAlreadyRendered(wrapper, fundingSource, hasEnabledSeparateGateways) ) {
            // Try to render registered buttons again in case they were removed from the DOM by an external source.
            widgetBuilder.renderButtons([wrapper, fundingSource]);
            return;
        }

        if (fundingSource) {
            contextConfig.fundingSource = fundingSource;
        }

        const buttonsOptions = () => {
            return {
                style,
                ...contextConfig,
                onClick: this.onSmartButtonClick,
                onInit: (data, actions) => {
                    if (this.onSmartButtonsInit) {
                        this.onSmartButtonsInit(data, actions);
                    }
                    this.handleOnButtonsInit(wrapper, data, actions);
                },
            }
        }

        jQuery(document)
            .off(this.reloadEventName, wrapper)
            .on(this.reloadEventName, wrapper, (event, settingsOverride = {}, triggeredFundingSource) => {

                // Only accept events from the matching funding source
                if (fundingSource && triggeredFundingSource && (triggeredFundingSource !== fundingSource)) {
                    return;
                }

                const settings = merge(this.defaultSettings, settingsOverride);
                let scriptOptions = keysToCamelCase(settings.url_params);
                scriptOptions = merge(scriptOptions, settings.script_attributes);

                loadScript(scriptOptions).then((paypal) => {
                    widgetBuilder.setPaypal(paypal);
                    widgetBuilder.registerButtons([wrapper, fundingSource], buttonsOptions());
                    widgetBuilder.renderAll();
                });
            });

        this.renderedSources.add(wrapper + (fundingSource ?? ''));

        if (typeof paypal !== 'undefined' && typeof paypal.Buttons !== 'undefined') {
            widgetBuilder.registerButtons([wrapper, fundingSource], buttonsOptions());
            widgetBuilder.renderButtons([wrapper, fundingSource]);
        }
    }

    isAlreadyRendered(wrapper, fundingSource) {
        return this.renderedSources.has(wrapper + (fundingSource ?? ''));
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
