import dataClientIdAttributeHandler from "../DataClientIdAttributeHandler";
import {loadScript} from "@paypal/paypal-js";
import widgetBuilder from "../Renderer/WidgetBuilder";
import merge from "deepmerge";
import {keysToCamelCase} from "./Utils";

export const loadPaypalScript = (config, onLoaded) => {
    if (typeof paypal !== 'undefined') {
        onLoaded();
        return;
    }

    const callback = (paypal) => {
        widgetBuilder.setPaypal(paypal);
        onLoaded();
    }

    let scriptOptions = keysToCamelCase(config.url_params);
    scriptOptions = merge(scriptOptions, config.script_attributes);

    if (config.data_client_id.set_attribute) {
        dataClientIdAttributeHandler(scriptOptions, config.data_client_id, callback);
        return;
    }

    loadScript(scriptOptions).then(callback);
}

export const loadPaypalJsScript = (options, buttons, container) => {
    loadScript(options).then((paypal) => {
        paypal.Buttons(buttons).render(container);
    });
}
