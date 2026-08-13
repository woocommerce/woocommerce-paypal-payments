/**
 * Maps wallet payment responses to the PayPal payer and shipping shapes that
 * ppc-approve-order expects.
 *
 * The output is Orders v2 snake_case, not WC fields, because the endpoint feeds
 * `payer` and `shipping_address` straight into PayerFactory / ShippingFactory
 * (WooCommerceOrderCreator::get_payer(), ::get_shipping()). Hence neither
 * existing helper fits: blocks/address.js maps the opposite way, into WC fields,
 * and utils/sdkAddress.js reads adminArea1/adminArea2, which Google never sends.
 *
 * @package
 */

import { splitFullName } from '../utils/name';

/**
 * Maps a Google Pay address to the PayPal address shape.
 *
 * Absent fields stay undefined so JSON.stringify drops them, leaving the
 * request body identical to v5's.
 *
 * @param {Object} data - A Google Pay address (billingAddress or shippingAddress).
 * @return {Object} The PayPal address.
 */
function googlePayAddress( data ) {
	return {
		country_code: data?.countryCode,
		address_line_1: data?.address1,
		address_line_2: data?.address2,
		admin_area_1: data?.administrativeArea,
		admin_area_2: data?.locality,
		postal_code: data?.postalCode,
	};
}

/**
 * Derives the PayPal payer from a Google Pay payment response.
 *
 * @param {Object} response - The Google Pay payment response.
 * @return {Object} The PayPal payer.
 */
export function googlePayPayer( response ) {
	const billing = response?.paymentMethodData?.info?.billingAddress;
	// v5 threw here when billingAddress was absent.
	const [ givenName, surname ] = splitFullName( billing?.name ?? '' );

	return {
		email_address: response?.email,
		name: {
			given_name: givenName,
			surname,
		},
		address: googlePayAddress( billing ),
	};
}

/**
 * Derives the PayPal shipping address from a Google Pay payment response.
 *
 * Falls back to the billing address: with the shipping callbacks disabled the
 * sheet returns no shippingAddress, and a physical-goods order still needs one.
 *
 * @param {Object} response - The Google Pay payment response.
 * @return {Object} The PayPal shipping address.
 */
export function googlePayShippingAddress( response ) {
	const shipping =
		response?.shippingAddress ??
		response?.paymentMethodData?.info?.billingAddress;

	return {
		name: {
			full_name: shipping?.name,
		},
		address: googlePayAddress( shipping ),
	};
}
