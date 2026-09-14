/**
 * Handles shipping address and option changes inside the PayPal popup.
 *
 * Failures are thrown (not swallowed) so the rejected promise reaches
 * the SDK and the popup can surface the problem to the buyer.
 *
 * @package
 */

import {
	selectShippingRate,
	updateCustomerAddress,
	updateShipping,
} from '../endpointsAdapter';
import { sdkShippingAddressToWc } from '../utils/sdkAddress';

/**
 * Updates the WC customer shipping address via the Store API,
 * then patches the PayPal order with recalculated totals.
 *
 * The Store API merges partial addresses server-side, so the new
 * fields are posted directly without fetching the cart first.
 *
 * @param {Object} data   - The v6 onShippingAddressChange data.
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
export async function handleShippingAddressChange( data, config ) {
	await updateCustomerAddress(
		config,
		sdkShippingAddressToWc( data.shippingAddress )
	);

	await updateShipping( config, data.orderId );
}

/**
 * Updates the selected shipping option in WC via the Store API,
 * then patches the PayPal order with recalculated totals.
 *
 * @param {Object} data   - The v6 onShippingOptionsChange data.
 * @param {Object} config - The wc_ppcp_sdk_v6 config object.
 */
export async function handleShippingOptionsChange( data, config ) {
	const rateId = data.selectedShippingOption?.id;

	if ( rateId ) {
		await selectShippingRate( config, rateId );
	}

	await updateShipping( config, data.orderId );
}
