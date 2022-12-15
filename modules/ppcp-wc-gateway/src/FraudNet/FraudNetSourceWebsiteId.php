<?php
/**
 * Fraudnet source website ID.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\FraudNet;

/**
 * Class FraudNetSourceWebsiteId.
 */
class FraudNetSourceWebsiteId {

	/**
	 * The merchant id.
	 *
	 * @var string
	 */
	protected $api_merchant_id;

	/**
	 * FraudNetSourceWebsiteId constructor.
	 *
	 * @param string $api_merchant_id The merchant id.
	 */
	public function __construct( string $api_merchant_id ) {
		$this->api_merchant_id = $api_merchant_id;
	}

	/**
	 * Returns the source website ID.
	 *
	 * @return string
	 */
	public function __invoke() {
		return "{$this->api_merchant_id}_checkout-page";
	}
}
