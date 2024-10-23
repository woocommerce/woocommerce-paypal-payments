<?php
/**
 * Settings container class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

/**
 * This class serves as a container for managing the onboarding profile details
 * within the WooCommerce PayPal Commerce plugin. It provides methods to retrieve
 * and save the onboarding profile data using WordPress options.
 */
class OnboardingProfile {
	/**
	 * Options key where profile details are stored.
	 *
	 * @var string
	 */
	private const KEY = 'woocommerce-ppcp-data-onboarding';

	/**
	 * Returns the current onboarding profile details.
	 *
	 * @return array
	 */
	public function get_data() : array {
		return get_option( self::KEY, array() );
	}

	/**
	 * Saves the onboarding profile details.
	 *
	 * @param array $data The profile details to save.
	 */
	public function save_data( array $data ) : void {
		update_option( self::KEY, $data );
	}
}
