<?php
/**
 * Registers and enqueues the assets for the Onboarding process.
 *
 * @package Inpsyde\PayPalCommerce\Onboarding\Assets
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Assets;

use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\State;

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

		$this->module_url            = $module_url;
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
		if ( ! $this->should_render_onboarding_script() ) {
			return false;
		}

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
			array(
				'endpoint' => home_url( \WC_AJAX::get_endpoint( LoginSellerEndpoint::ENDPOINT ) ),
				'nonce'    => wp_create_nonce( $this->login_seller_endpoint::nonce() ),
				'error'    => __(
					'We could not properly onboard you. Please reload and try again.',
					'paypal-for-woocommerce'
				),
			)
		);

		return true;
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
		if ( 'ppcp-gateway' !== $current_section ) {
			return false;
		}

		$should_render = $this->state->current_state() === State::STATE_PROGRESSIVE;
		return $should_render;
	}
}
