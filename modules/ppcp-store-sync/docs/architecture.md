# Architecture

Context for contributors and for debugging.

## Onboarding handshake

The store introduces itself to PayPal once. [`RegistrationEligibility`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Registration/RegistrationEligibility.php) decides whether it may, [`RegistrationService`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Registration/RegistrationService.php) performs the call, and the resulting token is stored in option `ppcp_agentic_registration_token`.

The token is a JWT the store builds itself, signed with a dummy key, because PayPal does not verify the signature on this call. It identifies the store rather than authenticating it. Store identity in the payload comes from [`MerchantMetadataProvider`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Merchant/MerchantMetadataProvider.php).

## Outbound endpoints

Sandbox merchants use `d-staging.joinhoney.com`, everyone else uses `d.joinhoney.com`. [`AgenticWebhookConfiguration`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Config/AgenticWebhookConfiguration.php) picks the host.

| Purpose            | Path                     |
|--------------------|--------------------------|
| Register the store | `/webhooks/ws/install`   |
| Deregister         | `/webhooks/ws/uninstall` |
| Product feed       | `/webhooks/products`     |

One further outbound call is unrelated to these: PayPal's public verification keys are fetched from `www.paypal.ai`, by [`PayPalJwkProvider`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Auth/PayPalJwkProvider.php).

## Deregistration

The store deregisters when the merchant disconnects the account, the plugin is deactivated, the plugin is uninstalled, or the feature stops being eligible or active.

Deregistering also unschedules the recurring sync. The feed then stops without any error being logged, which is worth remembering when a store mysteriously went quiet.

## How a batch is selected

Every product carries the timestamp of the last time the module dealt with it, in post meta `_ppcp_agentic_processed_at`. That timestamp is the whole scheduling mechanism.

| Marker state             | Meaning                                      |
|--------------------------|----------------------------------------------|
| Absent                   | Never processed, or edited since. Goes first |
| Older than the threshold | Processed long ago. Re-evaluate and re-sync  |
| Newer than the threshold | Handled recently. Skip                       |

[`IngestionBatchProvider`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/IngestionBatchProvider.php) fills a batch by taking never-processed products before stale ones, and runs each candidate through [`ProductFilter`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/ProductFilter.php) before accepting it.

Consequences that surprise people. A product that was excluded is marked exactly like a product that was synced, because the marker records "we dealt with this", not "this is in the catalog". And marking a product dirty means *deleting* the meta, not writing to it.

The threshold is computed live from the catalog lifespan, never stored, so changing the lifespan filter takes effect immediately.

## How a cart request is served

Every endpoint follows the same path, defined in [`AgenticRestEndpoint`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Endpoint/AgenticRestEndpoint.php):

1. Authenticate the JWT, and reject before any cart work happens.
2. Parse the body into [`PayPalCart`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Schema/PayPalCart.php), the input contract. Malformed content, such as a missing item ID, becomes a validation issue rather than an exception.
3. Build [`StorePayPalCart`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/StoreData/StorePayPalCart.php), which resolves real products and store-authoritative prices alongside what the agent claimed.
4. Run [`CartValidationProcessor`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/CartValidation/CartValidationProcessor.php), collecting issues onto the cart.
5. Assemble a [`CartResponse`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Response/CartResponse.php) through [`ResponseFactory`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Response/ResponseFactory.php).

## Session isolation

Agent requests must not touch a shopper's cart, so [`AgenticSessionManager`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Helper/AgenticSessionManager.php) swaps the WooCommerce session for a throwaway one and blocks `WC_Cart_Session` from initializing, restoring both afterward.

This is why code that assumes a logged-in customer or a persisted cart behaves differently inside these endpoints than on the storefront.

## Source layout

| Path                  | Contains                                              |
|-----------------------|-------------------------------------------------------|
| `src/Ingestion/`      | The product feed: batching, eligibility, the sync job |
| `src/Endpoint/`       | The REST endpoints                                    |
| `src/CartValidation/` | Validators and the processor that runs them           |
| `src/Validation/`     | Validation issues, contexts, resolution options       |
| `src/Schema/`         | Input contract: what PayPal sends us                  |
| `src/StoreData/`      | Output contract: store-authoritative cart data        |
| `src/Auth/`           | JWT verification                                      |
| `src/Registration/`   | Registering and deregistering the store               |
| `src/Helper/`         | Cart building, checkout processing, product data      |
| `src/Setting/`        | The merchant-facing settings toggle                   |
| `services.php`        | Service definitions for the whole module              |

## Hooks

Registration has no extension surface beyond observing it.

### Registration indicators

- `woocommerce_paypal_payments_store_sync_registered`
- `woocommerce_paypal_payments_store_sync_deregistered`

Sample:

```php
/**
 * The store was registered with PayPal, or deregistered from it.
 */
add_action(
	'woocommerce_paypal_payments_store_sync_registered',
	function (): void {}
);
```

Source: [`src/Registration/RegistrationService.php`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Registration/RegistrationService.php)
