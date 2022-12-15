<?php
/**
 * Fraudnet entity.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\FraudNet;

/**
 * Class FraudNet
 */
class FraudNet {

	/**
	 * The session ID.
	 *
	 * @var string
	 */
	protected $session_id;

	/**
	 * The source website ID.
	 *
	 * @var string
	 */
	protected $source_website_id;

	/**
	 * FraudNet constructor.
	 *
	 * @param string $session_id The session ID.
	 * @param string $source_website_id The source website ID.
	 */
	public function __construct( string $session_id, string $source_website_id ) {
		$this->session_id        = $session_id;
		$this->source_website_id = $source_website_id;
	}

	/**
	 * Returns the session ID.
	 *
	 * @return string
	 */
	public function session_id(): string {
		return $this->session_id;
	}

	/**
	 * Returns the source website id.
	 *
	 * @return string
	 */
	public function source_website_id(): string {
		return $this->source_website_id;
	}
}
