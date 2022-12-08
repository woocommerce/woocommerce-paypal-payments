<?php
/**
 * The uninstall module.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Uninstall;

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
            $this->registerClearDatabaseAssets($container->get('uninstall.clear-db-assets'));
        }
	}

    /**
     * Registers the assets for clear database functionality.
     *
     * @param ClearDatabaseAssets $asset_loader The clear database functionality asset loader.
     */
	protected function registerClearDatabaseAssets(ClearDatabaseAssets $asset_loader): void{
        add_action('init', array($asset_loader, 'register'));
        add_action('admin_enqueue_scripts', array($asset_loader, 'enqueue'));
    }
}
