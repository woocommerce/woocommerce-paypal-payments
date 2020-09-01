<?php
/**
 * The plugin module.
 *
 * @package Inpsyde\PayPalCommerce
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class PluginModule
 */
class PluginModule implements ModuleInterface {

	/**
	 * Sets the module up.
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider( array(), array() );
	}

	/**
	 * Runs the module.
	 *
	 * @param ContainerInterface $container The Container.
	 */
	public function run( ContainerInterface $container ) {
		// TODO: Implement run() method.
	}
}
