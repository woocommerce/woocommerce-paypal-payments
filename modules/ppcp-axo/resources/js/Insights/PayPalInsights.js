
class PayPalInsights {

    constructor() {
        window.paypalInsightDataLayer = window.paypalInsightDataLayer || [];
        document.paypalInsight = () => {
            paypalInsightDataLayer.push(arguments);
        }
    }

    /**
     * @returns {PayPalInsights}
     */
    static getInstance() {
        if (!PayPalInsights.instance) {
            PayPalInsights.instance = new PayPalInsights();
        }
        return PayPalInsights.instance;
    }

    track(eventName, data) {
        paypalInsight('event', eventName, data);
    }

    static config (clientId, data) {
        paypalInsight('config', clientId, data);
    }

    static setSessionId (sessionId) {
        paypalInsight('set', { session_id: sessionId });
    }

    static trackJsLoad () {
        PayPalInsights.getInstance().track('js_load', { timestamp: Date.now() });
    }

    static trackBeginCheckout (data) {
        PayPalInsights.getInstance().track('begin_checkout', data);
    }

    static trackSubmitCheckoutEmail (data) {
        PayPalInsights.getInstance().track('submit_checkout_email', data);
    }

    static trackSelectPaymentMethod (data) {
        PayPalInsights.getInstance().track('select_payment_method', data);
    }

    static trackEndCheckout (data) {
        PayPalInsights.getInstance().track('end_checkout', data);
    }

}

export default PayPalInsights;
