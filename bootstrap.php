<?php
/**
 * Bootstraps the modular app.
 *
 * @package WooCommerce\PayPalCommerce
 */

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Package;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Properties\PluginProperties;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\DhiiToModularityModule;

return function (
	string $root_dir,
	array $additional_containers = array(),
	array $additional_modules = array()
): ContainerInterface {
	/**
	 * Skip path check.
	 *
	 * @psalm-suppress UnresolvableInclude
	 */
	$modules = ( require "$root_dir/modules.php" )( $root_dir );

	$modules = array_merge( $modules, $additional_modules );

	/**
	 * Use this filter to add custom module or remove some of existing ones.
	 * Modules able to access container, add services and modify existing ones.
	 */
	$modules = apply_filters( 'woocommerce_paypal_payments_modules', $modules );

	// Initialize plugin.
	$properties = PluginProperties::new( __FILE__ );
	$bootstrap  = Package::new( $properties );

	foreach ($modules as $key => $module) {
		if (
			$module instanceof \WooCommerce\PayPalCommerce\AdminNotices\AdminNotices ||
			$module instanceof \WooCommerce\PayPalCommerce\ApiClient\ApiModule ||
			$module instanceof \WooCommerce\PayPalCommerce\Applepay\ApplepayModule ||
			$module instanceof \WooCommerce\PayPalCommerce\Blocks\BlocksModule ||
			$module instanceof \WooCommerce\PayPalCommerce\Button\ButtonModule
		) {
			$bootstrap->addModule( $module );
			unset($modules[$key]);
		}
	}


	$bootstrap->addModule( new DhiiToModularityModule( $modules ) );
	$bootstrap->boot();

	return $bootstrap->container();
};
