# REST endpoints (Cart API)

PayPal agents drive a cart through four endpoints. PayPal owns the schema, so this page covers the routes, what the store puts in a response, and how failures come back.

## Endpoints

All routes live under the `wc/v3/agentic` namespace.

| Method | Route                              | Purpose                              |
|--------|------------------------------------|--------------------------------------|
| `POST` | `merchant-cart`                    | Create a cart and a PayPal order     |
| `GET`  | `merchant-cart/{cart_id}`          | Read the stored cart                 |
| `PUT`  | `merchant-cart/{cart_id}`          | Replace the cart contents            |
| `POST` | `merchant-cart/{cart_id}/checkout` | Pay and create the WooCommerce order |

`cart_id` accepts letters, digits, underscores, and hyphens.

Requests do not run in a normal customer session. The module builds a throwaway cart session per request, so anything relying on the current customer or a persisted cart behaves differently here than on the storefront.

## Authentication

Every request needs a JWT in the `Authorization` header, and every endpoint requires the same `cart` scope. See [JWT authentication](authentication-via-jwt.md).

## Response shape

`CartResponse::to_array()` assembles the body. Keys that resolve to `null` are dropped, so a response only carries what applies.

| Key                          | Contents                                                             |
|------------------------------|----------------------------------------------------------------------|
| `id`                         | The cart ID                                                          |
| `status`                     | Cart lifecycle, see below                                            |
| `validation_status`          | `VALID` or `INVALID`                                                 |
| `validation_issues`          | Everything the validators reported                                   |
| `items`                      | Cart items with store-authoritative prices                           |
| `customer`                   | Customer data the agent supplied                                     |
| `shipping_address`           | Shipping address, when one was given                                 |
| `billing_address`            | Billing address, when one was given                                  |
| `available_shipping_options` | Shipping choices for the address                                     |
| `totals`                     | `subtotal`, `shipping`, `tax`, `total`, and `discount` when non-zero |
| `payment_method`             | Payment method, including the order token once created               |
| `applied_coupons`            | Coupons that were accepted                                           |
| `payment_confirmation`       | Order number and review page URL, after checkout                     |

## Status values

`status` is `CREATED` after a cart is created, `COMPLETED` once checkout produced an order, and `INCOMPLETE` otherwise. `validation_status` is `VALID` only when no validator reported anything.

Both fields are whitelisted on the way out. An unrecognized value silently becomes `INCOMPLETE` or `INVALID`, so the response is always schema-valid even if a code path sets something unexpected.

## When a cart can be paid

`StorePayPalCart::is_ready_for_payment()` requires all three: (1) the cart has items, (2) no validation issues detected, and (3) a payment token is present. Checkout will not proceed while any of them is missing.

## Errors

Errors replace the cart body with an envelope of `name` and `message`, plus `debug_id` and `details` when available. The `debug_id` also appears in the log line for the same failure, which is the fastest way to connect a reported error to its cause.

| Name                    | Status |
|-------------------------|--------|
| `INVALID_REQUEST`       | 400    |
| `CART_NOT_FOUND`        | 404    |
| `UNPROCESSABLE_ENTITY`  | 422    |
| `INTERNAL_SERVER_ERROR` | 500    |

`HttpErrorName` lists further names used for payment and order failures. Authentication failures are produced before an endpoint runs and are covered in [JWT authentication](authentication-via-jwt.md).

## Trying it out

PayPal publishes a [Postman collection](https://www.postman.com/paypal/workspace/paypal-agentic-commerce-workspace/collection/52882817-309b7569-7530-41fc-8be0-82f96c7d3f11) for the Cart API.

Sandbox merchants get relaxed authentication, so requests work without a properly signed token. See [setup and development](setup-and-development.md).

### Postman variables

Set correct values for those:

| Variable              | Value                                                          |
|-----------------------|----------------------------------------------------------------|
| `base_url`            | `https://wp-test-site.ddev.site/wp-json/wc/v3/agentic`         |
| `is_sandbox`          | `true`                                                         |
| `item_variant_id`     | An existing WooCommerce product ID                             |
| `jwt_merchant_id`     | ID of the onboarded merchant                                   |
| `jwt_private_key_pem` | Run `openssl genrsa 2048` locally and paste it into that field |
| `payer_id`            | See note below                                                 |

All other values can be left empty.

### Payer ID

1. Log into [Developer Dashboard](https://developer.paypal.com/dashboard/accounts) and locate a personal sandbox account
2. Look for `@personal.example.com` accounts
3. If none exists, create a new “Personal” sandbox user, based in US
4. Find the Account ID of that personal sandbox account: this is the `payer_id`.

## Hooks

Each endpoint fires one action on success and one on failure. They observe only; the response is already built and cannot be changed.

Success handlers receive `( string $cart_id, StorePayPalCart $store_cart, int $status_code )`, error handlers receive `( AgenticError $error )`.

| Hook                                                    | Type   | Fires when              | Source                                    |
|---------------------------------------------------------|--------|-------------------------|-------------------------------------------|
| `woocommerce_paypal_payments_store_sync_create`         | action | A cart was created      | `src/Endpoint/CreateCartEndpoint.php:34`  |
| `woocommerce_paypal_payments_store_sync_create_error`   | action | Creating a cart failed  | `src/Endpoint/CreateCartEndpoint.php:35`  |
| `woocommerce_paypal_payments_store_sync_get`            | action | A cart was read         | `src/Endpoint/GetCartEndpoint.php:34`     |
| `woocommerce_paypal_payments_store_sync_get_error`      | action | Reading a cart failed   | `src/Endpoint/GetCartEndpoint.php:35`     |
| `woocommerce_paypal_payments_store_sync_replace`        | action | A cart was replaced     | `src/Endpoint/ReplaceCartEndpoint.php:36` |
| `woocommerce_paypal_payments_store_sync_replace_error`  | action | Replacing a cart failed | `src/Endpoint/ReplaceCartEndpoint.php:37` |
| `woocommerce_paypal_payments_store_sync_checkout`       | action | Checkout completed      | `src/Endpoint/CheckoutEndpoint.php:51`    |
| `woocommerce_paypal_payments_store_sync_checkout_error` | action | Checkout failed         | `src/Endpoint/CheckoutEndpoint.php:52`    |

`AgenticRestEndpoint` also defines the fallback pair `woocommerce_paypal_payments_store_sync` and `..._store_sync_error`, used by any endpoint that does not declare its own.
