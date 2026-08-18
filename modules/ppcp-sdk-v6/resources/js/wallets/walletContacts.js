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
 * Maps a wallet address to the PayPal address shape.
 *
 * Both wallets name every field the same way bar the street, which they are asked
 * for separately. Absent fields stay undefined so JSON.stringify drops them,
 * leaving the request body identical to v5's.
 *
 * @param {Object}   source       - A wallet address or contact.
 * @param {string[]} addressLines - Its street lines, most significant first.
 * @return {Object} The PayPal address.
 */
function paypalAddress( source, addressLines ) {
	return {
		country_code: source?.countryCode,
		address_line_1: addressLines?.[ 0 ],
		address_line_2: addressLines?.[ 1 ],
		admin_area_1: source?.administrativeArea,
		admin_area_2: source?.locality,
		postal_code: source?.postalCode,
	};
}

/**
 * Maps a Google Pay address to the PayPal address shape.
 *
 * @param {Object} data - A Google Pay address (billingAddress or shippingAddress).
 * @return {Object} The PayPal address.
 */
function googlePayAddress( data ) {
	return paypalAddress( data, [ data?.address1, data?.address2 ] );
}

/**
 * Derives the PayPal payer from a Google Pay payment response.
 *
 * @param {Object} response - The Google Pay payment response.
 * @return {Object} The PayPal payer.
 */
export function googlePayPayer( response ) {
	const billing = response?.paymentMethodData?.info?.billingAddress;
	// Defaulted rather than assumed present: a sheet that collects no billing
	// address must not take the payment down with a TypeError.
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

/**
 * Maps an Apple Pay contact to the PayPal address shape.
 *
 * @param {Object} contact - An Apple Pay contact (billingContact or shippingContact).
 * @return {Object} The PayPal address.
 */
function applePayAddress( contact ) {
	return paypalAddress( contact, contact?.addressLines );
}

/**
 * Joins an Apple contact's name parts, skipping whichever is absent.
 *
 * @param {Object} contact - An Apple Pay contact.
 * @return {string|undefined} The full name, or undefined when it has no parts.
 */
function applePayFullName( contact ) {
	const fullName = [ contact?.givenName, contact?.familyName ]
		.filter( Boolean )
		.join( ' ' );

	return fullName || undefined;
}

/**
 * Derives the PayPal payer from an authorized Apple Pay payment.
 *
 * Apple sends the name pre-split as givenName/familyName, so no splitting is
 * needed. It never returns a billing email or phone, so those come off the
 * shipping contact.
 *
 * @param {Object} payment - The ApplePayPayment from onpaymentauthorized.
 * @return {Object} The PayPal payer.
 */
export function applePayPayer( payment ) {
	const billing = payment?.billingContact;
	const shipping = payment?.shippingContact;

	return {
		email_address: shipping?.emailAddress,
		name: {
			given_name: billing?.givenName,
			surname: billing?.familyName,
		},
		address: applePayAddress( billing ),
	};
}

/**
 * Derives the PayPal shipping address from an authorized Apple Pay payment.
 *
 * Falls back to the billing contact: on classic checkout no postal address is
 * requested in the sheet, and a physical-goods order still needs one.
 *
 * @param {Object} payment - The ApplePayPayment from onpaymentauthorized.
 * @return {Object} The PayPal shipping address.
 */
export function applePayShippingAddress( payment ) {
	// Only the postal half falls back: a shippingContact that carries just an
	// email and phone (which is all the checkout sheet asks for) has no address
	// to ship to, so the billing one stands in.
	const shipping = payment?.shippingContact?.countryCode
		? payment.shippingContact
		: payment?.billingContact;

	return {
		name: {
			full_name: applePayFullName( shipping ),
		},
		address: applePayAddress( shipping ),
	};
}
