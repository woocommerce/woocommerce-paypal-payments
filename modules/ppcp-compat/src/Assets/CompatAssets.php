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
	 * Whether Germanized synchronization scripts should be loaded.
	 *
	 * @var bool
	 */
	protected $should_enqueue_gzd_scripts;

	/**
	 * Compat module assets constructor.
	 *
	 * @param string $module_url The URL to the module.
	 * @param string $version The assets version.
	 * @param bool   $should_enqueue_gzd_scripts Whether Germanized synchronization scripts should be loaded.
	 */
	public function __construct( string $module_url, string $version, bool $should_enqueue_gzd_scripts ) {
		$this->module_url                 = $module_url;
		$this->version                    = $version;
		$this->should_enqueue_gzd_scripts = $should_enqueue_gzd_scripts;
	}

	/**
	 * Registers the scripts and styles.
	 *
	 * @return void
	 */
	public function register(): void {
		$gzd_sync_enabled = apply_filters( 'woocommerce_paypal_payments_sync_gzd_tracking', true );
		if ( $this->should_enqueue_gzd_scripts && $gzd_sync_enabled ) {
			wp_register_script(
				'ppcp-gzd-compat',
				untrailingslashit( $this->module_url ) . '/assets/js/gzd-compat.js',
				array( 'jquery' ),
				$this->version,
				true
			);
		}
	}

	/**
	 * Enqueues the necessary scripts.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$gzd_sync_enabled = apply_filters( 'woocommerce_paypal_payments_sync_gzd_tracking', true );
		if ( $this->should_enqueue_gzd_scripts && $gzd_sync_enabled ) {
			wp_enqueue_script( 'ppcp-gzd-compat' );
		}
	}
}
