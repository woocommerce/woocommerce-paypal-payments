<?php
/**
 * The order tracking module.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use Exception;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\OrderTracking\Assets\OrderEditPageAssets;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceHelper;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;

/**
 * Class OrderTrackingModule
 */
class OrderTrackingModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
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
	 * @param ContainerInterface $c A services container instance.
	 * @throws NotFoundException
	 */
	public function run( ContainerInterface $c ): void {
		$settings = $c->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		$pui_helper = $c->get( 'wcgateway.pay-upon-invoice-helper' );
		assert( $pui_helper instanceof PayUponInvoiceHelper );

		if ( $pui_helper->is_pui_gateway_enabled() ) {
			$settings->set( 'tracking_enabled', true );
			$settings->persist();
		}

		$tracking_enabled = $settings->has( 'tracking_enabled' ) && $settings->get( 'tracking_enabled' );

		if ( ! $tracking_enabled ) {
			return;
		}

		$asset_loader = $c->get( 'order-tracking.assets' );
		assert( $asset_loader instanceof OrderEditPageAssets );
		$is_paypal_order_edit_page = $c->get( 'order-tracking.is-paypal-order-edit-page' );

		$endpoint = $c->get( 'order-tracking.endpoint.controller' );
		assert( $endpoint instanceof OrderTrackingEndpoint );

		$logger = $c->get( 'woocommerce.logger.woocommerce' );
		assert( $logger instanceof LoggerInterface );

		add_action(
			'init',
			static function () use ( $asset_loader, $is_paypal_order_edit_page ) {
				if ( ! $is_paypal_order_edit_page ) {
					return;
				}

				$asset_loader->register();
			}
		);

		add_action(
			'admin_enqueue_scripts',
			static function () use ( $asset_loader, $is_paypal_order_edit_page ) {
				if ( ! $is_paypal_order_edit_page ) {
					return;
				}

				$asset_loader->enqueue();
			}
		);

		add_action(
			'wc_ajax_' . OrderTrackingEndpoint::ENDPOINT,
			array( $endpoint, 'handle_request' )
		);

		$meta_box_renderer = $c->get( 'order-tracking.meta-box.renderer' );
		add_action(
			'add_meta_boxes',
			static function() use ( $meta_box_renderer, $is_paypal_order_edit_page ) {
				if ( ! $is_paypal_order_edit_page ) {
					return;
				}

				add_meta_box( 'ppcp_order-tracking', __( 'Tracking Information', 'woocommerce-paypal-payments' ), array( $meta_box_renderer, 'render' ), 'shop_order', 'side' );
			},
			10,
			2
		);

		add_action(
			'woocommerce_order_status_completed',
			static function( int $order_id ) use ( $endpoint, $logger ) {
				$tracking_information = $endpoint->get_tracking_information( $order_id );

				if ( $tracking_information ) {
					return;
				}

				$wc_order = wc_get_order( $order_id );
				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return;
				}

				$transaction_id = $wc_order->get_transaction_id();
				if ( empty( $transaction_id ) ) {
					return;
				}

				$tracking_data = array(
					'transaction_id' => $transaction_id,
					'status'         => 'SHIPPED',
				);

				try {
					$endpoint->add_tracking_information( $tracking_data, $order_id );
				} catch ( Exception $exception ) {
					$logger->error( "Couldn't create tracking information: " . $exception->getMessage() );
					throw $exception;
				}
			}
		);
	}
}
