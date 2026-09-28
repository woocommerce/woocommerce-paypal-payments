<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use RuntimeException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use JsonException;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
/**
 * Represents a sync job for sending product data to the agentic commerce API.
 * This class handles the execution of product synchronization operations,
 * including transforming product data, sending requests to the API, and
 * managing success/failure states for products.
 */
class SyncJob
{
    private const ACTION_NAME_COMPLETED = 'woocommerce_paypal_payments_store_sync_ingestion_completed';
    private array $product_ids;
    private LoggerInterface $logger;
    private string $batch_id;
    private string $api_endpoint;
    private string $merchant_store_url;
    private ProductManager $product_manager;
    private \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter $product_filter;
    public function __construct(string $api_endpoint, string $merchant_store_url, array $product_ids, LoggerInterface $logger, ProductManager $product_manager, \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter $product_filter)
    {
        $this->api_endpoint = $api_endpoint;
        $this->merchant_store_url = $merchant_store_url;
        $this->product_ids = $product_ids;
        $this->logger = $logger;
        $this->batch_id = wp_generate_uuid4();
        $this->product_manager = $product_manager;
        $this->product_filter = $product_filter;
    }
    /**
     * Execute the sync job.
     *
     * This method performs the complete sync process:
     * 1. Transforms products into the API payload format
     * 2. Sends the data to the agentic commerce API
     * 3. Handles successful responses by marking products as synced
     * 4. Handles validation errors by marking only affected products with error details
     * 5. Handles API/network errors by logging and re-throwing exceptions for retry
     *
     * @throws RuntimeException When a retryable error occurs during sync.
     */
    public function execute(): void
    {
        $this->logger->info('[Sync] Started', $this->build_log_context());
        // Transform products into DTOs.
        $payload_container = new \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductsPayload($this->merchant_store_url, $this->product_ids, $this->product_manager);
        $products = $payload_container->get_products();
        // Drop entries the webhook would reject for missing data; park their products.
        $products = $this->drop_incomplete_products($products);
        if (empty($products)) {
            $this->logger->info('[Sync] No products', $this->build_log_context());
            $this->fire_completed_action('empty', 0, 0, 0);
            return;
        }
        // Compose the API payload, serialising each product DTO to its wire format.
        $body = array('merchant_url' => $this->merchant_store_url, 'products' => array_map(static fn(\WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductDTO $product): array => $product->to_array(), $products));
        // Send payload to API.
        $response = wp_remote_post($this->api_endpoint, array('timeout' => 30, 'headers' => array('Content-Type' => 'application/json'), 'body' => (string) wp_json_encode($body)));
        $this->logger->debug('[Sync] Started...', $this->build_log_context($body));
        if (is_wp_error($response)) {
            // Log the error message and throw an Exception.
            $this->handle_api_error($this->product_ids, $response->get_error_message());
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($status_code >= 200 && $status_code < 422) {
            $this->handle_successful_response($response_body, $products);
            return;
        }
        // Log the error message and throw an Exception.
        $this->handle_api_error($this->product_ids, "HTTP {$status_code}: {$response_body}");
    }
    private function drop_incomplete_products(array $products): array
    {
        $complete = array();
        foreach ($products as $product) {
            $data = $product->to_array();
            if ($this->has_complete_sync_data($data)) {
                $complete[] = $product;
                continue;
            }
            // The item_group_id is always a WC_Product, even for variants.
            $entry_id = (int) $data['item_group_id'];
            if (in_array($entry_id, $this->product_ids, \true)) {
                $this->product_ids = array_values(array_diff($this->product_ids, array($entry_id)));
                $wc_product = wc_get_product($entry_id);
                if ($wc_product) {
                    $this->product_filter->mark_processed($wc_product, 'Incomplete payload');
                }
            }
        }
        return $complete;
    }
    private function has_complete_sync_data(array $data): bool
    {
        $required_fields = array('id', 'item_group_id', 'title', 'link', 'image_link', 'description', 'price', 'availability', 'merchantStoreUrl');
        foreach ($required_fields as $field) {
            if ('' === ($data[$field] ?? '')) {
                return \false;
            }
        }
        return \true;
    }
    /**
     * Handle successful API response.
     *
     * Parses the response to check for individual product validation errors,
     * marks products accordingly, and logs the result.
     *
     * @param string       $response_body    The API response body.
     * @param ProductDTO[] $payload_products The product entries sent in the request, in
     *                                       payload order (used to resolve per-item errors).
     */
    private function handle_successful_response(string $response_body, array $payload_products): void
    {
        // First, mark all products as synced to avoid re-syncing them in the next batch.
        $this->mark_products_synced($this->product_ids);
        // Check for validation issues from the response and document them in product-meta fields.
        try {
            $response_data = json_decode($response_body, \true, 512, \JSON_THROW_ON_ERROR);
            $this->logger->info(sprintf('[Sync] Successfully synced %d products', count($this->product_ids)), $this->build_log_context($response_data));
            $contains_errors = \false === ($response_data['success'] ?? \false);
            $error_message = $response_data['message'] ?? '';
        } catch (JsonException $e) {
            // Do not process invalid JSON data.
            $this->logger->error('Invalid JSON response', array('response' => $response_body, 'error' => $e->getMessage()));
            return;
        }
        $validation_errors = array();
        if ($contains_errors && $error_message) {
            $validation_errors = $this->extract_product_errors($error_message, $payload_products);
            $this->mark_products_by_validation_result($validation_errors);
        }
        $pushed = count($this->product_ids);
        $failed = count($validation_errors);
        $status = $failed > 0 ? 'validation_errors' : 'success';
        $this->fire_completed_action($status, $pushed, $pushed - $failed, $failed, $error_message);
    }
    /**
     * Extract product IDs that failed validation from error message.
     *
     * Parses error messages like "data/products/0/image_link must pass..." to
     * identify which products in the batch actually failed validation.
     *
     * @param string       $error_message    The error message to parse.
     * @param ProductDTO[] $payload_products The product entries sent in the request, in payload
     *                                       order.
     * @return array Array of product IDs (keys) and the relevant validation error (values).
     */
    private function extract_product_errors(string $error_message, array $payload_products): array
    {
        $errors = array();
        // Pattern: data/products/{index} followed by error text until comma or end.
        preg_match_all('/data\/products\/(\d+)\s+([^,]+)/', $error_message, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            $index = (int) $match[1];
            $entry = $payload_products[$index] ?? null;
            if (null === $entry) {
                continue;
            }
            $id = (int) $entry->to_array()['item_group_id'];
            $errors[$id] = trim($match[2]);
        }
        return $errors;
    }
    /**
     * Mark products based on validation results.
     *
     * Products that failed validation get error annotations.
     * Products that passed (or weren't mentioned) get marked as successfully synced.
     *
     * @param array $validation_errors Mapping of product-id to validation error.
     */
    private function mark_products_by_validation_result(array $validation_errors): void
    {
        $this->logger->warning('[Sync] Validation errors', $this->build_log_context($validation_errors));
        foreach ($validation_errors as $product_id => $error_message) {
            $this->mark_product_with_validation_error($product_id, $error_message);
        }
    }
    /**
     * Handle API or network errors by logging and throwing exception for retry.
     *
     * This method handles actual API failures (not validation errors) that should
     * trigger retry logic. The error is logged and an exception is thrown to signal
     * Action Scheduler to retry; products are left unmarked so they are retried.
     *
     * @param array  $product_ids   Product IDs that failed to sync.
     * @param string $error_message The error message.
     * @throws RuntimeException When an error occurs during sync.
     */
    private function handle_api_error(array $product_ids, string $error_message): void
    {
        $this->logger->warning(sprintf('[Sync] API Error - %s', $error_message), $this->build_log_context(array('product_count' => count($product_ids), 'product_ids' => $product_ids)));
        $pushed = count($product_ids);
        $this->fire_completed_action('api_error', $pushed, 0, $pushed, $error_message);
        throw new RuntimeException(sprintf('Agentic sync failed: %s', $error_message));
    }
    /**
     * Fires the ingestion-completed action so other modules/plugins can monitor sync activity.
     *
     * @param string $status        One of: success, validation_errors, api_error, empty.
     * @param int    $pushed        Number of products sent in the request payload.
     * @param int    $synced        Number of products successfully synced (no validation errors).
     * @param int    $failed        Number of products that failed (validation or API error).
     * @param string $error_message Optional error message for failure cases.
     */
    private function fire_completed_action(string $status, int $pushed, int $synced, int $failed, string $error_message = ''): void
    {
        do_action(self::ACTION_NAME_COMPLETED, array('batch_id' => $this->batch_id, 'status' => $status, 'pushed' => $pushed, 'synced' => $synced, 'failed' => $failed, 'error_message' => $error_message));
    }
    /**
     * Mark a single product as synced successfully.
     *
     * @param int $product_id Product ID to mark as synced.
     */
    private function mark_product_synced(int $product_id): void
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        $this->product_filter->mark_processed($product);
    }
    /**
     * Mark a single product with validation error.
     *
     * Products with validation errors are still considered "processed" (the sync
     * attempt was made); the validation error is routed to the log via mark_processed().
     *
     * @param int    $product_id    Product ID.
     * @param string $error_message Validation error message.
     */
    private function mark_product_with_validation_error(int $product_id, string $error_message): void
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        $this->product_filter->mark_processed($product, $error_message);
    }
    /**
     * Mark multiple products as synced by updating their last sync timestamp.
     *
     * @param array $product_ids Product IDs to mark as synced.
     */
    private function mark_products_synced(array $product_ids): void
    {
        foreach ($product_ids as $product_id) {
            $this->mark_product_synced($product_id);
        }
    }
    /**
     * @param mixed $context The log data - an array or any other value to log.
     * @return array
     */
    private function build_log_context($context = array()): array
    {
        $log_context = array('job' => $this->batch_id);
        if (!is_array($context)) {
            if (null === $context) {
                $context = array();
            } else {
                $context = array('raw' => $context);
            }
        }
        return array_merge($log_context, $context);
    }
}
