<?php
/**
 * The logging module.
 *
 * @package Inpsyde\Woocommerce\Logging
 */

declare(strict_types=1);

namespace Inpsyde\Woocommerce\Logging;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class WoocommerceLoggingModule
 */
class WoocommerceLoggingModule implements ModuleInterface {

	/**
	 * Setup the module.
	 *
	 * @return ServiceProviderInterface
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * Run the module.
	 *
	 * @param ContainerInterface $container The container.
	 */
	public function run( ContainerInterface $container ) {
	}
}
