import {disable, enable} from "./ButtonDisabler";
import {hide, show} from "./Hiding";

/**
 * Common Bootstrap methods to avoid code repetition.
 */
export default class BootstrapHelper {

    static handleButtonStatus(bs, options) {
        options = options || {};
        options.wrapper = options.wrapper || bs.gateway.button.wrapper;
        options.messagesWrapper = options.messagesWrapper || bs.gateway.messages.wrapper;
        options.skipMessages = options.skipMessages || false;

        // Handle messages hide / show
        if (this.shouldShowMessages(bs, options)) {
            show(options.messagesWrapper);
        } else {
            hide(options.messagesWrapper);
        }

        // Handle enable / disable
        if (bs.shouldEnable()) {
            bs.renderer.enableSmartButtons(options.wrapper);
            enable(options.wrapper);

            if (!options.skipMessages) {
                enable(options.messagesWrapper);
            }
        } else {
            bs.renderer.disableSmartButtons(options.wrapper);
            disable(options.wrapper, options.formSelector || null);

            if (!options.skipMessages) {
                disable(options.messagesWrapper);
            }
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

    static shouldShowMessages(bs, options) {
        options = options || {};
        if (typeof options.isMessagesHidden === 'undefined') {
            options.isMessagesHidden = bs.gateway.messages.is_hidden;
        }

        return options.isMessagesHidden !== true;
    }
}
