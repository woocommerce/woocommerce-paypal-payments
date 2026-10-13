export const isChangePaymentPage = () => {
	const urlParams = new URLSearchParams( window.location.search );
	return urlParams.has( 'change_payment_method' );
};

export const getPlanIdFromVariation = ( variation ) => {
	let subscription_plan = '';
	PayPalCommerceGateway.variable_paypal_subscription_variations.forEach(
		( element ) => {
			const obj = {};
			variation.forEach( ( { name, value } ) => {
				Object.assign( obj, {
					[ name.replace( 'attribute_', '' ) ]: value,
				} );
			} );

			if (
				JSON.stringify( obj ) ===
					JSON.stringify( element.attributes ) &&
				element.subscription_plan !== ''
			) {
				subscription_plan = element.subscription_plan;
			}
		}
	);

	return subscription_plan;
};

/**
 * Where to send the shopper once a subscription has been approved.
 *
 * The approve endpoint returns an order received URL only when it created and
 * paid the order itself. A failed response carries no URL either, so it is told
 * apart from that rather than quietly sending the shopper to checkout as though
 * the subscription were waiting for them there.
 *
 * @param {Object}   response         - The approve endpoint response.
 * @param {Object}   errorHandler     - Reports the failure to the shopper.
 * @param {string}   fallbackRedirect - Used when the endpoint named no URL.
 * @return {string} The URL to navigate to.
 * @throws {Error} When the endpoint reported a failure.
 */
export const approvalRedirectUrl = (
	response,
	errorHandler,
	fallbackRedirect
) => {
	if ( ! response.success ) {
		const message = response.data?.message;

		errorHandler.clear();

		if ( message ) {
			errorHandler.message( message );
		} else {
			errorHandler.genericError();
		}

		throw Error( message );
	}

	return response.data?.order_received_url || fallbackRedirect;
};
