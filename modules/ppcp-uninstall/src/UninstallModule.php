<?php
/**
 * The uninstall module.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Uninstall;

use Exception;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Uninstall\Assets\ClearDatabaseAssets;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class UninstallModule
 */
class UninstallModule implements ModuleInterface {

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
	 */
	public function run( ContainerInterface $container ): void {
		$page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		if ( Settings::CONNECTION_TAB_ID === $page_id ) {
			$this->registerClearDatabaseAssets( $container->get( 'uninstall.clear-db-assets' ) );
		}

		$request_data           = $container->get( 'button.request-data' );
		$clear_db               = $container->get( 'uninstall.clear-db' );
		$clear_db_endpoint      = $container->get( 'uninstall.clear-db-endpoint' );
		$option_names           = $container->get( 'uninstall.ppcp-all-option-names' );
		$scheduled_action_names = $container->get( 'uninstall.ppcp-all-scheduled-action-names' );
		$action_names           = $container->get( 'uninstall.ppcp-all-action-names' );

		$this->handleClearDbAjaxRequest( $request_data, $clear_db, $clear_db_endpoint, $option_names, $scheduled_action_names, $action_names );
	}

	/**
	 * Registers the assets for clear database functionality.
	 *
	 * @param ClearDatabaseAssets $asset_loader The clear database functionality asset loader.
	 */
	protected function registerClearDatabaseAssets( ClearDatabaseAssets $asset_loader ): void {
		add_action( 'init', array( $asset_loader, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $asset_loader, 'enqueue' ) );
	}

	/**
	 * Handles the AJAX request to clear the database.
	 *
	 * @param RequestData            $request_data The request data helper.
	 * @param ClearDatabaseInterface $clear_db Can delete the options and clear scheduled actions from database.
	 * @param string                 $nonce The nonce.
	 * @param string[]               $option_names The list of option names.
	 * @param string[]               $scheduled_action_names The list of scheduled action names.
	 * @param string[]               $action_names The list of action names.
	 */
	protected function handleClearDbAjaxRequest(
		RequestData $request_data,
		ClearDatabaseInterface $clear_db,
		string $nonce,
		array $option_names,
		array $scheduled_action_names,
		array $action_names
	): void {
		add_action(
			"wc_ajax_{$nonce}",
			static function () use ( $request_data, $clear_db, $nonce, $option_names, $scheduled_action_names, $action_names ) {
				try {
					if ( ! current_user_can( 'manage_woocommerce' ) ) {
						wp_send_json_error( 'Not admin.', 403 );
						return false;
					}

					// Validate nonce.
					$request_data->read_request( $nonce );

					$clear_db->delete_options( $option_names );
					$clear_db->clear_scheduled_actions( $scheduled_action_names );
					$clear_db->clear_actions( $action_names );

					wp_send_json_success();
					return true;
				} catch ( Exception $error ) {
					wp_send_json_error( $error->getMessage(), 403 );
					return false;
				}
			}
		);
	}
}
