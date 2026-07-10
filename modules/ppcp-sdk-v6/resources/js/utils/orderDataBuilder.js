/**
 * Builds the createOrder fetch call for v6 payment sessions.
 *
 * @package
 */

/**
 * Creates an order via the existing WC AJAX endpoint.
 * Returns a promise resolving to { orderId: string }.
 *
 * @param {Object} ajaxConfig          - The ajax.create_order config.
 * @param {string} ajaxConfig.endpoint - The endpoint URL.
 * @param {string} ajaxConfig.nonce    - The nonce.
 * @param {string} context             - The page context (product, cart, checkout).
 * @param {string} fundingSource       - The funding source (paypal, venmo, paylater).
 * @return {Promise<{orderId: string}>} A promise resolving to the order ID.
 */
export async function createOrder( ajaxConfig, context, fundingSource ) {
	const body = {
		nonce: ajaxConfig.nonce,
		purchase_units: [],
		payment_method: 'ppcp-gateway',
		funding_source: fundingSource || 'paypal',
		context,
	};

	// On product pages, include product form data.
	if ( context === 'product' ) {
		const form = document.querySelector( 'form.cart' );
		if ( form ) {
			const formData = new FormData( form );
			body.products = [
				{
					quantity: formData.get( 'quantity' ) || 1,
					product_id:
						formData.get( 'add-to-cart' ) ||
						formData.get( 'product_id' ) ||
						'',
					variation_id: formData.get( 'variation_id' ) || 0,
				},
			];
		}
	}

	const response = await fetch( ajaxConfig.endpoint, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify( body ),
	} );

	const data = await response.json();

	if ( ! data.success ) {
		throw new Error( data.data?.message || 'Failed to create order.' );
	}

	return { orderId: data.data.id };
}
