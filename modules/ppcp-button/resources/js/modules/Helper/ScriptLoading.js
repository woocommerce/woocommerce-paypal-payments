import dataClientIdAttributeHandler from "../DataClientIdAttributeHandler";
import {loadScript} from "@paypal/paypal-js";
import widgetBuilder from "../Renderer/WidgetBuilder";
import merge from "deepmerge";
import {keysToCamelCase} from "./Utils";

// This component may be used by multiple modules. This assures that options are shared between all instances.
let options = window.ppcpWidgetBuilder = window.ppcpWidgetBuilder || {
    isLoading: false,
    onLoadedCallbacks: []
};

export const loadPaypalScript = (config, onLoaded) => {
    // If PayPal is already loaded call the onLoaded callback and return.
    if (typeof paypal !== 'undefined') {
        onLoaded();
        return;
    }

    // Add the onLoaded callback to the onLoadedCallbacks stack.
    options.onLoadedCallbacks.push(onLoaded);

    // Return if it's still loading.
    if (options.isLoading) {
        return;
    }
    options.isLoading = true;

    // Callback to be called once the PayPal script is loaded.
    const callback = (paypal) => {
        widgetBuilder.setPaypal(paypal);

        for (const onLoadedCallback of options.onLoadedCallbacks) {
            onLoadedCallback();
        }

        options.isLoading = false;
        options.onLoadedCallbacks = [];
    }

    // Build the PayPal script options.
    let scriptOptions = keysToCamelCase(config.url_params);
    scriptOptions = merge(scriptOptions, config.script_attributes);

    // Load PayPal script for special case with data-client-token
    if (config.data_client_id.set_attribute) {
        dataClientIdAttributeHandler(scriptOptions, config.data_client_id, callback);
        return;
    }

    // Load PayPal script
    loadScript(scriptOptions).then(callback);
}

export const loadPaypalJsScript = (options, buttons, container) => {
    loadScript(options).then((paypal) => {
        paypal.Buttons(buttons).render(container);
    });
}
