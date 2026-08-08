/**
 * Address conversion for the v6 SDK's shipping callbacks.
 *
 * @package
 */

/**
 * Converts a PayPal v6 SDK callback address to WC address fields.
 *
 * Distinct from blocks/address.js, which maps the snake_case Orders v2 shape
 * returned by ppc-get-order; this one takes the camelCase shape the v6 SDK
 * passes to onShippingAddressChange. Shared by the classic and the blocks
 * shipping handlers.
 *
 * @param {Object} address - The v6 onShippingAddressChange address.
 * @return {Object} WC address fields.
 */
export function sdkShippingAddressToWc( address = {} ) {
	return {
		country: address.countryCode || '',
		// v6 uses Orders-v2 naming: adminArea1 = state, adminArea2 = city.
		state: address.adminArea1 || address.state || '',
		postcode: address.postalCode || '',
		city: address.adminArea2 || address.city || '',
	};
}
