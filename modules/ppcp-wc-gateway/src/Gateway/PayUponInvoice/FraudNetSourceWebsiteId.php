<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

class FraudNetSourceWebsiteId
{
	/**
	 * @var string
	 */
	protected $api_merchant_id;

	public function __construct(string $api_merchant_id)
	{
		$this->api_merchant_id = $api_merchant_id;
	}

	public function __invoke()
	{
		return "{$this->api_merchant_id}_checkout-page";
	}
}
