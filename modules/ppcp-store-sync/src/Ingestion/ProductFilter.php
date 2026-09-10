<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use Automattic\WooCommerce\Enums\ProductStatus;
use Automattic\WooCommerce\Enums\ProductType;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Product;
/**
 * Single source of truth for "is this product eligible for the ingestion feed?".
 *
 * Owns the internal eligibility criteria, the reduce-only filter for third-party
 * exclusions and the "processed at" meta which lets the ingestion query skip
 * recently handled products until they become stale.
 */
class ProductFilter
{
    /**
     * Post-meta key holding the unix timestamp of when a product was last processed.
     *
     * "Processed" means we synced it or decided not to sync it (ineligible), so the
     * value records "we have dealt with this product". It is compared live against a
     * dynamically computed stale threshold, never a persisted deadline:
     *
     * Absent          .. never processed (new or edited); first priority.
     * < stale_date    .. processed long ago; stale, re-evaluate and sync.
     * >= stale_date   .. processed recently; skip.
     */
    public const META_KEY = '_ppcp_agentic_processed_at';
    /**
     * Product types compatible with the ingestion feed.
     */
    public const SUPPORTED_PRODUCT_TYPES = array(ProductType::SIMPLE, ProductType::VARIABLE);
    private LoggerInterface $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    /**
     * Coarse eligibility criteria as declarative arguments for wc_get_products().
     *
     * @return array<string, mixed>
     */
    public function query_filters(): array
    {
        return array('status' => ProductStatus::PUBLISH, 'type' => self::SUPPORTED_PRODUCT_TYPES, 'downloadable' => \false);
    }
    /**
     * Per-product form of {@see ProductFilter::query_filters()}.
     *
     * @param WC_Product $product The product to inspect.
     *
     * @return string|null The slug of the violated rule ('downloadable' | 'type' | 'status'),
     *                     or null when the product matches all criteria.
     */
    public function criteria_violation(WC_Product $product): ?string
    {
        if ($product->is_downloadable()) {
            return 'downloadable';
        }
        if (!$product->is_type(self::SUPPORTED_PRODUCT_TYPES)) {
            return 'type';
        }
        if (ProductStatus::PUBLISH !== $product->get_status()) {
            return 'status';
        }
        return null;
    }
    /**
     * Runs the reduce-only third-party exclusion hook.
     *
     * @param WC_Product $product The product to inspect.
     * @return bool True to keep the product, false when a third party excludes it.
     */
    public function passes_exclusion_filter(WC_Product $product): bool
    {
        return !(bool) apply_filters('woocommerce_paypal_payments_store_sync_exclude_product', \false, $product);
    }
    /**
     * Records that a product was processed (synced or decided ineligible).
     *
     * Writes the current timestamp, so the product is skipped by the ingestion query
     * until it becomes stale, then re-evaluated. The same value is written whether the
     * product was synced or excluded: the meta only records "we dealt with this".
     */
    public function mark_processed(WC_Product $product, string $log_info = ''): void
    {
        $product->update_meta_data(self::META_KEY, (string) time());
        $product->save_meta_data();
        $context = array();
        if ('' !== $log_info) {
            $context['log_info'] = $log_info;
        }
        $this->logger->debug(sprintf('[ProductFilter] Marked product %d processed', $product->get_id()), $context);
    }
    /**
     * Releases a product so it is re-evaluated on the next run (first priority).
     *
     * @param WC_Product $product The product to release.
     */
    public function release(WC_Product $product): void
    {
        $product->delete_meta_data(self::META_KEY);
        $product->save_meta_data();
    }
    /**
     * Clears the processed marker on every product, forcing a full re-evaluation on
     * the next run.
     *
     * Reminder: WC products are stored in the post table, even with HPOS enabled.
     */
    public function invalidate_all(): void
    {
        delete_post_meta_by_key(self::META_KEY);
        /**
         * Broadcast that all ingestion-flags were invalidated and the next sync
         * re-evaluates each product again.
         */
        do_action('woocommerce_paypal_payments_store_sync_eligibility_invalidated');
        $this->logger->info('[ProductFilter] Invalidated all processed markers');
    }
}
