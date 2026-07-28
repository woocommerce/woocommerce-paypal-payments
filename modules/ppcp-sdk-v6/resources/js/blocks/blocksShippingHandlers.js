/**
 * Popup shipping handlers for the WooCommerce Blocks express flow.
 *
 * Unlike the classic handlers (which post to the WC Store API directly),
 * these route through the Blocks data store so the React checkout UI stays
 * in sync. Each then patches the PayPal order with recalculated totals.
 * Failures throw so the rejected promise reaches the SDK and the popup
 * surfaces the problem to the buyer.
 *
 * @package
 */

import { updateShipping } from '../endpointsAdapter';
import { sdkShippingAddressToWc } from '../utils/sdkAddress';

/**
 * Builds the block-aware onShippingAddressChange / onShippingOptionsChange
 * handlers for a payment session.
 *
 * @param {Object} config       - The wc_ppcp_sdk_v6 config object.
 * @param {Object} shippingData - The Blocks shippingData prop.
 * @return {{onShippingAddressChange: (data: Object) => Promise<void>, onShippingOptionsChange: (data: Object) => Promise<void>}} Handlers.
 */
export function buildBlocksShippingHandlers( config, shippingData ) {
	return {
		onShippingAddressChange: async ( data ) => {
			const address = sdkShippingAddressToWc( data.shippingAddress );

			await wp.data
				.dispatch( 'wc/store/cart' )
				.updateCustomerData( { shipping_address: address } );

			if ( shippingData?.setShippingAddress ) {
				await shippingData.setShippingAddress( address );
			}

			await updateShipping( config, data.orderId );
		},

		onShippingOptionsChange: async ( data ) => {
			const rateId = data.selectedShippingOption?.id;

			if ( rateId ) {
				await wp.data
					.dispatch( 'wc/store/cart' )
					.selectShippingRate( rateId );

				if ( shippingData?.setSelectedRates ) {
					await shippingData.setSelectedRates( rateId );
				}
			}

			await updateShipping( config, data.orderId );
		},
	};
}
