
class WidgetBuilder {

    constructor() {
        this.paypal = null;
        this.buttons = new Map();
        this.messages = new Map();

        document.ppcpWidgetBuilderStatus = () => {
            console.log({
                buttons: this.buttons,
                messages: this.messages,
            });
        }
    }

    setPaypal(paypal) {
        this.paypal = paypal;
    }

    registerButtons(wrapper, options) {
        this.buttons.set(wrapper, {
            wrapper: wrapper,
            options: options
        });
    }

    renderButtons(wrapper) {
        if (!this.buttons.has(wrapper)) {
            return;
        }

        if (this.hasRendered(wrapper)) {
            return;
        }

        const entry = this.buttons.get(wrapper);
        const btn = this.paypal.Buttons(entry.options);

        if (!btn.isEligible()) {
            return;
        }

        btn.render(entry.wrapper);
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

        if (this.hasRendered(wrapper)) {
            return;
        }

        const entry = this.messages.get(wrapper);
        const btn = this.paypal.Messages(entry.options);

        btn.render(entry.wrapper);
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
        return document.querySelector(wrapper).hasChildNodes();
    }
}

export default new WidgetBuilder();
