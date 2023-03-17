<?php
/**
 * Register and configure assets for webhooks status page.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Status\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Status\Assets;

use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulationStateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;

/**
 * Class WebhooksStatusPageAssets
 */
class WebhooksStatusPageAssets {

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
	 * The environment object.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * WebhooksStatusPageAssets constructor.
	 *
	 * @param string      $module_url  The URL to the module.
	 * @param string      $version     The assets version.
	 * @param Environment $environment The environment object.
	 */
	public function __construct(
		string $module_url,
		string $version,
		Environment $environment
	) {
		$this->module_url  = untrailingslashit( $module_url );
		$this->version     = $version;
		$this->environment = $environment;
	}

	/**
	 * Registers the scripts and styles.
	 *
	 * @return void
	 */
	public function register(): void {
		wp_register_style(
			'ppcp-webhooks-status-page-style',
			untrailingslashit( $this->module_url ) . '/assets/css/status-page.css',
			array(),
			$this->version
		);

		wp_register_script(
			'ppcp-webhooks-status-page',
			untrailingslashit( $this->module_url ) . '/assets/js/status-page.js',
			array(),
			$this->version,
			true
		);

		wp_localize_script(
			'ppcp-webhooks-status-page',
			'PayPalCommerceGatewayWebhooksStatus',
			$this->get_script_data()
		);
	}

	/**
	 * Returns the data for the script.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'resubscribe' => array(
				'endpoint'       => \WC_AJAX::get_endpoint( ResubscribeEndpoint::ENDPOINT ),
				'nonce'          => wp_create_nonce( ResubscribeEndpoint::nonce() ),
				'button'         => '.ppcp-webhooks-resubscribe',
				'failureMessage' => __( 'Operation failed. Check WooCommerce logs for more details.', 'woocommerce-paypal-payments' ),
			),
			'simulation'  => array(
				'start' => array(
					'endpoint'       => \WC_AJAX::get_endpoint( SimulateEndpoint::ENDPOINT ),
					'nonce'          => wp_create_nonce( SimulateEndpoint::nonce() ),
					'button'         => '.ppcp-webhooks-simulate',
					'failureMessage' => __( 'Operation failed. Check WooCommerce logs for more details.', 'woocommerce-paypal-payments' ),
				),
				'state' => array(
					'endpoint'            => \WC_AJAX::get_endpoint( SimulationStateEndpoint::ENDPOINT ),
					'successState'        => WebhookSimulation::STATE_RECEIVED,
					'waitingMessage'      => __( 'Waiting for the webhook to arrive...', 'woocommerce-paypal-payments' ),
					'successMessage'      => __( 'The webhook was received successfully.', 'woocommerce-paypal-payments' ),
					'tooLongDelayMessage' => __( 'Looks like the webhook cannot be received. Check that your website is accessible from the internet.', 'woocommerce-paypal-payments' ),
				),
			),
			'environment' => $this->environment->current_environment(),
		);
	}

	/**
	 * Enqueues the necessary scripts.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		wp_enqueue_style( 'ppcp-webhooks-status-page-style' );
		wp_enqueue_script( 'ppcp-webhooks-status-page' );
	}
}
