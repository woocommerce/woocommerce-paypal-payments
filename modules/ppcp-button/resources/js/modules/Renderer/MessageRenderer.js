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

        jQuery(document.body).on('updated_cart_totals', () => {
            paypal.Messages({
                amount: this.config.amount,
                placement: this.config.placement,
                style: this.config.style
            }).render(this.config.wrapper);
        });
    }

    renderWithAmount(amount) {

        if (! this.shouldRender()) {
            return;
        }

        const newWrapper = document.createElement('div');
        newWrapper.setAttribute('id', this.config.wrapper.replace('#', ''));

        const sibling = document.querySelector(this.config.wrapper).nextSibling;
        document.querySelector(this.config.wrapper).parentElement.removeChild(document.querySelector(this.config.wrapper));
        sibling.parentElement.insertBefore(newWrapper, sibling);
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
