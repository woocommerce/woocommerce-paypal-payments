# Product catalog

The store pushes batches of products to PayPal on a schedule, so agents know what is for sale. Two things are yours to change: which products appear, and what each one looks like. For how batches are selected, see [architecture](architecture.md).

## Settings

| Setting            | Default                               | Filter                                                             |
|--------------------|---------------------------------------|--------------------------------------------------------------------|
| Sync interval      | 15 minutes                            | `woocommerce_paypal_payments_store_sync_interval`                  |
| Products per batch | 50                                    | `woocommerce_paypal_payments_store_sync_batch_size`                |
| Stale threshold    | 5 days, minus a 5-cycle safety margin | `woocommerce_paypal_payments_store_sync_expired_product_timestamp` |

Two caveats. The interval is only read when the recurring job is first scheduled, so changing it later has no effect until the job is unscheduled. A batch size of `0` or less produces an empty batch, which pauses the feed without unscheduling it.

## Which products appear

A product must be published, be a simple or variable product, and not be downloadable. `ProductFilter` owns these rules and they are not filterable.

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

Exclusion is not cosmetic. The same filter runs during cart validation (`ProductValidator`), so an excluded product cannot be bought through the agent API either, not merely hidden from the feed.

## Changing product fields

Each field sent to PayPal passes through a filter. All six take `( string $value, WC_Product $product )`.

| Filter                                                     | Value                                      |
|------------------------------------------------------------|--------------------------------------------|
| `woocommerce_paypal_payments_store_sync_item_title`        | Product name                               |
| `woocommerce_paypal_payments_store_sync_item_description`  | Description, stripped to plain text        |
| `woocommerce_paypal_payments_store_sync_item_link`         | Permalink                                  |
| `woocommerce_paypal_payments_store_sync_item_image`        | Full-size image URL                        |
| `woocommerce_paypal_payments_store_sync_item_availability` | `in stock`, `out of stock`, or `backorder` |
| `woocommerce_paypal_payments_store_sync_item_product_type` | Category list, as plain text               |

Returning an empty string does not send a blank field. Most of these are required, and a product missing a required field is dropped from the batch and marked as processed, so it silently disappears from the catalog instead of syncing with a gap. See `SyncJob::has_complete_sync_data()` for the required set.

Prices, SKU, and variant attributes have no filters.

## Variants

A variable product is never sent itself. Its purchasable variations are sent as individual items, grouped under the parent through `item_group_id`. Only the `color`, `size`, and `gender` attributes are recognised. See `ProductsPayload` for the assembly and `ProductDTO` for the wire format.

## Forcing a re-sync

Editing a product or changing its stock already queues it for the next batch. Fire this when something the module cannot observe changes, such as the logic inside your own exclusion filter:

```php
do_action( 'woocommerce_paypal_payments_store_sync_invalidate_eligibility' );
```

Every product is then re-evaluated on the following runs. On a large catalog this costs several cycles to work through.

## Hooks

| Hook                                                               | Type   | Arguments                              | Source                                     |
|--------------------------------------------------------------------|--------|----------------------------------------|--------------------------------------------|
| `woocommerce_paypal_payments_store_sync_exclude_product`           | filter | `bool $exclude`, `WC_Product $product` | `src/Ingestion/ProductFilter.php:92`       |
| `woocommerce_paypal_payments_store_sync_interval`                  | filter | `int $seconds`                         | `src/Config/IngestionConfiguration.php:52` |
| `woocommerce_paypal_payments_store_sync_batch_size`                | filter | `int $size`                            | `src/Config/IngestionConfiguration.php:69` |
| `woocommerce_paypal_payments_store_sync_expired_product_timestamp` | filter | `int $timestamp`                       | `src/Config/IngestionConfiguration.php:37` |
| `woocommerce_paypal_payments_store_sync_item_title`                | filter | `string $value`, `WC_Product $product` | `src/Helper/ProductManager.php:145`        |
| `woocommerce_paypal_payments_store_sync_item_description`          | filter | `string $value`, `WC_Product $product` | `src/Helper/ProductManager.php:127`        |
| `woocommerce_paypal_payments_store_sync_item_link`                 | filter | `string $value`, `WC_Product $product` | `src/Helper/ProductManager.php:163`        |
| `woocommerce_paypal_payments_store_sync_item_image`                | filter | `string $value`, `WC_Product $product` | `src/Helper/ProductManager.php:185`        |
| `woocommerce_paypal_payments_store_sync_item_availability`         | filter | `string $value`, `WC_Product $product` | `src/Helper/ProductManager.php:211`        |
| `woocommerce_paypal_payments_store_sync_item_product_type`         | filter | `string $value`, `WC_Product $product` | `src/Helper/ProductManager.php:234`        |
| `woocommerce_paypal_payments_store_sync_invalidate_eligibility`    | action | none                                   | `src/Ingestion/IngestionManager.php:71`    |
| `woocommerce_paypal_payments_store_sync_eligibility_invalidated`   | action | none                                   | `src/Ingestion/ProductFilter.php:146`      |
| `woocommerce_paypal_payments_store_sync_ingestion_completed`       | action | `array $result`                        | `src/Ingestion/SyncJob.php:19`             |

`$result` carries `batch_id`, `status` (`success`, `validation_errors`, `api_error`, or `empty`), `pushed`, `synced`, `failed`, and `error_message`.
