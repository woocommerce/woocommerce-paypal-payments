<?php
/**
 * Removes PayPal connection data from a Blueprint export payload.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

/**
 * Single source of truth for what counts as "connection data" in a Blueprint export.
 */
class ConnectionDataSanitizer {

	/**
	 * Option holding the merchant connection details.
	 */
	public const OPTION_COMMON = 'woocommerce-ppcp-data-common';

	/**
	 * Option holding the onboarding progress flags.
	 */
	public const OPTION_ONBOARDING = 'woocommerce-ppcp-data-onboarding';

	/**
	 * Connection keys of the common option, mapped to their neutral value.
	 *
	 * Mirrors GeneralSettings::get_defaults(), which is protected; the two are kept
	 * in sync by ConnectionDataSanitizerTest.
	 *
	 * @var array<string, mixed>
	 */
	public const CONNECTION_DEFAULTS = array(
		'client_id'             => '',
		'client_secret'         => '',
		'merchant_id'           => '',
		'merchant_email'        => '',

		// Not reliably recomputed on connect, so a stale value would persist.
		'merchant_country'      => '',
		'wc_installation_path'  => '',

		'seller_type'           => 'unknown',
		'sandbox_merchant'      => false,
		'merchant_connected'    => false,
		'use_sandbox'           => false,
		'use_manual_connection' => false,
	);

	/**
	 * Onboarding flags reset alongside the connection data.
	 *
	 * `setup_done` is deliberately NOT reset: it guards
	 * SettingsDataManager::set_defaults_for_new_merchant(), which would overwrite
	 * the styling settings this export exists to carry.
	 *
	 * @var array<string, mixed>
	 */
	public const ONBOARDING_DEFAULTS = array(
		'completed'          => false,
		'step'               => 0,
		'gateways_synced'    => false,
		'gateways_refreshed' => false,
	);

	/**
	 * Returns a copy of the export payload with all connection data neutralised.
	 *
	 * @param array<string, mixed> $options Option name => option value.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $options ): array {
		if ( isset( $options[ self::OPTION_COMMON ] ) && is_array( $options[ self::OPTION_COMMON ] ) ) {
			$options[ self::OPTION_COMMON ] = $this->reset_present_keys(
				$options[ self::OPTION_COMMON ],
				self::CONNECTION_DEFAULTS
			);
		}

		if ( isset( $options[ self::OPTION_ONBOARDING ] ) && is_array( $options[ self::OPTION_ONBOARDING ] ) ) {
			$options[ self::OPTION_ONBOARDING ] = $this->reset_present_keys(
				$options[ self::OPTION_ONBOARDING ],
				self::ONBOARDING_DEFAULTS
			);
		}

		return $options;
	}

	/**
	 * Overwrites the given keys with their neutral values, skipping keys the
	 * payload does not contain.
	 *
	 * @param array<string, mixed> $data     Stored option value.
	 * @param array<string, mixed> $defaults Key => neutral value.
	 * @return array<string, mixed>
	 */
	private function reset_present_keys( array $data, array $defaults ): array {
		foreach ( $defaults as $key => $neutral_value ) {
			if ( array_key_exists( $key, $data ) ) {
				$data[ $key ] = $neutral_value;
			}
		}

		return $data;
	}
}
