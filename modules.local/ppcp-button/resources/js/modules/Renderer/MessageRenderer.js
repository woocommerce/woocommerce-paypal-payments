class MessageRenderer {

    constructor(config) {
        this.config = config;
    }

    render() {
        if (typeof paypal.Messages === 'undefined' || typeof this.config.wrapper === 'undefined' ) {
            return;
        }
        if (! document.querySelector(this.config.wrapper)) {
            return;
        }

        paypal.Messages({
            amount: this.config.amount,
            placement: this.config.placement,
            style: this.config.style
        }).render(this.config.wrapper);
    }
}
export default MessageRenderer;