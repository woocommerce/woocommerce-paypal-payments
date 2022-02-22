<?php
/**
 * The plugin module services.
 *
 * @package WooCommerce\PayPalCommerce
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use Dhii\Versions\StringVersionFactory;
use Psr\Container\ContainerInterface;
use WpOop\WordPress\Plugin\PluginInterface;

return array(
	'ppcp.plugin'        => function( ContainerInterface $container ) : PluginInterface {
		$factory = new FilePathPluginFactory( new StringVersionFactory() );
		return $factory->createPluginFromFilePath( dirname( realpath( __FILE__ ), 2 ) . '/woocommerce-paypal-payments.php' );
	},
	'ppcp.asset-version' => function( ContainerInterface $container ) : string {
		$plugin = $container->get( 'ppcp.plugin' );
		assert( $plugin instanceof PluginInterface );

		return (string) $plugin->getVersion();
	},
);
