<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\AgenticWebhookConfiguration;

/**
 * Factory class for creating SyncJob instances.
 *
 * This factory encapsulates the creation of SyncJob objects with the necessary
 * dependencies (API endpoint and logger) while allowing different product sets
 * to be processed through separate job instances.
 */
class SyncJobFactory {
	private LoggerInterface $logger;
	private AgenticWebhookConfiguration $webhook_urls;
	private ProductsPayloadFactory $products_payload_factory;

	public function __construct(
		AgenticWebhookConfiguration $webhook_urls,
		LoggerInterface $logger,
		ProductsPayloadFactory $products_payload_factory
	) {

		$this->logger                   = $logger;
		$this->webhook_urls             = $webhook_urls;
		$this->products_payload_factory = $products_payload_factory;
	}

	/**
	 * Creates a new SyncJob instance for the given product IDs.
	 *
	 * This method instantiates a SyncJob with the factory's configured API endpoint
	 * and logger, along with the specified product IDs to be synchronized.
	 *
	 * @param array $product_ids Array of WooCommerce product IDs to be synced.
	 * @return SyncJob A configured SyncJob instance ready for execution.
	 */
	public function create_job( array $product_ids ): SyncJob {
		return new SyncJob(
			$this->webhook_urls->get_product_ingestion_url(),
			$product_ids,
			$this->logger,
			$this->products_payload_factory
		);
	}
}
