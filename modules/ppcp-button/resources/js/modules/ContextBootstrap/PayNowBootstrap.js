import CheckoutBootstap from './CheckoutBootstap'

class PayNowBootstrap extends CheckoutBootstap {
    constructor(gateway, renderer, messages, spinner) {
        super(gateway, renderer, messages, spinner)
    }

    updateUi() {
        const urlParams = new URLSearchParams(window.location.search)
        if (urlParams.has('change_payment_method')) {
            return
        }

        super.updateUi();
    }
}

export default PayNowBootstrap;
