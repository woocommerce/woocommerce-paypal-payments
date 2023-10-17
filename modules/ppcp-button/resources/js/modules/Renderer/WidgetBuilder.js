/**
 * Handles the registration and rendering of PayPal widgets: Buttons and Messages.
 * To have several Buttons per wrapper, an array should be provided, ex: [wrapper, fundingSource].
 */
class WidgetBuilder {

    constructor() {
        this.paypal = null;
        this.buttons = new Map();
        this.messages = new Map();

        this.renderEventName = 'ppcp-render';

        document.ppcpWidgetBuilderStatus = () => {
            console.log({
                buttons: this.buttons,
                messages: this.messages,
            });
        }

        jQuery(document)
            .off(this.renderEventName)
            .on(this.renderEventName, () => {
                this.renderAll();
            });
    }

    setPaypal(paypal) {
        this.paypal = paypal;
        jQuery(document).trigger('ppcp-paypal-loaded', paypal);
    }

    registerButtons(wrapper, options) {
        wrapper = this.sanitizeWrapper(wrapper);

        this.buttons.set(this.toKey(wrapper), {
            wrapper: wrapper,
            options: options,
        });
    }

    renderButtons(wrapper) {
        wrapper = this.sanitizeWrapper(wrapper);

        if (!this.buttons.has(this.toKey(wrapper))) {
            return;
        }

        if (this.hasRendered(wrapper)) {
            return;
        }

        const entry = this.buttons.get(this.toKey(wrapper));
        const btn = this.paypal.Buttons(entry.options);

        if (!btn.isEligible()) {
            this.buttons.delete(this.toKey(wrapper));
            return;
        }

        let target = this.buildWrapperTarget(wrapper);

        if (!target) {
            return;
        }

        btn.render(target);
    }

    renderAllButtons() {
        for (const [wrapper, entry] of this.buttons) {
            this.renderButtons(wrapper);
        }
    }

    registerMessages(wrapper, options) {
        this.messages.set(wrapper, {
            wrapper: wrapper,
            options: options
        });
    }

    renderMessages(wrapper) {
        if (!this.messages.has(wrapper)) {
            return;
        }

        const entry = this.messages.get(wrapper);

        if (this.hasRendered(wrapper)) {
            const element = document.querySelector(wrapper);
            element.setAttribute('data-pp-amount', entry.options.amount);
            return;
        }

        const btn = this.paypal.Messages(entry.options);

        btn.render(entry.wrapper);

        // watchdog to try to handle some strange cases where the wrapper may not be present
        setTimeout(() => {
            if (!this.hasRendered(wrapper)) {
                btn.render(entry.wrapper);
            }
        }, 100);
    }

    renderAllMessages() {
        for (const [wrapper, entry] of this.messages) {
            this.renderMessages(wrapper);
        }
    }

    renderAll() {
        this.renderAllButtons();
        this.renderAllMessages();
    }

    hasRendered(wrapper) {
        let selector = wrapper;

        if (Array.isArray(wrapper)) {
            selector = wrapper[0];
            for (const item of wrapper.slice(1)) {
                selector += ' .item-' + item;
            }
        }

        const element = document.querySelector(selector);
        return element && element.hasChildNodes();
    }

    sanitizeWrapper(wrapper) {
        if (Array.isArray(wrapper)) {
            wrapper = wrapper.filter(item => !!item);
            if (wrapper.length === 1) {
                wrapper = wrapper[0];
            }
        }
        return wrapper;
    }

    buildWrapperTarget(wrapper) {
        let target = wrapper;

        if (Array.isArray(wrapper)) {
            const $wrapper = jQuery(wrapper[0]);

            if (!$wrapper.length) {
                return;
            }

            const itemClass = 'item-' + wrapper[1];

            // Check if the parent element exists and it doesn't already have the div with the class
            let $item = $wrapper.find('.' + itemClass);

            if (!$item.length) {
                $item = jQuery(`<div class="${itemClass}"></div>`);
                $wrapper.append($item);
            }

            target = $item.get(0);
        }

        if (!jQuery(target).length) {
            return null;
        }

        return target;
    }

    toKey(wrapper) {
        if (Array.isArray(wrapper)) {
            return JSON.stringify(wrapper);
        }
        return wrapper;
    }
}

window.widgetBuilder = window.widgetBuilder || new WidgetBuilder();
export default window.widgetBuilder;
