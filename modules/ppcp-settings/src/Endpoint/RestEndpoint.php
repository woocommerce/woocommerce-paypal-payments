<?php
/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WC_REST_Controller;

/**
 * Base class for REST controllers in the settings module.
 *
 * This is a base class for specific REST endpoints; do not instantiate this
 * class directly.
 */
class RestEndpoint extends WC_REST_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3/wc_paypal';

	/**
	 * Verify access.
	 *
	 * Override this method if custom permissions required.
	 */
	public function check_permission() : bool {
		return current_user_can( 'manage_woocommerce' );
	}
}
