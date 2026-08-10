# Setup and development

Store sync only runs for a merchant it supports, and only while the merchant leaves it switched on. Most reports of "nothing happens" are one of those two conditions.

## When store sync runs

**The merchant must be eligible.** [`RegistrationEligibility`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Registration/RegistrationEligibility.php) requires a connected PayPal account and checks the store and merchant countries against the markets PayPal supports for agentic commerce. That list is narrow while the feature rolls out, so read the class for what currently qualifies. Eligibility is evaluated on `init` and cached in the settings, because the answer is needed earlier in the request than the PayPal connection data becomes available.

**The feature must be active.** The merchant toggle lives in option `woocommerce-ppcp-ext-agentic`, with keys `active` (on by default) and `eligible` (the cached eligibility result). [`AgenticSettingsDataModel::should_initialize_features()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Setting/AgenticSettingsDataModel.php) combines them, and the module stops early when it returns `false`.

## Loading the module before general availability

Store sync is still behind a feature flag, so it is not loaded at all unless you ask for it. Set the environment variable `PCP_STORE_SYNC_ENABLED` to `1`, or force the flag:

```php
add_filter( 'woocommerce.feature-flags.woocommerce_paypal_payments.store_sync_enabled', '__return_true' );
```

While the flag is off, none of the module's classes exist and none of its hooks ever fire, so code depending on them cannot fail gracefully. There is nothing to fail.

## Checking whether the feature is live

The recurring sync is scheduled only after everything above has passed, so its presence answers the question immediately:

```php
$is_live = (bool) as_next_scheduled_action( 'ppcp_agentic_sync_batch' );
```

Reading the option is not sufficient on its own. `active` can be `true` while the merchant is ineligible, in which case nothing runs.

## Logs

The module writes to separate WooCommerce log sources, so high-volume feed entries stay out of the cart API stream:

| Source                         | Contains                           |
|--------------------------------|------------------------------------|
| `woocommerce-paypal-agentic`   | Registration and cart API requests |
| `woocommerce-paypal-ingestion` | Product feed batches               |

Both are visible under WooCommerce, Status, Logs.

## Local development

Sandbox merchants get relaxed authentication automatically, so the endpoints accept requests without a properly signed token. See [JWT authentication](authentication-via-jwt.md) for what is still checked, and [REST endpoints](rest-api.md) for calling them.

An eligible store registers itself with PayPal as soon as the feature is active. To work on the module without that happening, keep it enabled but suppress registration:

```php
define( 'PPCP_AGENTIC_AUTO_REGISTER', false );
```

To run a product sync now instead of waiting for the schedule:

```php
do_action( 'ppcp_agentic_sync_batch' );
```

The recurring job itself is visible under WooCommerce, Status, Scheduled Actions, in the `ppcp_agentic_sync` group.

For tests, see [testing](testing.md).
