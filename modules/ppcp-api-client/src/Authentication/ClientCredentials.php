<?php
/**
 * The client credentials.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Authentication
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

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
	public function __construct(Settings $settings) {
		$this->settings = $settings;
	}

	public function credentials(): string {
		$client_id = $this->settings->has( 'client_id' ) ? $this->settings->get( 'client_id' ) : '';
		$client_secret = $this->settings->has( 'client_secret' ) ? $this->settings->get( 'client_secret' ) : '';

		return 'Basic ' . base64_encode($client_id . ':' . $client_secret);
	}
}
