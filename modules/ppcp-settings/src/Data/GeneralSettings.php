<?php
/**
 * General plugin settings class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;

/**
 * Class GeneralSettings
 *
 * This class serves as a container for managing the common settings that
 * are used and managed in various areas of the settings UI
 *
 * Those settings mainly describe connection details and are initially collected
 * in the onboarding wizard, and also appear in the settings screen.
 */
class GeneralSettings extends AbstractDataModel {

	/**
	 * Option key where profile details are stored.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'woocommerce-ppcp-data-common';

	/**
	 * List of customization flags, provided by the server (read-only).
	 *
	 * @var array
	 */
	protected array $woo_settings = array();

	/**
	 * Constructor.
	 *
	 * @param string $country  WooCommerce store country.
	 * @param string $currency WooCommerce store currency.
	 *
	 * @throws RuntimeException When forgetting to define the OPTION_KEY in this class.
	 */
	public function __construct( string $country, string $currency ) {
		parent::__construct();

		$this->woo_settings['country']  = $country;
		$this->woo_settings['currency'] = $currency;

		$this->data['merchant_connected'] = $this->is_merchant_connected();
	}

	/**
	 * Get default values for the model.
	 *
	 * @return array
	 */
	protected function get_defaults() : array {
		return array(
			'use_sandbox'           => false, // UI state, not a connection detail.
			'use_manual_connection' => false, // UI state, not a connection detail.

			// Details about connected merchant account.
			'merchant_connected'    => false,
			'sandbox_merchant'      => false,
			'merchant_id'           => '',
			'merchant_email'        => '',
			'client_id'             => '',
			'client_secret'         => '',
		);
	}

	// -----

	/**
	 * Gets the 'use sandbox' setting.
	 *
	 * @return bool
	 */
	public function get_sandbox() : bool {
		return (bool) $this->data['use_sandbox'];
	}

	/**
	 * Sets the 'use sandbox' setting.
	 *
	 * @param bool $use_sandbox Whether to use sandbox mode.
	 */
	public function set_sandbox( bool $use_sandbox ) : void {
		$this->data['use_sandbox'] = $use_sandbox;
	}

	/**
	 * Gets the 'use manual connection' setting.
	 *
	 * @return bool
	 */
	public function get_manual_connection() : bool {
		return (bool) $this->data['use_manual_connection'];
	}

	/**
	 * Sets the 'use manual connection' setting.
	 *
	 * @param bool $use_manual_connection Whether to use manual connection.
	 */
	public function set_manual_connection( bool $use_manual_connection ) : void {
		$this->data['use_manual_connection'] = $use_manual_connection;
	}

	/**
	 * Returns the list of read-only customization flags.
	 *
	 * @return array
	 */
	public function get_woo_settings() : array {
		return $this->woo_settings;
	}

	/**
	 * Setter to update details of the connected merchant account.
	 *
	 * @param MerchantConnectionDTO $connection Connection details.
	 *
	 * @return void
	 */
	public function set_merchant_data( MerchantConnectionDTO $connection ) : void {
		$this->data['sandbox_merchant']   = $connection->is_sandbox;
		$this->data['merchant_id']        = sanitize_text_field( $connection->merchant_id );
		$this->data['merchant_email']     = sanitize_email( $connection->merchant_email );
		$this->data['client_id']          = sanitize_text_field( $connection->client_id );
		$this->data['client_secret']      = sanitize_text_field( $connection->client_secret );
		$this->data['merchant_connected'] = $this->is_merchant_connected();
	}

	/**
	 * Returns the full merchant connection DTO for the current connection.
	 *
	 * @return MerchantConnectionDTO All connection details.
	 */
	public function get_merchant_data() : MerchantConnectionDTO {
		return new MerchantConnectionDTO(
			$this->is_sandbox_merchant(),
			$this->data['client_id'],
			$this->data['client_secret'],
			$this->data['merchant_id'],
			$this->data['merchant_email']
		);
	}

	/**
	 * Reset all connection details to the initial, disconnected state.
	 *
	 * @return void
	 */
	public function reset_merchant_data() : void {
		$defaults = $this->get_defaults();

		$this->data['sandbox_merchant']   = $defaults['sandbox_merchant'];
		$this->data['merchant_id']        = $defaults['merchant_id'];
		$this->data['merchant_email']     = $defaults['merchant_email'];
		$this->data['client_id']          = $defaults['client_id'];
		$this->data['client_secret']      = $defaults['client_secret'];
		$this->data['merchant_connected'] = false;
	}

	/**
	 * Whether the currently connected merchant is a sandbox account.
	 *
	 * @return bool
	 */
	public function is_sandbox_merchant() : bool {
		return $this->data['sandbox_merchant'];
	}

	/**
	 * Whether the merchant successfully logged into their PayPal account.
	 *
	 * @return bool
	 */
	public function is_merchant_connected() : bool {
		return $this->data['merchant_email']
			&& $this->data['merchant_id']
			&& $this->data['client_id']
			&& $this->data['client_secret'];
	}

	/**
	 * Gets the currently connected merchant ID.
	 *
	 * @return string
	 */
	public function get_merchant_id() : string {
		return $this->data['merchant_id'];
	}

	/**
	 * Gets the currently connected merchant's email.
	 *
	 * @return string
	 */
	public function get_merchant_email() : string {
		return $this->data['merchant_email'];
	}
}
