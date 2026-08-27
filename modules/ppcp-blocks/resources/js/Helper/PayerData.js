/**
 * The payer details the block checkout can supply when creating a PayPal order.
 *
 * PayPal's risk engine identifies a buyer partly by their email address. Without
 * one, a card it has never seen has nothing to be matched against, and the first
 * attempts on a brand-new card are declined until a fingerprint builds up. The
 * classic checkout has always sent this along with the serialised form; the block
 * checkout sent nothing, so the same shopper fared worse there.
 *
 * @package
 */

/**
 * The shopper's billing details from the cart store.
 *
 * Read through wp.data rather than a React hook, so the plain create-order
 * functions can use it too.
 *
 * @return {?Object} The billing address, or null when the store is unavailable.
 */
function billingAddress() {
	const cart = window.wp?.data?.select?.( 'wc/store/cart' );

	return cart?.getCustomerData?.()?.billingAddress ?? null;
}

/**
 * Builds the payer object for a create-order request.
 *
 * Returns null unless an email is known: PayPal is told nothing rather than told
 * something empty. Payer::to_array() always emits the name, so a payer carrying
 * only an email would send two empty strings and risk the whole order being
 * rejected, which is worse than the missing signal this exists to provide.
 *
 * @return {?Object} The payer, or null when there is nothing worth sending.
 */
export function payerData() {
	const billing = billingAddress();
	const email = billing?.email?.trim();

	if ( ! email ) {
		return null;
	}

	const payer = {
		email_address: email,
		name: {
			given_name: billing.first_name || '',
			surname: billing.last_name || '',
		},
	};

	const phone = ( billing.phone || '' )
		.replace( /[^0-9]/g, '' )
		.slice( 0, 14 );
	if ( phone ) {
		payer.phone = {
			phone_type: 'HOME',
			phone_number: { national_number: phone },
		};
	}

	if ( billing.country ) {
		payer.address = {
			country_code: billing.country,
			address_line_1: billing.address_1 || '',
			address_line_2: billing.address_2 || '',
			admin_area_1: billing.state || '',
			admin_area_2: billing.city || '',
			postal_code: billing.postcode || '',
		};
	}

	return payer;
}
