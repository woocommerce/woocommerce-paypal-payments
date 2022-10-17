<?php
/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Exception;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Vendidero\Germanized\Shipments\Shipment;
use WC_Order;
use WooCommerce\PayPalCommerce\Compat\Assets\CompatAssets;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class CompatModule
 */
class CompatModule implements ModuleInterface {

	/**
	 * Setup the compatibility module.
	 *
	 * @return ServiceProviderInterface
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws NotFoundException
	 */
	public function run( ContainerInterface $c ): void {
		$this->initialize_ppec_compat_layer( $c );
		$this->fix_site_ground_optimizer_compatibility( $c );
		$this->initialize_gzd_compat_layer( $c );

		$asset_loader = $c->get( 'compat.assets' );
		assert( $asset_loader instanceof CompatAssets );

		add_action( 'init', array( $asset_loader, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $asset_loader, 'enqueue' ) );
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}

	/**
	 * Sets up the PayPal Express Checkout compatibility layer.
	 *
	 * @param ContainerInterface $container The Container.
	 * @return void
	 */
	private function initialize_ppec_compat_layer( ContainerInterface $container ): void {
		// Process PPEC subscription renewals through PayPal Payments.
		$handler = $container->get( 'compat.ppec.subscriptions-handler' );
		$handler->maybe_hook();

		// Settings.
		$ppec_import = $container->get( 'compat.ppec.settings_importer' );
		$ppec_import->maybe_hook();

		// Inbox note inviting merchant to disable PayPal Express Checkout.
		add_action(
			'woocommerce_init',
			function() {
				if ( is_callable( array( WC(), 'is_wc_admin_active' ) ) && WC()->is_wc_admin_active() && class_exists( 'Automattic\WooCommerce\Admin\Notes\Notes' ) ) {
					PPEC\DeactivateNote::init();
				}
			}
		);

	}

	/**
	 * Fixes the compatibility issue for <a href="https://wordpress.org/plugins/sg-cachepress/">SiteGround Optimizer plugin</a>.
	 *
	 * @link https://wordpress.org/plugins/sg-cachepress/
	 *
	 * @param ContainerInterface $c The Container.
	 */
	protected function fix_site_ground_optimizer_compatibility( ContainerInterface $c ): void {
		$ppcp_script_names = $c->get( 'compat.plugin-script-names' );
		add_filter(
			'sgo_js_minify_exclude',
			function ( array $scripts ) use ( $ppcp_script_names ) {
				return array_merge( $scripts, $ppcp_script_names );
			}
		);
	}

	/**
	 * Sets up the <a href="https://wordpress.org/plugins/woocommerce-germanized/">Germanized for WooCommerce</a>
	 * plugin compatibility layer.
	 *
	 * @link https://wordpress.org/plugins/woocommerce-germanized/
	 *
	 * @param ContainerInterface $c The Container.
	 * @return void
	 */
	protected function initialize_gzd_compat_layer( ContainerInterface $c ): void {
		if ( ! $c->get( 'compat.should-initialize-gzd-compat-layer' ) ) {
			return;
		}

		$endpoint = $c->get( 'order-tracking.endpoint.controller' );
		assert( $endpoint instanceof OrderTrackingEndpoint );

		$logger = $c->get( 'woocommerce.logger.woocommerce' );
		assert( $logger instanceof LoggerInterface );

		$status_map = $c->get( 'compat.gzd.tracking_statuses_map' );

		add_action(
			'woocommerce_gzd_shipment_after_save',
			static function( Shipment $shipment ) use ( $endpoint, $logger, $status_map ) {
				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_gzd_tracking', true ) ) {
					return;
				}

				$gzd_shipment_status = $shipment->get_status();
				if ( ! array_key_exists( $gzd_shipment_status, $status_map ) ) {
					return;
				}

				$wc_order = $shipment->get_order();
				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return;
				}

				$transaction_id = $wc_order->get_transaction_id();
				if ( empty( $transaction_id ) ) {
					return;
				}

				$tracking_data = array(
					'transaction_id' => $transaction_id,
					'status'         => (string) $status_map[ $gzd_shipment_status ],
				);

				$provider = $shipment->get_shipping_provider();
				if ( ! empty( $provider ) && $provider !== 'none' ) {
					$tracking_data['carrier'] = 'DHL_DEUTSCHE_POST';
				}

				try {
					$tracking_information = $endpoint->get_tracking_information( $wc_order->get_id() );

					$tracking_data['tracking_number'] = $tracking_information['tracking_number'] ?? '';

					if ( $shipment->has_tracking() ) {
						$tracking_data['tracking_number'] = $shipment->get_tracking_id();
					}

					! $tracking_information ? $endpoint->add_tracking_information( $tracking_data, $wc_order->get_id() ) : $endpoint->update_tracking_information( $tracking_data, $wc_order->get_id() );
				} catch ( Exception $exception ) {
					$logger->error( "Couldn't sync tracking information: " . $exception->getMessage() );
					$shipment->add_note( "Couldn't sync tracking information: " . $exception->getMessage() );
					throw $exception;
				}
			}
		);
	}
}
