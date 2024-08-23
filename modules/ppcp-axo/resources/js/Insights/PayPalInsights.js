class PayPalInsights {
	constructor() {
		window.paypalInsightDataLayer = window.paypalInsightDataLayer || [];
		document.paypalInsight = () => {
			paypalInsightDataLayer.push( arguments );
		};
	}

	/**
	 * @return {PayPalInsights}
	 */
	static init() {
		if ( ! PayPalInsights.instance ) {
			PayPalInsights.instance = new PayPalInsights();
		}
		return PayPalInsights.instance;
	}

	static track( eventName, data ) {
		PayPalInsights.init();
		paypalInsight( 'event', eventName, data );
	}

	static config( clientId, data ) {
		PayPalInsights.init();
		paypalInsight( 'config', clientId, data );
	}

	static setSessionId( sessionId ) {
		PayPalInsights.init();
		paypalInsight( 'set', { session_id: sessionId } );
	}

	static trackJsLoad() {
		PayPalInsights.track( 'js_load', { timestamp: Date.now() } );
	}

	static trackBeginCheckout( data ) {
		PayPalInsights.track( 'begin_checkout', data );
	}

	static trackSubmitCheckoutEmail( data ) {
		PayPalInsights.track( 'submit_checkout_email', data );
	}

	static trackSelectPaymentMethod( data ) {
		PayPalInsights.track( 'select_payment_method', data );
	}

	static trackEndCheckout( data ) {
		PayPalInsights.track( 'end_checkout', data );
	}
}

export default PayPalInsights;
