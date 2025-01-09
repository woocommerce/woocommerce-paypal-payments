<?php
/**
 * Data transfer object. Stores all connection credentials of the PayPal merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Settings\DTO;
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\DTO;

/**
 * DTO that collects all details of a "merchant connection".
 *
 * Intentionally has no internal logic, sanitation or validation.
 */
class MerchantConnectionDTO {
	/**
	 * Whether this connection is a sandbox account.
	 *
	 * @var bool
	 */
	public bool $is_sandbox = false;

	/**
	 * API client ID.
	 *
	 * @var string
	 */
	public string $client_id = '';

	/**
	 * API client secret.
	 *
	 * @var string
	 */
	public string $client_secret = '';

	/**
	 * PayPal's 13-character merchant ID.
	 *
	 * @var string
	 */
	public string $merchant_id = '';

	/**
	 * Email address of the merchant account.
	 *
	 * @var string
	 */
	public string $merchant_email = '';

	/**
	 * Constructor.
	 *
	 * @param bool   $is_sandbox     Whether this connection is a sandbox account.
	 * @param string $client_id      API client ID.
	 * @param string $client_secret  API client secret.
	 * @param string $merchant_id    PayPal's 13-character merchant ID.
	 * @param string $merchant_email Email address of the merchant account.
	 */
	public function __construct(
		bool $is_sandbox,
		string $client_id,
		string $client_secret,
		string $merchant_id,
		string $merchant_email
	) {
		$this->is_sandbox     = $is_sandbox;
		$this->client_id      = $client_id;
		$this->client_secret  = $client_secret;
		$this->merchant_id    = $merchant_id;
		$this->merchant_email = $merchant_email;
	}
}
