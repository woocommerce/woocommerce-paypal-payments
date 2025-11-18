# JWT Authentication Flow

## Overview

The Agentic Commerce API uses JWT (JSON Web Tokens) for authenticating requests from PayPal's
Commerce Platform. This document explains how JWT authentication works in our implementation.

## What is a JWT?

A JWT is a compact, URL-safe token consisting of three Base64-encoded parts separated by dots (`.`):

```
<header>.<payload>.<signature>
```

- **Header** - Token metadata (algorithm, type)
- **Payload** - Claims/data (base64-decodable)
- **Signature** - Cryptographic signature verifying token authenticity

## Authentication Flow

```
1. Request arrives via the HTTP `Authorization` header
   ↓
2. AgenticRestEndpoint.check_permission() extracts token
   ↓
3. JwtAuthService.get_token() parses and validates token
   - Extracts JWT from "Bearer {token}" format
   - Decodes using PayPal's public keys (via PayPalJwkProvider)
   - Verifies signature, expiration, and validity
   ↓
4. JwtAuthService.verify_claims() validates business rules
   - Verifies issuer is "paypal.com"
   - Checks required scopes are present
   - Verifies merchant ID matches configured merchant
   ↓
5. Request proceeds if authentication successful
```

## Token Payload Structure

PayPal tokens contain these standard JWT claims:

```json
{
  "iss": "paypal.com",              // Issuer (verified by verify_claims)
  "sub": "woo_syde_merchant_id",    // Subject (ignored)
  "aud": "woocommerce.com",         // Audience (ignored)
  "scope": ["cart","checkout"],     // Permission scopes (verified by verify_claims)
  "external_id": ["PayPal:ABC123"], // PayPal merchant ID (verified by verify_claims)
  "iat": 1761153926,                // Issued at (verified by JWT::decode)
  "exp": 1763745926                 // Expires at (verified by JWT::decode)
}
```

## Verification Steps

### Automatic Verification (JWT::decode)

These are handled automatically by the Firebase JWT library:

- **Signature** - Cryptographically verified using PayPal's public keys
- **Expiration (`exp`)** - Token must not be expired
- **Not Before (`iat`)** - Token must be valid at current time

### Business Rule Verification (verify_claims)

Additional checks performed by our code:

- **Issuer (`iss`)** - Must be exactly "paypal.com"
- **Scopes (`scope`)** - Must contain all required permissions for the endpoint
- **Merchant ID (`external_id`)** - Must match the PayPal merchant ID configured on this WooCommerce store

## Implementation Classes

### JwtAuthService

Handles token parsing and validation:

```php
// Parse and decode token
$token = $auth_service->get_token( $bearer_token );

// Verify claims
$result = $auth_service->verify_claims( $token, ['cart'] );
```

### AgenticRestEndpoint

Base class for all agentic endpoints. Orchestrates authentication in `check_permission()`:

```php
public function check_permission( WP_REST_Request $request ) {
    $token = $request->get_header( 'Authorization' );
    $context = $this->auth_service->get_token( $token );
    
    if ( is_wp_error( $context ) ) {
        return $context; // 401/503 error
    }
    
    return $this->auth_service->verify_claims( $context, static::REQUIRED_SCOPES );
}
```

### Endpoint Scope Requirements

Each endpoint can specify required scopes via a constant - the default scope is `array( 'cart' )`:

```php
class CreateCartEndpoint extends AgenticRestEndpoint {
    protected const REQUIRED_SCOPES = array( 'cart' );
}

class CheckoutEndpoint extends AgenticRestEndpoint {
    protected const REQUIRED_SCOPES = array( 'cart', 'checkout' );
}
```

## Error Responses

| Error Code                | HTTP Status | Description                                |
|---------------------------|-------------|--------------------------------------------|
| `missing_token`           | 401         | Authorization header missing or empty      |
| `invalid_jwt`             | 401         | Token format invalid or signature mismatch |
| `invalid_issuer`          | 401         | Token not issued by PayPal                 |
| `invalid_token`           | 401         | Token claims malformed                     |
| `insufficient_scope`      | 403         | Token lacks required permissions           |
| `merchant_mismatch`       | 403         | Token not valid for this merchant          |
| `merchant_not_configured` | 500         | Merchant ID not configured on store        |
| `key_unavailable`         | 503         | Could not retrieve PayPal public keys      |

## Local Development

The relaxed authentication service `SandboxAuthService` is a drop-in replacement to unlock REST
endpoints on a local or dev environment without providing a cryptographically valid JWT token.

This drop-in is automatically enabled when connected using a PayPal sandbox merchant.

To simulate real production authentication as a sandbox merchant, the following flag can be used in
`wp-config.php`:

```php
define( 'PPCP_AGENTIC_FULL_AUTH', true );
```

### Relaxed Rules

The sandbox authentication mainly checks if the Authorization header and the JWT payload have a
valid structure, and uses the expected issuer string.

| Feature                     | SandboxAuthService         | JwtAuthService                        |
|-----------------------------|----------------------------|---------------------------------------|
| **Header format**           | ✅ Required (Bearer prefix) | ✅ Required (Bearer prefix)            |
| **JWT structure**           | ✅ Must have 3 parts        | ✅ Must have 3 parts                   |
| **Valid JSON in payload**   | ✅ Must be parseable        | ✅ Must be parseable                   |
| **Cryptographic signature** | ❌ Not verified             | ✅ Verified against PayPal public keys |
| **Token expiration (exp)**  | ❌ Not checked              | ✅ Must not be expired                 |
| **Issues-at time (iat)**    | ❌ Not checked              | ✅ Must be past not-before time        |
| **Issuer claim (iss)**      | ✅ Must be `paypal.com`     | ✅ Must be `paypal.com`                |
| **Required scopes**         | ❌ Not checked              | ✅ Must include endpoint scopes        |
| **Merchant ID match**       | ❌ Not checked              | ✅ Must match configured merchant      |

**Token acceptance scenarios:**

| Token Type           | SandboxAuthService | JwtAuthService                  |
|----------------------|--------------------|---------------------------------|
| Valid PayPal token   | ✅ Accepted         | ✅ Accepted                      |
| Expired PayPal token | ✅ Accepted         | ❌ Rejected (expired)            |
| Wrong merchant ID    | ✅ Accepted         | ❌ Rejected (merchant mismatch)  |
| Invalid signature    | ✅ Accepted         | ❌ Rejected (invalid signature)  |
| Missing scopes       | ✅ Accepted         | ❌ Rejected (insufficient scope) |
| Wrong issuer         | ❌ Rejected         | ❌ Rejected                      |
| Malformed JSON       | ❌ Rejected         | ❌ Rejected                      |
| Missing "Bearer"     | ❌ Rejected         | ❌ Rejected                      |

### Security Notes

- Production environments **always** use full authentication
- Sandbox mode cannot process real payments
- Relaxed auth only affects development workflows
