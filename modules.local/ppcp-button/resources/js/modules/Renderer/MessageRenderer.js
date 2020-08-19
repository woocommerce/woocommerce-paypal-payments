class MessageRenderer {

    constructor(config) {
        this.config = config;
    }

    render() {
        if (! this.shouldRender()) {
            return;
        }

        paypal.Messages({
            amount: this.config.amount,
            placement: this.config.placement,
            style: this.config.style
        }).render(this.config.wrapper);
    }

    renderWithAmount(amount) {

        if (! this.shouldRender()) {
            return;
        }

        console.log(amount);
        paypal.Messages({
            amount,
            placement: this.config.placement,
            style: this.config.style
        }).render(this.config.wrapper);
    }

    shouldRender() {

        if (typeof paypal.Messages === 'undefined' || typeof this.config.wrapper === 'undefined' ) {
            return false;
        }
        if (! document.querySelector(this.config.wrapper)) {
            return false;
        }
        return true;
    }
}
export default MessageRenderer;