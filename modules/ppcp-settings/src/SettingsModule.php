<?php
/**
 * The Settings module.
 *
 * @package WooCommerce\PayPalCommerce\AxoBlock
 */

declare(strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class SettingsModule
 */
class SettingsModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $container ): bool {
		add_action(
			'admin_enqueue_scripts',
			function( $hook_suffix ) use ( $container ) {
				if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
					return;
				}

				$script_asset_file = require dirname( realpath( __FILE__ ), 2 ) . '/assets/index.asset.php';
				$module_url        = $container->get( 'settings.url' );

				wp_register_script(
					'ppcp-admin-settings',
					$module_url . '/assets/index.js',
					$script_asset_file['dependencies'],
					$script_asset_file['version'],
					true
				);

				wp_enqueue_script( 'ppcp-admin-settings' );

				$stlye_asset_file = require dirname( realpath( __FILE__ ), 2 ) . '/assets/style.asset.php';
				wp_register_style(
					'ppcp-admin-settings',
					$module_url . '/assets/style-style.css',
					$stlye_asset_file['dependencies'],
					$stlye_asset_file['version']
				);

				wp_enqueue_style( 'ppcp-admin-settings' );
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_admin_options_wrapper',
			function( PayPalGateway $gateway ) {
				global $hide_save_button;
				$hide_save_button = true;

				echo '<div id="ppcp-settings-container"></div>';
			}
		);

		return true;
	}
}
