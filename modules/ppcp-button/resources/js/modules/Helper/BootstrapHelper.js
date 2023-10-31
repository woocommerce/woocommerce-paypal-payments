import {disable, enable, isDisabled} from "./ButtonDisabler";
import merge from "deepmerge";

/**
 * Common Bootstrap methods to avoid code repetition.
 */
export default class BootstrapHelper {

    static handleButtonStatus(bs, options) {
        options = options || {};
        options.wrapper = options.wrapper || bs.gateway.button.wrapper;

        const wasDisabled = isDisabled(options.wrapper);
        const shouldEnable = bs.shouldEnable();

        // Handle enable / disable
        if (shouldEnable && wasDisabled) {
            bs.renderer.enableSmartButtons(options.wrapper);
            enable(options.wrapper);
        } else if (!shouldEnable && !wasDisabled) {
            bs.renderer.disableSmartButtons(options.wrapper);
            disable(options.wrapper, options.formSelector || null);
        }

        if (wasDisabled !== !shouldEnable) {
            jQuery(options.wrapper).trigger('ppcp_buttons_enabled_changed', [shouldEnable]);
        }
    }

    static shouldEnable(bs, options) {
        options = options || {};
        if (typeof options.isDisabled === 'undefined') {
            options.isDisabled = bs.gateway.button.is_disabled;
        }

        return bs.shouldRender()
            && options.isDisabled !== true;
    }

    static updateScriptData(bs, newData) {
        const newObj = merge(bs.gateway, newData);

        const isChanged = JSON.stringify(bs.gateway) !== JSON.stringify(newObj);

        bs.gateway = newObj;

        if (isChanged) {
            jQuery(document.body).trigger('ppcp_script_data_changed', [newObj]);
        }
    }
}
