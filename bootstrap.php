<?php
/**
 * Bootstraps the modular app.
 *
 * @package WooCommerce\PayPalCommerce
 */

use Dhii\Container\CachingContainer;
use Dhii\Container\CompositeCachingServiceProvider;
use Dhii\Container\CompositeContainer;
use Dhii\Container\DelegatingContainer;
use Dhii\Container\ProxyContainer;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\LoggingDelegatingContainer;

return function (
	string $root_dir,
	ContainerInterface ...$additional_containers
): ContainerInterface {
	$modules = ( require "$root_dir/modules.php" )( $root_dir );

	/**
	 * Use this filter to add custom module or remove some of existing ones.
	 * Modules able to access container, add services and modify existing ones.
	 */
	$modules = apply_filters( 'woocommerce_paypal_payments_modules', $modules );

	$providers = array_map(
		function ( ModuleInterface $module ): ServiceProviderInterface {
			return $module->setup();
		},
		$modules
	);

	// phpcs:ignore
	$is_container_logging_enabled = str_contains(  $_SERVER['REQUEST_URI'] ?? '', 'ppcp-log-container=1' );

	$provider        = new CompositeCachingServiceProvider( $providers );
	$proxy_container = new ProxyContainer();
	// TODO: caching does not work currently,
	// may want to consider fixing it later (pass proxy as parent to DelegatingContainer)
	// for now not fixed since we were using this behavior for long time and fixing it now may break things.
	$container     = $is_container_logging_enabled ? new LoggingDelegatingContainer( $provider ) : new DelegatingContainer( $provider );
	$app_container = new CachingContainer(
		new CompositeContainer(
			array_merge(
				$additional_containers,
				array( $container )
			)
		)
	);
	$proxy_container->setInnerContainer( $app_container );

	foreach ( $modules as $module ) {
		/* @var $module ModuleInterface module */
		$module->run( $app_container );
	}

	return $app_container;
};
