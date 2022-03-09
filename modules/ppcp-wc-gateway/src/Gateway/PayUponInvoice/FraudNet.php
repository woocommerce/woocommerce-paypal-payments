<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

class FraudNet {

	/**
	 * @var string
	 */
	protected $session_id;

	/**
	 * @var string
	 */
	protected $source_website_id;

	public function __construct( string $session_id, string $source_website_id ) {
		$this->session_id        = $session_id;
		$this->source_website_id = $source_website_id;
	}

	/**
	 * @return string
	 */
	public function sessionId(): string {
		return $this->session_id;
	}

	/**
	 * @return string
	 */
	public function sourceWebsiteId(): string {
		return $this->source_website_id;
	}
}
