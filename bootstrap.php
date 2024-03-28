<?php
/**
 * Bootstraps the modular app.
 *
 * @package WooCommerce\PayPalCommerce
 */

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Package;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Properties\PluginProperties;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

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

	foreach ( $modules as $module ) {
		$bootstrap->addModule( $module );
	}

	$bootstrap->boot();

	return $bootstrap->container();
};
