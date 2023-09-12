<?php
/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use Vendidero\Germanized\Shipments\ShipmentItem;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use Exception;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Vendidero\Germanized\Shipments\Shipment;
use WC_Order;
use WooCommerce\PayPalCommerce\Compat\Assets\CompatAssets;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;

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
		$this->initialize_tracking_compat_layer( $c );

		$asset_loader = $c->get( 'compat.assets' );
		assert( $asset_loader instanceof CompatAssets );

		add_action( 'init', array( $asset_loader, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $asset_loader, 'enqueue' ) );

		$this->migrate_pay_later_settings( $c );
		$this->migrate_smart_button_settings( $c );

		$this->fix_page_builders();
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
	 * Sets up the 3rd party plugins compatibility layer for PayPal tracking.
	 *
	 * @param ContainerInterface $c The Container.
	 * @return void
	 */
	protected function initialize_tracking_compat_layer( ContainerInterface $c ): void {
		$is_gzd_active                  = $c->get( 'compat.gzd.is_supported_plugin_version_active' );
		$is_wc_shipment_tracking_active = $c->get( 'compat.wc_shipment_tracking.is_supported_plugin_version_active' );
		$is_ywot_active                 = $c->get( 'compat.ywot.is_supported_plugin_version_active' );

		if ( $is_gzd_active ) {
			$this->initialize_gzd_compat_layer( $c );
		}

		if ( $is_wc_shipment_tracking_active ) {
			$this->initialize_wc_shipment_tracking_compat_layer( $c );
		}

		if ( $is_ywot_active ) {
			$this->initialize_ywot_compat_layer( $c );
		}
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
		add_action(
			'woocommerce_gzd_shipment_status_shipped',
			function( int $shipment_id, Shipment $shipment ) use ( $c ) {
				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_gzd_tracking', true ) ) {
					return;
				}

				$wc_order = $shipment->get_order();

				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return;
				}

				$order_id        = $wc_order->get_id();
				$transaction_id  = $wc_order->get_transaction_id();
				$tracking_number = $shipment->get_tracking_id();
				$carrier         = $shipment->get_shipping_provider();
				$items           = array_map(
					function ( ShipmentItem $item ): int {
						return $item->get_order_item_id();
					},
					$shipment->get_items()
				);

				if ( ! $tracking_number || ! $carrier || ! $transaction_id ) {
					return;
				}

				$this->create_tracking( $c, $order_id, $transaction_id, $tracking_number, $carrier, $items );
			},
			500,
			2
		);
	}

	/**
	 * Sets up the <a href="https://woocommerce.com/document/shipment-tracking/">Shipment Tracking</a>
	 * plugin compatibility layer.
	 *
	 * @link https://woocommerce.com/document/shipment-tracking/
	 *
	 * @param ContainerInterface $c The Container.
	 * @return void
	 */
	protected function initialize_wc_shipment_tracking_compat_layer( ContainerInterface $c ): void {
		add_action(
			'wp_ajax_wc_shipment_tracking_save_form',
			function() use ( $c ) {
				check_ajax_referer( 'create-tracking-item', 'security', true );

				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_wc_shipment_tracking', true ) ) {
					return;
				}

				$order_id = (int) wc_clean( wp_unslash( $_POST['order_id'] ?? '' ) );
				$wc_order = wc_get_order( $order_id );
				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return;
				}

				$transaction_id  = $wc_order->get_transaction_id();
				$tracking_number = wc_clean( wp_unslash( $_POST['tracking_number'] ?? '' ) );
				$carrier         = wc_clean( wp_unslash( $_POST['tracking_provider'] ?? '' ) );
				$carrier_other   = wc_clean( wp_unslash( $_POST['custom_tracking_provider'] ?? '' ) );
				$carrier         = $carrier ?: $carrier_other ?: '';

				if ( ! $tracking_number || ! is_string( $tracking_number ) || ! $carrier || ! is_string( $carrier ) || ! $transaction_id ) {
					return;
				}

				$this->create_tracking( $c, $order_id, $transaction_id, $tracking_number, $carrier, array() );
			}
		);

		add_filter(
			'woocommerce_rest_prepare_order_shipment_tracking',
			function( WP_REST_Response $response, array $tracking_item, WP_REST_Request $request ) use ( $c ): WP_REST_Response {
				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_wc_shipment_tracking', true ) ) {
					return $response;
				}

				$callback = $request->get_attributes()['callback']['1'] ?? '';
				if ( $callback !== 'create_item' ) {
					return $response;
				}

				$order_id = $tracking_item['order_id'] ?? 0;
				$wc_order = wc_get_order( $order_id );
				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return $response;
				}

				$transaction_id  = $wc_order->get_transaction_id();
				$tracking_number = $tracking_item['tracking_number'] ?? '';
				$carrier         = $tracking_item['tracking_provider'] ?? '';
				$carrier_other   = $tracking_item['custom_tracking_provider'] ?? '';
				$carrier         = $carrier ?: $carrier_other ?: '';

				if ( ! $tracking_number || ! $carrier || ! $transaction_id ) {
					return $response;
				}

				$this->create_tracking( $c, $order_id, $transaction_id, $tracking_number, $carrier, array() );

				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Sets up the <a href="https://wordpress.org/plugins/yith-woocommerce-order-tracking/">YITH WooCommerce Order & Shipment Tracking</a>
	 * plugin compatibility layer.
	 *
	 * @link https://wordpress.org/plugins/yith-woocommerce-order-tracking/
	 *
	 * @param ContainerInterface $c The Container.
	 * @return void
	 */
	protected function initialize_ywot_compat_layer( ContainerInterface $c ): void {
		add_action(
			'woocommerce_process_shop_order_meta',
			function( int $order_id ) use ( $c ) {
				if ( ! apply_filters( 'woocommerce_paypal_payments_sync_ywot_tracking', true ) ) {
					return;
				}

				$wc_order = wc_get_order( $order_id );
				if ( ! is_a( $wc_order, WC_Order::class ) ) {
					return;
				}

				$transaction_id = $wc_order->get_transaction_id();
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$tracking_number = wc_clean( wp_unslash( $_POST['ywot_tracking_code'] ?? '' ) );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$carrier = wc_clean( wp_unslash( $_POST['ywot_carrier_name'] ?? '' ) );

				if ( ! $tracking_number || ! is_string( $tracking_number ) || ! $carrier || ! is_string( $carrier ) || ! $transaction_id ) {
					return;
				}

				$this->create_tracking( $c, $order_id, $transaction_id, $tracking_number, $carrier, array() );
			},
			500,
			1
		);
	}

	/**
	 * Creates PayPal tracking.
	 *
	 * @param ContainerInterface $c The Container.
	 * @param int                $wc_order_id The WC order ID.
	 * @param string             $transaction_id The transaction ID.
	 * @param string             $tracking_number The tracking number.
	 * @param string             $carrier The shipment carrier.
	 * @param int[]              $line_items The list of shipment line item IDs.
	 * @return void
	 */
	protected function create_tracking(
		ContainerInterface $c,
		int $wc_order_id,
		string $transaction_id,
		string $tracking_number,
		string $carrier,
		array $line_items
	) {
		$endpoint = $c->get( 'order-tracking.endpoint.controller' );
		assert( $endpoint instanceof OrderTrackingEndpoint );

		$logger = $c->get( 'woocommerce.logger.woocommerce' );
		assert( $logger instanceof LoggerInterface );

		$shipment_factory = $c->get( 'order-tracking.shipment.factory' );
		assert( $shipment_factory instanceof ShipmentFactoryInterface );

		try {
			$ppcp_shipment = $shipment_factory->create_shipment(
				$wc_order_id,
				$transaction_id,
				$tracking_number,
				'SHIPPED',
				'OTHER',
				$carrier,
				$line_items
			);

			$tracking_information = $endpoint->get_tracking_information( $wc_order_id, $tracking_number );

			$tracking_information
				? $endpoint->update_tracking_information( $ppcp_shipment, $wc_order_id )
				: $endpoint->add_tracking_information( $ppcp_shipment, $wc_order_id );

		} catch ( Exception $exception ) {
			$logger->error( "Couldn't sync tracking information: " . $exception->getMessage() );
		}
	}

	/**
	 * Migrates the old Pay Later button and messaging settings for new Pay Later Tab.
	 *
	 * The migration will be done on plugin upgrade if it hasn't already done.
	 *
	 * @param ContainerInterface $c The Container.
	 * @throws NotFoundException When setting was not found.
	 */
	protected function migrate_pay_later_settings( ContainerInterface $c ): void {
		$is_pay_later_settings_migrated_option_name = 'woocommerce_ppcp-is_pay_later_settings_migrated';
		$is_pay_later_settings_migrated             = get_option( $is_pay_later_settings_migrated_option_name );

		if ( $is_pay_later_settings_migrated ) {
			return;
		}

		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			function () use ( $c, $is_pay_later_settings_migrated_option_name ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				$disable_funding = $settings->has( 'disable_funding' ) ? $settings->get( 'disable_funding' ) : array();

				$available_messaging_locations = array_keys( $c->get( 'wcgateway.settings.pay-later.messaging-locations' ) );
				$available_button_locations    = array_keys( $c->get( 'wcgateway.button.locations' ) );

				if ( in_array( 'credit', $disable_funding, true ) ) {
					$settings->set( 'pay_later_button_enabled', false );
				} else {
					$settings->set( 'pay_later_button_enabled', true );
					$selected_button_locations = $this->selected_locations( $settings, $available_button_locations, 'button' );
					if ( ! empty( $selected_button_locations ) ) {
						$settings->set( 'pay_later_button_locations', $selected_button_locations );
					}
				}

				$selected_messaging_locations = $this->selected_locations( $settings, $available_messaging_locations, 'message' );

				if ( ! empty( $selected_messaging_locations ) ) {
					$settings->set( 'pay_later_messaging_enabled', true );
					$settings->set( 'pay_later_messaging_locations', $selected_messaging_locations );
					$settings->set( 'pay_later_enable_styling_per_messaging_location', true );

					foreach ( $selected_messaging_locations as $location ) {
						$this->migrate_message_styling_settings_by_location( $settings, $location );
					}
				} else {
					$settings->set( 'pay_later_messaging_enabled', false );
				}

				$settings->persist();

				update_option( $is_pay_later_settings_migrated_option_name, true );
			}
		);
	}

	/**
	 * Migrates the messages styling setting by given location.
	 *
	 * @param Settings $settings The settings.
	 * @param string   $location The location.
	 * @throws NotFoundException When setting was not found.
	 */
	protected function migrate_message_styling_settings_by_location( Settings $settings, string $location ): void {

		$old_location = $location === 'checkout' ? '' : "_{$location}";

		$layout        = $settings->has( "message{$old_location}_layout" ) ? $settings->get( "message{$old_location}_layout" ) : 'text';
		$logo_type     = $settings->has( "message{$old_location}_logo" ) ? $settings->get( "message{$old_location}_logo" ) : 'primary';
		$logo_position = $settings->has( "message{$old_location}_position" ) ? $settings->get( "message{$old_location}_position" ) : 'left';
		$text_color    = $settings->has( "message{$old_location}_color" ) ? $settings->get( "message{$old_location}_color" ) : 'black';
		$style_color   = $settings->has( "message{$old_location}_flex_color" ) ? $settings->get( "message{$old_location}_flex_color" ) : 'blue';
		$ratio         = $settings->has( "message{$old_location}_flex_ratio" ) ? $settings->get( "message{$old_location}_flex_ratio" ) : '1x1';

		$settings->set( "pay_later_{$location}_message_layout", $layout );
		$settings->set( "pay_later_{$location}_message_logo", $logo_type );
		$settings->set( "pay_later_{$location}_message_position", $logo_position );
		$settings->set( "pay_later_{$location}_message_color", $text_color );
		$settings->set( "pay_later_{$location}_message_flex_color", $style_color );
		$settings->set( "pay_later_{$location}_message_flex_ratio", $ratio );
	}

	/**
	 * Finds from old settings the selected locations for given type.
	 *
	 * @param Settings $settings The settings.
	 * @param string[] $all_locations The list of all available locations.
	 * @param string   $type The setting type: 'button' or 'message'.
	 * @return string[] The list of locations, which should be selected.
	 */
	protected function selected_locations( Settings $settings, array $all_locations, string $type ): array {
		$button_locations = array();

		foreach ( $all_locations as $location ) {
			$location_setting_name_part = $location === 'checkout' ? '' : "_{$location}";
			$setting_name               = "{$type}{$location_setting_name_part}_enabled";

			if ( $settings->has( $setting_name ) && $settings->get( $setting_name ) ) {
				$button_locations[] = $location;
			}
		}

		return $button_locations;
	}

	/**
	 * Migrates the old smart button settings.
	 *
	 * The migration will be done on plugin upgrade if it hasn't already done.
	 *
	 * @param ContainerInterface $c The Container.
	 */
	protected function migrate_smart_button_settings( ContainerInterface $c ): void {
		$is_smart_button_settings_migrated_option_name = 'woocommerce_ppcp-is_smart_button_settings_migrated';
		$is_smart_button_settings_migrated             = get_option( $is_smart_button_settings_migrated_option_name );

		if ( $is_smart_button_settings_migrated ) {
			return;
		}

		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			function () use ( $c, $is_smart_button_settings_migrated_option_name ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				$available_button_locations = array_keys( $c->get( 'wcgateway.button.locations' ) );
				$selected_button_locations  = $this->selected_locations( $settings, $available_button_locations, 'button' );
				if ( ! empty( $selected_button_locations ) ) {
					$settings->set( 'smart_button_locations', $selected_button_locations );
					$settings->persist();
				}

				update_option( $is_smart_button_settings_migrated_option_name, true );
			}
		);
	}

	/**
	 * Changes the button rendering place for page builders
	 * that do not work well with our default places.
	 *
	 * @return void
	 */
	protected function fix_page_builders(): void {
		add_action(
			'init',
			function() {
				if ( $this->is_elementor_pro_active() || $this->is_divi_theme_active() ) {
					add_filter(
						'woocommerce_paypal_payments_single_product_renderer_hook',
						function(): string {
							return 'woocommerce_after_add_to_cart_form';
						},
						5
					);
				}
			}
		);
	}

	/**
	 * Checks whether the Elementor Pro plugins (allowing integrations with WC) is active.
	 *
	 * @return bool
	 */
	protected function is_elementor_pro_active(): bool {
		return is_plugin_active( 'elementor-pro/elementor-pro.php' );
	}

	/**
	 * Checks whether the Divi theme is currently used.
	 *
	 * @return bool
	 */
	protected function is_divi_theme_active(): bool {
		$theme = wp_get_theme();
		return $theme->get( 'Name' ) === 'Divi';
	}
}
