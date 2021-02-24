<?php
/**
 * Registers and enqueues the assets for the Onboarding process.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Assets;

use WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\State;

/**
 * Class OnboardingAssets
 */
class OnboardingAssets {

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The State.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The LoginSeller Endpoint.
	 *
	 * @var LoginSellerEndpoint
	 */
	private $login_seller_endpoint;

	/**
	 * OnboardingAssets constructor.
	 *
	 * @param string              $module_url                         The URL to the module.
	 * @param State               $state                               The State object.
	 * @param LoginSellerEndpoint $login_seller_endpoint The LoginSeller endpoint.
	 */
	public function __construct(
		string $module_url,
		State $state,
		LoginSellerEndpoint $login_seller_endpoint
	) {

		$this->module_url            = untrailingslashit( $module_url );
		$this->state                 = $state;
		$this->login_seller_endpoint = $login_seller_endpoint;
	}

	/**
	 * Registers the scripts.
	 *
	 * @return bool
	 */
	public function register(): bool {

		$url = $this->module_url . '/assets/css/onboarding.css';
		wp_register_style(
			'ppcp-onboarding',
			$url,
			array(),
			1
		);
		$url = $this->module_url . '/assets/js/settings.js';
		wp_register_script(
			'ppcp-settings',
			$url,
			array(),
			1,
			true
		);

		$url = $this->module_url . '/assets/js/onboarding.js';
		wp_register_script(
			'ppcp-onboarding',
			$url,
			array( 'jquery' ),
			1,
			true
		);
		wp_localize_script(
			'ppcp-onboarding',
			'PayPalCommerceGatewayOnboarding',
			$this->get_script_data()
		);

		return true;
	}

	/**
	 * Returns the data associated to the onboarding script.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'endpoint'      => home_url( \WC_AJAX::get_endpoint( LoginSellerEndpoint::ENDPOINT ) ),
			'nonce'         => wp_create_nonce( $this->login_seller_endpoint::nonce() ),
			'paypal_js_url' => 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js',
		);
	}

	/**
	 * Enqueues the necessary scripts.
	 *
	 * @return bool
	 */
	public function enqueue(): bool {
		wp_enqueue_style( 'ppcp-onboarding' );
		wp_enqueue_script( 'ppcp-settings' );
		if ( ! $this->should_render_onboarding_script() ) {
			return false;
		}

		wp_enqueue_script( 'ppcp-onboarding' );
		return true;
	}

	/**
	 * Whether the onboarding script should be rendered or not.
	 *
	 * @return bool
	 */
	private function should_render_onboarding_script(): bool {
		global $current_section;
		return 'ppcp-gateway' === $current_section;
	}
}
