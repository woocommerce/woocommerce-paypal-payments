<?php
/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

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

		$this->migrate_pay_later_settings( $c );
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
				$available_button_locations    = array_merge( $available_messaging_locations, array( 'mini-cart' ) );

				if ( in_array( 'credit', $disable_funding, true ) ) {
					$settings->set( 'pay_later_button_enabled', false );
				} else {
					$settings->set( 'pay_later_button_enabled', true );
					$selected_button_locations = $this->pay_later_selected_locations( $settings, $available_button_locations, 'button' );
					if ( ! empty( $selected_button_locations ) ) {
						$settings->set( 'pay_later_button_locations', $selected_button_locations );
					}
				}

				$selected_messaging_locations = $this->pay_later_selected_locations( $settings, $available_messaging_locations, 'message' );

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
	 * Finds from old settings the locations, which should be selected for new Pay Later tab settings.
	 *
	 * @param Settings $settings The settings.
	 * @param string[] $all_locations The list of all available locations.
	 * @param string   $setting The setting: 'button' or 'message'.
	 * @return string[] The list of locations, which should be selected.
	 * @throws NotFoundException When setting was not found.
	 */
	protected function pay_later_selected_locations( Settings $settings, array $all_locations, string $setting ): array {
		$pay_later_locations = array();

		foreach ( $all_locations as $location ) {
			$location_setting_name_part = $location === 'checkout' ? '' : "_{$location}";
			$setting_name               = "{$setting}{$location_setting_name_part}_enabled";

			if ( $settings->has( $setting_name ) && $settings->get( $setting_name ) ) {
				$pay_later_locations[] = $location;
			}
		}

		return $pay_later_locations;
	}
}
