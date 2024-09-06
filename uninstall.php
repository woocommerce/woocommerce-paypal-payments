<?php
/**
 * Uninstalls the plugin.
 *
 * @package WooCommerce\PayPalCommerce
 */

declare(strict_types=1);

use WooCommerce\PayPalCommerce\Uninstall\ClearDatabaseInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die( 'Direct access not allowed.' );
}

$root_dir = __DIR__;
$main_plugin_file = "{$root_dir}/woocommerce-paypal-payments.php";

if ( !file_exists( $main_plugin_file ) ) {
    return;
}

require $main_plugin_file;

( static function (string $root_dir): void {

	$autoload_filepath = "{$root_dir}/vendor/autoload.php";
	if ( file_exists( $autoload_filepath ) && ! class_exists( '\WooCommerce\PayPalCommerce\PluginModule' ) ) {
		require $autoload_filepath;
	}

	try {
		$bootstrap = require "{$root_dir}/bootstrap.php";

		$app_container = $bootstrap( $root_dir );
		assert( $app_container instanceof ContainerInterface );

		$settings = $app_container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		$should_clear_db = $settings->has( 'uninstall_clear_db_on_uninstall' ) && $settings->get( 'uninstall_clear_db_on_uninstall' );
		if ( ! $should_clear_db ) {
			return;
		}

		$clear_db = $app_container->get( 'uninstall.clear-db' );
		assert( $clear_db instanceof ClearDatabaseInterface );

		$option_names           = $app_container->get( 'uninstall.ppcp-all-option-names' );
		$scheduled_action_names = $app_container->get( 'uninstall.ppcp-all-scheduled-action-names' );

		$clear_db->delete_options( $option_names );
		$clear_db->clear_scheduled_actions( $scheduled_action_names );
	} catch ( Throwable $throwable ) {
		$message = sprintf(
			'<strong>Error:</strong> %s <br><pre>%s</pre>',
			$throwable->getMessage(),
			$throwable->getTraceAsString()
		);

		add_action(
			'all_admin_notices',
			static function () use ( $message ) {
				$class = 'notice notice-error';
				printf(
					'<div class="%1$s"><p>%2$s</p></div>',
					esc_attr( $class ),
					wp_kses_post( $message )
				);
			}
		);
	}
} )($root_dir);
