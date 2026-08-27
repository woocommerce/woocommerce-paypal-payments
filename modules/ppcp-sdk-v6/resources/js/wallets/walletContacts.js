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
 * Maps a wallet address to WC address fields, for pricing shipping.
 *
 * Only the four fields a shipping zone resolves from, which is all either sheet
 * exposes before authorization: Apple redacts the street. The Store API merges
 * partial addresses, so the omitted ones are not cleared.
 *
 * `administrativeArea` must land on `state`, or state-scoped zones stop matching.
 *
 * @param {Object} source - A wallet address or contact.
 * @return {Object} WC address fields.
 */
export function walletAddressToWc( source ) {
	return {
		country: source?.countryCode || '',
		state: source?.administrativeArea || '',
		postcode: source?.postalCode || '',
		city: source?.locality || '',
	};
}

/**
 * Maps an authorized wallet address to complete WC address fields.
 *
 * The street and the recipient are known only once the buyer authorizes. Writing
 * them completes the customer record, which is what PurchaseUnitFactory sends to
 * PayPal: an incomplete one is dropped (no street, no city or no postcode), and a
 * stale one would ship the order to whatever address WooCommerce already held.
 *
 * @param {Object}   source         - A wallet address or contact.
 * @param {string[]} addressLines   - Its street lines, most significant first.
 * @param {string}   [fullName]     - The recipient, as the wallet spells it.
 * @return {Object} WC address fields.
 */
function walletAddressToWcComplete( source, addressLines, fullName ) {
	const [ firstName, lastName ] = splitFullName( fullName ?? '' );

	return {
		...walletAddressToWc( source ),
		address_1: addressLines?.[ 0 ] || '',
		address_2: addressLines?.[ 1 ] || '',
		first_name: firstName,
		last_name: lastName,
	};
}

/**
 * The complete WC shipping address from a Google Pay payment response.
 *
 * @param {Object} response - The Google Pay payment response.
 * @return {Object} WC address fields.
 */
export function googlePayWcShippingAddress( response ) {
	const shipping = response?.shippingAddress;

	return walletAddressToWcComplete(
		shipping,
		[ shipping?.address1, shipping?.address2 ],
		shipping?.name
	);
}

/**
 * The complete WC billing address from a Google Pay payment response.
 *
 * The card's own billing address, which is what the order is created with. Known
 * only at authorization, so the sheet's own quotes cannot price against it: send
 * it with the final quote and the cart then taxes on the basis the order will.
 *
 * @param {Object} response - The Google Pay payment response.
 * @return {Object} WC address fields.
 */
export function googlePayWcBillingAddress( response ) {
	const billing = response?.paymentMethodData?.info?.billingAddress;

	return walletAddressToWcComplete(
		billing,
		[ billing?.address1, billing?.address2 ],
		billing?.name
	);
}

/**
 * The complete WC shipping address from an authorized Apple Pay payment.
 *
 * @param {Object} payment - The ApplePayPayment from onpaymentauthorized.
 * @return {Object} WC address fields.
 */
export function applePayWcShippingAddress( payment ) {
	const shipping = payment?.shippingContact;

	return walletAddressToWcComplete(
		shipping,
		shipping?.addressLines,
		applePayFullName( shipping )
	);
}

/**
 * The complete WC billing address from an authorized Apple Pay payment.
 *
 * @param {Object} payment - The ApplePayPayment from onpaymentauthorized.
 * @return {Object} WC address fields.
 */
export function applePayWcBillingAddress( payment ) {
	const billing = payment?.billingContact;

	return walletAddressToWcComplete(
		billing,
		billing?.addressLines,
		applePayFullName( billing )
	);
}

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
	// splitFullName() trims, so a sheet without a billing address must not reach
	// it undefined.
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
 * Falls back to the billing address: where the sheet collects no shipping it
 * returns no shippingAddress, and a physical-goods order still needs one.
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
 * Apple never returns a billing email or phone, so those come off the shipping
 * contact.
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
 * Falls back to the billing contact when the shipping one carries no postal
 * address, which is the classic checkout sheet: it asks only for an email and
 * phone, and a physical-goods order still needs somewhere to ship.
 *
 * @param {Object} payment - The ApplePayPayment from onpaymentauthorized.
 * @return {Object} The PayPal shipping address.
 */
export function applePayShippingAddress( payment ) {
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

/**
 * The opposite direction to everything above, for prefilling a sheet with an
 * address the shopper already gave the store.
 *
 * @param {Object} address - A WC address (Store API customer data shape).
 * @return {Object|undefined} The contact, or undefined without a country.
 */
export function wcAddressToApplePay( address ) {
	// Apple silently drops a contact it cannot resolve to a region.
	if ( ! address?.country ) {
		return undefined;
	}

	const contact = {
		countryCode: address.country,
		administrativeArea: address.state || '',
		postalCode: address.postcode || '',
		locality: address.city || '',
		addressLines: [ address.address_1, address.address_2 ].filter( Boolean ),
		givenName: address.first_name || '',
		familyName: address.last_name || '',
	};

	if ( address.email ) {
		contact.emailAddress = address.email;
	}
	if ( address.phone ) {
		contact.phoneNumber = address.phone;
	}

	return contact;
}
