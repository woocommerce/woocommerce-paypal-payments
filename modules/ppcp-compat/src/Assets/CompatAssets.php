<?php
/**
 * Register and configure assets for Compat module.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\OrderTracking\TrackingAvailabilityTrait;

/**
 * Class OrderEditPageAssets
 */
class CompatAssets {

	use TrackingAvailabilityTrait;

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
	 * Whether Germanized plugin is active.
	 *
	 * @var bool
	 */
	protected $is_gzd_active;

	/**
	 * Whether WC Shipments plugin is active
	 *
	 * @var bool
	 */
	protected $is_wc_shipment_active;

	/**
	 * The bearer.
	 *
	 * @var Bearer
	 */
	protected $bearer;

	/**
	 * Compat module assets constructor.
	 *
	 * @param string $module_url The URL to the module.
	 * @param string $version The assets version.
	 * @param bool   $is_gzd_active Whether Germanized plugin is active.
	 * @param bool   $is_wc_shipment_active Whether WC Shipments plugin is active.
	 * @param Bearer $bearer The bearer.
	 */
	public function __construct(
		string $module_url,
		string $version,
		bool $is_gzd_active,
		bool $is_wc_shipment_active,
		Bearer $bearer
	) {

		$this->module_url            = $module_url;
		$this->version               = $version;
		$this->is_gzd_active         = $is_gzd_active;
		$this->is_wc_shipment_active = $is_wc_shipment_active;
		$this->bearer                = $bearer;
	}

	/**
	 * Registers the scripts and styles.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->is_tracking_enabled( $this->bearer ) ) {
			wp_register_script(
				'ppcp-tracking-compat',
				untrailingslashit( $this->module_url ) . '/assets/js/tracking-compat.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_localize_script(
				'ppcp-tracking-compat',
				'PayPalCommerceGatewayOrderTrackingCompat',
				array(
					'gzd_sync_enabled'         => apply_filters( 'woocommerce_paypal_payments_sync_gzd_tracking', true ) && $this->is_gzd_active,
					'wc_shipment_sync_enabled' => apply_filters( 'woocommerce_paypal_payments_sync_wc_shipment_tracking', true ) && $this->is_wc_shipment_active,
				)
			);
		}
	}

	/**
	 * Enqueues the necessary scripts.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( $this->is_tracking_enabled( $this->bearer ) ) {
			wp_enqueue_script( 'ppcp-tracking-compat' );
		}
	}
}
