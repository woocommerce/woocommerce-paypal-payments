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

	public const PPCP_TRACKING_INFO_META_NAME = '_ppcp_paypal_tracking_info_meta_name';

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
		$tracking_enabled = $c->get( 'order-tracking.is-module-enabled' );

		$endpoint = $c->get( 'order-tracking.endpoint.controller' );
		assert( $endpoint instanceof OrderTrackingEndpoint );

		add_action( 'wc_ajax_' . OrderTrackingEndpoint::ENDPOINT, array( $endpoint, 'handle_request' ) );

		if ( ! $tracking_enabled ) {
			return;
		}

		$asset_loader = $c->get( 'order-tracking.assets' );
		assert( $asset_loader instanceof OrderEditPageAssets );

		$logger = $c->get( 'woocommerce.logger.woocommerce' );
		assert( $logger instanceof LoggerInterface );

		add_action( 'init', array( $asset_loader, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $asset_loader, 'enqueue' ) );

		$meta_box_renderer = $c->get( 'order-tracking.meta-box.renderer' );
		add_action(
			'add_meta_boxes',
			static function() use ( $meta_box_renderer ) {
				add_meta_box(
					'ppcp_order-tracking',
					__( 'PayPal Shipment Tracking', 'woocommerce-paypal-payments' ),
					array( $meta_box_renderer, 'render' ),
					'shop_order',
					'normal'
				);
			},
			10,
			2
		);
	}
}
