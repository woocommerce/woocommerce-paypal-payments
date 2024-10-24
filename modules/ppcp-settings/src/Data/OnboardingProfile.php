<?php
/**
 * Onboarding Profile class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

/**
 * Class OnboardingProfile
 *
 * This class serves as a container for managing the onboarding profile details
 * within the WooCommerce PayPal Commerce plugin. It provides methods to retrieve
 * and save the onboarding profile data using WordPress options.
 */
class OnboardingProfile extends AbstractDataModel {

	/**
	 * Option key where profile details are stored.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'woocommerce-ppcp-data-onboarding';

	/**
	 * Get default values for the model.
	 *
	 * @return array
	 */
	protected function get_defaults() : array {
		return array(
			'step'                  => 0,
			'use_sandbox'           => false,
			'use_manual_connection' => false,
			'client_id'             => '',
			'client_secret'         => '',
		);
	}

	// -----

	/**
	 * Gets the 'step' setting.
	 *
	 * @return int
	 */
	public function get_step() : int {
		return (int) $this->data['step'];
	}

	/**
	 * Sets the 'step' setting.
	 *
	 * @param int $step Whether to use sandbox mode.
	 */
	public function set_step( int $step ) : void {
		$this->data['step'] = $step;
	}

	/**
	 * Gets the 'use sandbox' setting.
	 *
	 * @return bool
	 */
	public function get_use_sandbox() : bool {
		return (bool) $this->data['use_sandbox'];
	}

	/**
	 * Sets the 'use sandbox' setting.
	 *
	 * @param bool $use_sandbox Whether to use sandbox mode.
	 */
	public function set_use_sandbox( bool $use_sandbox ) : void {
		$this->data['use_sandbox'] = $use_sandbox;
	}

	/**
	 * Gets the 'use manual connection' setting.
	 *
	 * @return bool
	 */
	public function get_use_manual_connection() : bool {
		return (bool) $this->data['use_manual_connection'];
	}

	/**
	 * Sets the 'use manual connection' setting.
	 *
	 * @param bool $use_manual_connection Whether to use manual connection.
	 */
	public function set_use_manual_connection( bool $use_manual_connection ) : void {
		$this->data['use_manual_connection'] = $use_manual_connection;
	}

	/**
	 * Gets the client ID.
	 *
	 * @return string
	 */
	public function get_client_id() : string {
		return $this->data['client_id'];
	}

	/**
	 * Sets the client ID.
	 *
	 * @param string $client_id The client ID.
	 */
	public function set_client_id( string $client_id ) : void {
		$this->data['client_id'] = sanitize_text_field( $client_id );
	}

	/**
	 * Gets the client secret.
	 *
	 * @return string
	 */
	public function get_client_secret() : string {
		return $this->data['client_secret'];
	}

	/**
	 * Sets the client secret.
	 *
	 * @param string $client_secret The client secret.
	 */
	public function set_client_secret( string $client_secret ) : void {
		$this->data['client_secret'] = sanitize_text_field( $client_secret );
	}
}
