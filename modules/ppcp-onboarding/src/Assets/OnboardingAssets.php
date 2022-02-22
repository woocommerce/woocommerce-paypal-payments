<?php
/**
 * Registers and enqueues the assets for the Onboarding process.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Assets;

use WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

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
	 * The assets version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * The State.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The Environment.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * The LoginSeller Endpoint.
	 *
	 * @var LoginSellerEndpoint
	 */
	private $login_seller_endpoint;

	/**
	 * ID of the current PPCP gateway settings page, or empty if it is not such page.
	 *
	 * @var string
	 */
	protected $page_id;

	/**
	 * OnboardingAssets constructor.
	 *
	 * @param string              $module_url                         The URL to the module.
	 * @param string              $version                            The assets version.
	 * @param State               $state                               The State object.
	 * @param Environment         $environment  The Environment.
	 * @param LoginSellerEndpoint $login_seller_endpoint The LoginSeller endpoint.
	 * @param string              $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 */
	public function __construct(
		string $module_url,
		string $version,
		State $state,
		Environment $environment,
		LoginSellerEndpoint $login_seller_endpoint,
		string $page_id
	) {

		$this->module_url            = untrailingslashit( $module_url );
		$this->version               = $version;
		$this->state                 = $state;
		$this->environment           = $environment;
		$this->login_seller_endpoint = $login_seller_endpoint;
		$this->page_id               = $page_id;
	}

	/**
	 * Registers the scripts.
	 *
	 * @return bool
	 */
	public function register(): bool {

		$url = untrailingslashit( $this->module_url ) . '/assets/css/onboarding.css';
		wp_register_style(
			'ppcp-onboarding',
			$url,
			array(),
			$this->version
		);
		$url = untrailingslashit( $this->module_url ) . '/assets/js/settings.js';
		wp_register_script(
			'ppcp-settings',
			$url,
			array(),
			$this->version,
			true
		);

		$url = untrailingslashit( $this->module_url ) . '/assets/js/onboarding.js';
		wp_register_script(
			'ppcp-onboarding',
			$url,
			array( 'jquery' ),
			$this->version,
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
			'endpoint'         => home_url( \WC_AJAX::get_endpoint( LoginSellerEndpoint::ENDPOINT ) ),
			'nonce'            => wp_create_nonce( $this->login_seller_endpoint::nonce() ),
			'paypal_js_url'    => 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js',
			'sandbox_state'    => State::get_state_name( $this->state->sandbox_state() ),
			'production_state' => State::get_state_name( $this->state->production_state() ),
			'current_state'    => State::get_state_name( $this->state->current_state() ),
			'current_env'      => $this->environment->current_environment(),
			'error_messages'   => array(
				'no_credentials' => __( 'API credentials must be entered to save the settings.', 'woocommerce-paypal-payments' ),
			),
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
		return PayPalGateway::ID === $this->page_id;
	}
}
