<?php
/**
 * Register and configure assets for webhooks status page.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Status\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;

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
	 * WebhooksStatusPageAssets constructor.
	 *
	 * @param string $module_url                         The URL to the module.
	 */
	public function __construct(
		string $module_url
	) {
		$this->module_url = untrailingslashit( $module_url );
	}

	/**
	 * Registers the scripts and styles.
	 *
	 * @return void
	 */
	public function register(): void {
		wp_register_style(
			'ppcp-webhooks-status-page-style',
			$this->module_url . '/assets/css/status-page.css',
			array(),
			1
		);

		wp_register_script(
			'ppcp-webhooks-status-page',
			$this->module_url . '/assets/js/status-page.js',
			array(),
			1,
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
				'endpoint'       => home_url( \WC_AJAX::get_endpoint( ResubscribeEndpoint::ENDPOINT ) ),
				'nonce'          => wp_create_nonce( ResubscribeEndpoint::nonce() ),
				'button'         => '.ppcp-webhooks-resubscribe',
				'failureMessage' => __( 'Operation failed. Check WooCommerce logs for more details.', 'woocommerce-paypal-payments' ),
			),
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
