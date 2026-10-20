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

	/**
	 * Whether PayPal's insights script has loaded and installed its global.
	 *
	 * Analytics must never break checkout, and the script is loaded async: a
	 * caller can reach these methods before it arrives, which used to throw a
	 * ReferenceError out of the AXO bootstrap.
	 *
	 * @return {boolean} True when the global is callable.
	 */
	static isAvailable() {
		return typeof window.paypalInsight === 'function';
	}

	static track( eventName, data ) {
		PayPalInsights.init();
		if ( ! PayPalInsights.isAvailable() ) {
			return;
		}
		window.paypalInsight( 'event', eventName, data );
	}

	static config( clientId, data ) {
		PayPalInsights.init();
		if ( ! PayPalInsights.isAvailable() ) {
			return;
		}
		window.paypalInsight( 'config', clientId, data );
	}

	static setSessionId( sessionId ) {
		PayPalInsights.init();
		if ( ! PayPalInsights.isAvailable() ) {
			return;
		}
		window.paypalInsight( 'set', { session_id: sessionId } );
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
