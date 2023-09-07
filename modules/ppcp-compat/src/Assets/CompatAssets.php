<?php
/**
 * Register and configure assets for Compat module.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\Assets;

/**
 * Class OrderEditPageAssets
 */
class CompatAssets {
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
	 * Whether tracking compat scripts should be loaded.
	 *
	 * @var bool
	 */
	protected $should_enqueue_tracking_scripts;

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
	 * Compat module assets constructor.
	 *
	 * @param string $module_url The URL to the module.
	 * @param string $version The assets version.
	 * @param bool   $should_enqueue_tracking_scripts Whether Germanized synchronization scripts should be loaded.
	 * @param bool   $is_gzd_active Whether Germanized plugin is active.
	 * @param bool   $is_wc_shipment_active Whether WC Shipments plugin is active.
	 */
	public function __construct(
		string $module_url,
		string $version,
		bool $should_enqueue_tracking_scripts,
		bool $is_gzd_active,
		bool $is_wc_shipment_active
	) {

		$this->module_url                      = $module_url;
		$this->version                         = $version;
		$this->should_enqueue_tracking_scripts = $should_enqueue_tracking_scripts;
		$this->is_gzd_active                   = $is_gzd_active;
		$this->is_wc_shipment_active           = $is_wc_shipment_active;
	}

	/**
	 * Registers the scripts and styles.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->should_enqueue_tracking_scripts ) {
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
		if ( $this->should_enqueue_tracking_scripts ) {
			wp_enqueue_script( 'ppcp-tracking-compat' );
		}
	}
}
