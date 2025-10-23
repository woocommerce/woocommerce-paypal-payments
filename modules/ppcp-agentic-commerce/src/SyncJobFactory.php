<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Psr\Log\LoggerInterface;

/**
 * Factory class for creating SyncJob instances.
 *
 * This factory encapsulates the creation of SyncJob objects with the necessary
 * dependencies (API endpoint and logger) while allowing different product sets
 * to be processed through separate job instances.
 */
class SyncJobFactory {
	/**
	 * Logger instance for recording sync operations and errors.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * The API endpoint URL for the agentic commerce product ingestion service.
	 *
	 * @var string
	 */
	private string $api_endpoint;

	/**
	 * Constructor for SyncJobFactory.
	 *
	 * @param string          $api_endpoint The API endpoint URL for product ingestion.
	 * @param LoggerInterface $logger       Logger instance for recording operations.
	 */
	public function __construct(
		string $api_endpoint,
		LoggerInterface $logger
	) {
		$this->logger       = $logger;
		$this->api_endpoint = $api_endpoint;
	}

	/**
	 * Creates a new SyncJob instance for the given product IDs.
	 *
	 * This method instantiates a SyncJob with the factory's configured API endpoint
	 * and logger, along with the specified product IDs to be synchronized.
	 *
	 * @param array $product_ids Array of WooCommerce product IDs to be synced.
	 *
	 * @return SyncJob A configured SyncJob instance ready for execution.
	 */
	public function create_job(
		array $product_ids
	): SyncJob {
		return new SyncJob(
			$this->api_endpoint,
			$product_ids,
			$this->logger
		);
	}
}
