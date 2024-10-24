<?php
/**
 * The Settings module.
 *
 * @package WooCommerce\PayPalCommerce\Settings
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class SettingsModule
 */
class SettingsModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services() : array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $container ) : bool {
		add_action(
			'admin_enqueue_scripts',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $hook_suffix ) use ( $container ) {
				if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
					return;
				}

				/**
				 * Require resolves.
				 *
				 * @psalm-suppress UnresolvableInclude
				 */
				$script_asset_file = require dirname( realpath( __FILE__ ) ?: '', 2 ) . '/assets/index.asset.php';

				$module_url = $container->get( 'settings.url' );

				wp_register_script(
					'ppcp-admin-settings',
					$module_url . '/assets/index.js',
					$script_asset_file['dependencies'],
					$script_asset_file['version'],
					true
				);

				wp_enqueue_script( 'ppcp-admin-settings' );

				/**
				 * Require resolves.
				 *
				 * @psalm-suppress UnresolvableInclude
				 */
				$style_asset_file = require dirname( realpath( __FILE__ ) ?: '', 2 ) . '/assets/style.asset.php';

				wp_register_style(
					'ppcp-admin-settings',
					$module_url . '/assets/style-style.css',
					$style_asset_file['dependencies'],
					$style_asset_file['version']
				);

				wp_enqueue_style( 'ppcp-admin-settings' );

				wp_enqueue_style( 'ppcp-admin-settings-font', 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap', array(), $style_asset_file['version'] );
				wp_localize_script(
					'ppcp-admin-settings',
					'ppcpSettings',
					array(
						'assets' => array(
							'imagesUrl' => $module_url . '/images/',
						),
					)
				);
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_admin_options_wrapper',
			static function () : void {
				global $hide_save_button;
				$hide_save_button = true;

				echo '<div id="ppcp-settings-container"></div>';
			}
		);

		add_action(
			'rest_api_init',
			static function () use ( $container ) : void {
				$onboarding_endpoint = $container->get( 'settings.rest.onboarding' );
				$onboarding_endpoint->register_routes();
			}
		);

		return true;
	}
}
