<?php
/**
 * Register and configure assets for My account PayPal payments page.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting\Assets;

use WooCommerce\PayPalCommerce\Vaulting\Endpoint\DeletePaymentTokenEndpoint;

/**
 * Class MyAccountPaymentsAssets
 */
class MyAccountPaymentsAssets {

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
	 * MyAccountPaymentsAssets constructor.
	 *
	 * @param string $module_url The URL to the module.
	 * @param string $version                            The assets version.
	 */
	public function __construct(
		string $module_url,
		string $version
	) {
		$this->module_url = untrailingslashit( $module_url );
		$this->version    = $version;
	}

	/**
	 * Enqueues the necessary scripts.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		wp_enqueue_script(
			'ppcp-vaulting-myaccount-payments',
			untrailingslashit( $this->module_url ) . '/assets/js/myaccount-payments.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Localize script.
	 */
	public function localize() {
		wp_localize_script(
			'ppcp-vaulting-myaccount-payments',
			'PayPalCommerceGatewayVaulting',
			array(
				'delete' => array(
					'endpoint' => home_url( \WC_AJAX::get_endpoint( DeletePaymentTokenEndpoint::ENDPOINT ) ),
					'nonce'    => wp_create_nonce( DeletePaymentTokenEndpoint::nonce() ),
				),
			)
		);
	}
}
