# Product catalog

The store pushes batches of products to PayPal on a schedule, so agents know what is for sale. What is yours to change: which products appear, and what each one looks like. For how batches are selected, see [architecture](architecture.md).

## Settings

| Setting            | Default        | Filter                                                             |
|--------------------|----------------|--------------------------------------------------------------------|
| Sync interval      | 15 minutes     | `woocommerce_paypal_payments_store_sync_interval`                  |
| Products per batch | 50             | `woocommerce_paypal_payments_store_sync_batch_size`                |
| Stale threshold    | Roughly 5 days | `woocommerce_paypal_payments_store_sync_expired_product_timestamp` |

1. The interval is only read when the recurring job is first scheduled, so changing it later has no effect until the job is unscheduled.
2. A batch size of `0` or less produces an empty batch, which pauses the feed without unscheduling it.

## Which products appear

[`ProductFilter`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/ProductFilter.php) owns the baseline rules and they are not filterable. It drops unpublished products, for example, and only supports a subset of WooCommerce product types.

Everything that passes goes through an exclusion filter, which is yours:

```php
add_filter(
	'woocommerce_paypal_payments_store_sync_exclude_product',
	function ( bool $exclude, WC_Product $product ): bool {
		return $exclude || 'yes' === $product->get_meta( '_hide_from_agents' );
	},
	10,
	2
);
```

The filter reduces only. Returning `false` cannot bring back a product that failed the rules above.

Exclusion is not cosmetic. The same filter runs during cart validation ([`ProductValidator`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/CartValidation/ProductValidator.php)), so an excluded product cannot be bought through the agent API either, not merely hidden from the feed.

## Changing product fields

Each field sent to PayPal passes through a filter, and they all take `( string $value, WC_Product $product )`.

| Filter                                                     | Value                                      |
|------------------------------------------------------------|--------------------------------------------|
| `woocommerce_paypal_payments_store_sync_item_title`        | Product name                               |
| `woocommerce_paypal_payments_store_sync_item_description`  | Description, stripped to plain text        |
| `woocommerce_paypal_payments_store_sync_item_link`         | Permalink                                  |
| `woocommerce_paypal_payments_store_sync_item_image`        | Full-size image URL                        |
| `woocommerce_paypal_payments_store_sync_item_availability` | `in stock`, `out of stock`, or `backorder` |
| `woocommerce_paypal_payments_store_sync_item_product_type` | Category list, as plain text               |

Returning an empty string does not send a blank field. Most of these are required, and a product missing a required field is dropped from the batch and marked as processed, so it silently disappears from the catalog instead of syncing with a gap. See [`SyncJob::has_complete_sync_data()`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/SyncJob.php) for the required set.

## Variants

A variable product is never sent itself. Its purchasable variations are sent as individual items, grouped under the parent through `item_group_id`. Only the `color`, `size`, and `gender` attributes are recognized. See [`ProductsPayload`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/ProductsPayload.php) for the assembly and [`ProductDTO`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/ProductDTO.php) for the wire format.

## Forcing a re-sync

Editing a product or changing its stock already queues it for the next batch. Fire this when something the module cannot observe changes, such as the logic inside your own exclusion filter:

```php
do_action( 'woocommerce_paypal_payments_store_sync_invalidate_eligibility' );
```

Every product is then re-evaluated on the following runs. On a large catalog this costs several cycles to work through.

## Hooks

### Exclusion filter

```php
/**
 * Whether to keep a product out of the feed. Reduce-only, see "Which products appear" above.
 */
add_filter(
	'woocommerce_paypal_payments_store_sync_exclude_product',
	function ( bool $exclude, WC_Product $product ): bool {
		return $exclude;
	},
	10, 2
);
```

Source: [`src/Ingestion/ProductFilter.php`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/ProductFilter.php)

### Configuration filters

```php
/**
 * Seconds between recurring sync runs. Must be positive.
 */
add_filter(
	'woocommerce_paypal_payments_store_sync_interval',
	function ( int $seconds ): int {
		return $seconds;
	}
);

/**
 * Products per batch. Zero or less pauses the feed.
 */
add_filter(
	'woocommerce_paypal_payments_store_sync_batch_size',
	function ( int $size ): int {
		return $size;
	}
);

/**
 * Unix timestamp beyond which a synced product counts as stale and is re-evaluated.
 */
add_filter(
	'woocommerce_paypal_payments_store_sync_expired_product_timestamp',
	function ( int $timestamp ): int {
		return $timestamp;
	}
);
```

Source: [`src/Config/IngestionConfiguration.php`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Config/IngestionConfiguration.php)

### Product field filters

- `woocommerce_paypal_payments_store_sync_item_title`
- `woocommerce_paypal_payments_store_sync_item_description`
- `woocommerce_paypal_payments_store_sync_item_link`
- `woocommerce_paypal_payments_store_sync_item_image`
- `woocommerce_paypal_payments_store_sync_item_availability`
- `woocommerce_paypal_payments_store_sync_item_product_type`

What each one carries is described under [Changing product fields](#changing-product-fields).

Sample:

```php
/**
 * A single field on a product, as sent to PayPal.
 */
add_filter(
	'woocommerce_paypal_payments_store_sync_item_title',
	function ( string $value, WC_Product $product ): string {
		return $value;
	},
	10, 2
);
```

Source: [`src/Helper/ProductManager.php`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Helper/ProductManager.php)

### Actions

```php
/**
 * Fires after a sync run, whether it succeeded or not.
 *
 * $result carries batch_id, status (success, validation_errors, api_error, empty),
 * pushed, synced, failed, and error_message.
 */
add_action(
	'woocommerce_paypal_payments_store_sync_ingestion_completed',
	function ( array $result ): void {}
);
```

Source: [`src/Ingestion/SyncJob.php`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/SyncJob.php)

```php
/**
 * Fires after every product was queued for re-evaluation.
 */
add_action(
	'woocommerce_paypal_payments_store_sync_eligibility_invalidated',
	function (): void {}
);
```

Source: [`src/Ingestion/ProductFilter.php`](https://github.com/woocommerce/woocommerce-paypal-payments/blob/dev/develop/modules/ppcp-store-sync/src/Ingestion/ProductFilter.php)
