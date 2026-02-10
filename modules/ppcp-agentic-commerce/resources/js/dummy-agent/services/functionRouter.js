/**
 * Function Router - Maps Qwen 3 0.6B actions to API calls.
 *
 * This is the "private bridge" between the AI's suggestions and actual API execution.
 * The AI suggests actions in JSON format, and this router validates and executes them.
 */

/**
 * Execute an action from the LLM.
 *
 * @param {string} action - Action name (e.g., SEARCH_PRODUCTS, CREATE_CART)
 * @param {Object} parameters - Action parameters
 * @param {AgenticClient} client - API client instance
 * @param {Object} cart - Current cart state (optional)
 * @return {Promise<Object>} Action result with type and data
 */
export async function executeAction(action, parameters, client, cart) {
	switch (action) {
		case 'SEARCH_PRODUCTS':
			return {
				type: 'products',
				data: await client.searchProducts(parameters.query)
			};

		case 'CREATE_CART':
			return {
				type: 'cart',
				data: await client.createCart({
					items: parameters.items,
					customer: parameters.customer,
					payment_method: parameters.payment_method || { type: 'paypal' }
				}),
				approvalUrl: client.getApprovalUrl()
			};

		case 'UPDATE_CART':
			return {
				type: 'cart',
				data: await client.updateCart({
					items: parameters.items || cart?.items,
					shipping_address: parameters.shipping_address,
					billing_address: parameters.billing_address
				})
			};

		case 'GET_CART':
			return {
				type: 'cart',
				data: await client.getCart()
			};

		case 'CHECKOUT':
			return {
				type: 'checkout',
				data: await client.checkout({
					items: cart?.items || [],
					payment_method: parameters.payment_method || { type: 'paypal' }
				})
			};

		case 'CONVERSATION':
		default:
			return { type: 'conversation', data: null };
	}
}
