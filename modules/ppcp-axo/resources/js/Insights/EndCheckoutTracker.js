import PayPalInsights from '@ppcp-axo/Insights/PayPalInsights';

class EndCheckoutTracker {
	constructor() {
		this.initialize();
	}

	async initialize() {
		const axoConfig = window.wc_ppcp_axo_insights_data || {};

		if (
			axoConfig?.enabled === '1' &&
			axoConfig?.client_id &&
			axoConfig?.session_id &&
			axoConfig?.orderTotal &&
			axoConfig?.orderCurrency
		) {
			try {
				await this.waitForPayPalInsight();

				PayPalInsights.config( axoConfig?.client_id, {
					debug: axoConfig?.wp_debug === '1',
				} );
				PayPalInsights.setSessionId( axoConfig.session_id );
				PayPalInsights.trackJsLoad();

				const trackingData = {
					amount: {
						currency_code: axoConfig?.orderCurrency,
						value: axoConfig?.orderTotal,
					},
					page_type: 'checkout',
					payment_method_selected:
						axoConfig?.payment_method_selected_map[
							axoConfig?.paymentMethod
						] || 'other',
					user_data: {
						country: 'US',
						is_store_member: false,
					},
					order_id: axoConfig?.orderId,
				};

				PayPalInsights.trackEndCheckout( trackingData );
			} catch ( error ) {
				console.error(
					'EndCheckoutTracker: Error during tracking:',
					error
				);
				console.error( 'PayPalInsights object:', window.paypalInsight );
			}
		} else {
			console.warn(
				'EndCheckoutTracker: Missing required configuration',
				{
					enabled: axoConfig?.enabled,
					hasClientId: !! axoConfig?.client_id,
					hasSessionId: !! axoConfig?.session_id,
					hasOrderTotal: !! axoConfig?.orderTotal,
					hasOrderCurrency: !! axoConfig?.orderCurrency,
				}
			);
		}
	}

	waitForPayPalInsight() {
		return new Promise( ( resolve, reject ) => {
			// If already loaded, resolve immediately
			if ( window.paypalInsight ) {
				resolve( window.paypalInsight );
				return;
			}

			const timeoutId = setTimeout( () => {
				observer.disconnect();
				reject( new Error( 'PayPal Insights script load timeout' ) );
			}, 10000 );

			// Create MutationObserver to watch for script initialization
			const observer = new MutationObserver( () => {
				if ( window.paypalInsight ) {
					observer.disconnect();
					clearTimeout( timeoutId );
					resolve( window.paypalInsight );
				}
			} );

			observer.observe( document, {
				childList: true,
				subtree: true,
			} );
		} );
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	new EndCheckoutTracker();
} );
