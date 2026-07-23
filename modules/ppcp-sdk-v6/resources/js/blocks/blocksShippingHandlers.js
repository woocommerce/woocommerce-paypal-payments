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

/**
 * Converts a PayPal v6 shipping address to WC customer-data fields.
 *
 * @param {Object} address - The v6 onShippingAddressChange address.
 * @return {Object} WC address fields.
 */
function paypalAddressToWc( address = {} ) {
	return {
		country: address.countryCode || '',
		// v6 uses Orders-v2 naming: adminArea1 = state, adminArea2 = city.
		state: address.adminArea1 || address.state || '',
		postcode: address.postalCode || '',
		city: address.adminArea2 || address.city || '',
	};
}

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
			const address = paypalAddressToWc( data.shippingAddress );

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
