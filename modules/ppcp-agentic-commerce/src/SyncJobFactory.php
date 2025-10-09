<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Psr\Log\LoggerInterface;

class SyncJobFactory {
	private LoggerInterface $logger;
	private string $api_endpoint;

	public function __construct(
		string $api_endpoint,
		LoggerInterface $logger
	) {
		$this->logger       = $logger;
		$this->api_endpoint = $api_endpoint;
	}

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
