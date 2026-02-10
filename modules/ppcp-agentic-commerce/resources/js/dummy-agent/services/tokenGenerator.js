/**
 * Generates sandbox-compatible JWT tokens for testing the agentic commerce API.
 *
 * The SandboxAuthService only validates:
 * - Valid JWT structure (header.payload.signature)
 * - iss: "paypal.com" in payload
 *
 * This allows us to test the API without requiring real PayPal credentials.
 */

/**
 * Generate a sandbox JWT token.
 *
 * @param {string} merchantId - Optional merchant ID (defaults to SANDBOX_MERCHANT)
 * @return {string} Base64-encoded JWT token
 */
export function generateSandboxToken(merchantId = 'SANDBOX_MERCHANT') {
	const header = {
		alg: 'HS256',
		typ: 'JWT'
	};

	const payload = {
		iss: 'paypal.com',
		scope: 'cart checkout',
		merchant_id: merchantId,
		iat: Math.floor(Date.now() / 1000),
		exp: Math.floor(Date.now() / 1000) + 3600 // 1 hour expiry
	};

	// Base64 encode without padding
	const encode = (obj) => btoa(JSON.stringify(obj)).replace(/=/g, '');

	return `${encode(header)}.${encode(payload)}.sandbox-signature`;
}
