import dataClientIdAttributeHandler from "../DataClientIdAttributeHandler";
import {loadScript} from "@paypal/paypal-js";

export const loadPaypalScript = (config, onLoaded) => {
    if (typeof paypal !== 'undefined') {
        onLoaded();
        return;
    }

    const script = document.createElement('script');
    script.addEventListener('load', onLoaded);
    script.setAttribute('src', config.url);
    Object.entries(config.script_attributes).forEach(
        (keyValue) => {
            script.setAttribute(keyValue[0], keyValue[1]);
        }
    );

    if (config.data_client_id.set_attribute) {
        dataClientIdAttributeHandler(script, config.data_client_id);
        return;
    }

    document.body.appendChild(script);
}

export const loadPaypalJsScript = (options, buttons, container) => {
    loadScript(options).then((paypal) => {
        paypal.Buttons(buttons).render(container);
    });
}
