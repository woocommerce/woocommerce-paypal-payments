<?php
/**
 * The client credentials.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Authentication
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class ClientCredentials
 */
class ClientCredentials {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * ClientCredentials constructor.
	 *
	 * @param Settings $settings The settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns encoded client credentials.
	 *
	 * @return string
	 * @throws NotFoundException If setting does not found.
	 */
	public function credentials(): string {
		$client_id     = $this->settings->has( 'client_id' ) ? $this->settings->get( 'client_id' ) : '';
		$client_secret = $this->settings->has( 'client_secret' ) ? $this->settings->get( 'client_secret' ) : '';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'Basic ' . base64_encode( $client_id . ':' . $client_secret );
	}
}
