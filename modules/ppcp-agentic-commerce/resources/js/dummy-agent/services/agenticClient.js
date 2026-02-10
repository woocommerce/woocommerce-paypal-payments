/**
 * API client for the PayPal Agentic Commerce API.
 *
 * CRITICAL: Uses credentials: 'omit' for agentic API calls to prevent
 * mixing WordPress admin session with test carts.
 */

import apiFetch from '@wordpress/api-fetch';
import { generateSandboxToken } from './tokenGenerator';

class AgenticClient {
	/**
	 * Create a new API client.
	 *
	 * @param {string} agenticUrl - Base URL for agentic API (e.g., /wp-json/wc/v3/agentic)
	 * @param {string} productsUrl - Base URL for products API (e.g., /wp-json/wc/v3/products)
	 */
	constructor(agenticUrl, productsUrl) {
		this.agenticUrl = agenticUrl;
		this.productsUrl = productsUrl;
		this.cartId = null;
		this.ecToken = null;
		this.token = generateSandboxToken();
	}

	/**
	 * Search products via WooCommerce REST API.
	 * Uses credentials: include (needs WP nonce for admin access).
	 *
	 * @param {string} query - Search query
	 * @return {Promise<Array>} Product list
	 */
	async searchProducts(query) {
		return apiFetch({
			path: `/wc/v3/products?search=${encodeURIComponent(query)}&per_page=10&status=publish`,
		});
	}

	/**
	 * Create a new cart.
	 * Uses credentials: omit (isolated from admin session).
	 *
	 * @param {Object} cartData - Cart data matching PayPalCart schema
	 * @return {Promise<Object>} Cart response
	 */
	async createCart(cartData) {
		const response = await this.agenticRequest('POST', '/merchant-cart', cartData);
		if (response.id) {
			this.cartId = response.id;
			this.ecToken = response.payment_method?.token || null;
		}
		return response;
	}

	/**
	 * Get current cart details.
	 *
	 * @return {Promise<Object>} Cart response
	 */
	async getCart() {
		if (!this.cartId) {
			throw new Error('No cart created');
		}
		return this.agenticRequest('GET', `/merchant-cart/${this.cartId}`);
	}

	/**
	 * Update cart (PUT replaces entire cart).
	 *
	 * @param {Object} cartData - Complete cart data
	 * @return {Promise<Object>} Updated cart response
	 */
	async updateCart(cartData) {
		if (!this.cartId) {
			throw new Error('No cart created');
		}
		const response = await this.agenticRequest('PUT', `/merchant-cart/${this.cartId}`, cartData);
		// A new PayPal order may have been created if items changed — update the token.
		if (response.payment_method?.token) {
			this.ecToken = response.payment_method.token;
		}
		return response;
	}

	/**
	 * Complete checkout.
	 *
	 * @param {Object} checkoutData - Checkout data with payment_method
	 * @return {Promise<Object>} Checkout response
	 */
	async checkout(checkoutData) {
		if (!this.cartId) {
			throw new Error('No cart created');
		}
		return this.agenticRequest('POST', `/merchant-cart/${this.cartId}/checkout`, checkoutData);
	}

	/**
	 * Get PayPal approval URL for the current cart.
	 *
	 * @return {string|null} Approval URL or null if no token
	 */
	getApprovalUrl() {
		if (!this.ecToken) {
			return null;
		}
		// Sandbox approval URL format
		return `https://www.sandbox.paypal.com/checkoutnow?token=${this.ecToken}`;
	}

	/**
	 * Make an agentic API request.
	 * CRITICAL: Uses credentials: 'omit' to prevent admin session mixing.
	 *
	 * @param {string} method - HTTP method
	 * @param {string} path - API path
	 * @param {Object} data - Request body (optional)
	 * @return {Promise<Object>} Response data
	 */
	async agenticRequest(method, path, data) {
		const response = await fetch(`${this.agenticUrl}${path}`, {
			method,
			credentials: 'omit', // CRITICAL: Don't send admin cookies
			headers: {
				'Content-Type': 'application/json',
				'Authorization': `Bearer ${this.token}`
			},
			body: data ? JSON.stringify(data) : undefined
		});

		if (!response.ok) {
			let errorMessage = `Request failed: ${response.status}`;
			try {
				const error = await response.json();
				errorMessage = error.message || errorMessage;
			} catch (e) {
				// Could not parse error response
			}
			throw new Error(errorMessage);
		}

		return response.json();
	}

	/**
	 * Reset client state (clear cart ID and token).
	 */
	reset() {
		this.cartId = null;
		this.ecToken = null;
	}
}

export default AgenticClient;
