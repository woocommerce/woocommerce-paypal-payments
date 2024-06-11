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
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Compat\Assets\CompatAssets;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;
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
		$this->initialize_tracking_compat_layer( $c );

		$asset_loader = $c->get( 'compat.assets' );
		assert( $asset_loader instanceof CompatAssets );

		add_action( 'init', array( $asset_loader, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $asset_loader, 'enqueue' ) );

		$this->migrate_pay_later_settings( $c );
		$this->migrate_smart_button_settings( $c );

		$this->fix_page_builders();
		$this->exclude_cache_plugins_js_minification( $c );
		$this->set_elementor_checkout_context();
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
	 * Sets up the 3rd party plugins compatibility layer for PayPal tracking.
	 *
	 * @param ContainerInterface $c The Container.
	 * @return void
	 */
	protected function initialize_tracking_compat_layer( ContainerInterface $c ): void {
		$order_tracking_integrations = $c->get( 'order-tracking.integrations' );

		foreach ( $order_tracking_integrations as $integration ) {
			assert( $integration instanceof Integration );
			$integration->integrate();
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
				if (
					$this->is_block_theme_active()
					|| $this->is_elementor_pro_active()
					|| $this->is_divi_theme_active()
					|| $this->is_divi_child_theme_active()
				) {
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
	 * Checks whether the current theme is a blocks theme.
	 *
	 * @return bool
	 */
	protected function is_block_theme_active(): bool {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
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

	/**
	 * Checks whether a Divi child theme is currently used.
	 *
	 * @return bool
	 */
	protected function is_divi_child_theme_active(): bool {
		$theme  = wp_get_theme();
		$parent = $theme->parent();
		return ( $parent && $parent->get( 'Name' ) === 'Divi' );
	}

	/**
	 * Sets the context for the Elementor checkout page.
	 *
	 * @return void
	 */
	protected function set_elementor_checkout_context(): void {
		add_action(
			'wp',
			function() {
				$page_id = get_the_ID();
				if ( ! $page_id || ! CartCheckoutDetector::has_elementor_checkout( $page_id ) ) {
					return;
				}

				add_filter(
					'woocommerce_paypal_payments_context',
					function ( string $context ): string {
						// Default context.
						return ( 'mini-cart' === $context ) ? 'checkout' : $context;
					}
				);
			}
		);
	}

	/**
	 * Excludes PayPal scripts from being minified by cache plugins.
	 *
	 * @param ContainerInterface $c The Container.
	 * @return void
	 */
	protected function exclude_cache_plugins_js_minification( ContainerInterface $c ): void {
		$ppcp_script_names      = $c->get( 'compat.plugin-script-names' );
		$ppcp_script_file_names = $c->get( 'compat.plugin-script-file-names' );

		// Siteground SG Optimize.
		add_filter(
			'sgo_js_minify_exclude',
			function( array $scripts ) use ( $ppcp_script_names ) {
				return array_merge( $scripts, $ppcp_script_names );
			}
		);

		// LiteSpeed Cache.
		add_filter(
			'litespeed_optimize_js_excludes',
			function( array $excluded_js ) use ( $ppcp_script_file_names ) {
				return array_merge( $excluded_js, $ppcp_script_file_names );
			}
		);

		// W3 Total Cache.
		add_filter(
			'w3tc_minify_js_do_tag_minification',
			function( bool $do_tag_minification, string $script_tag, string $file ) {
				if ( $file && strpos( $file, 'ppcp' ) !== false ) {
					return false;
				}
				return $do_tag_minification;
			},
			10,
			3
		);
	}
}
