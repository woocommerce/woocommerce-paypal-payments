<?php
/**
 * The session module.
 *
 * @package Inpsyde\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Session\Cancellation\CancelController;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class SessionModule
 */
class SessionModule implements ModuleInterface {

	/**
	 * Sets up the module.
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
		add_action(
			'woocommerce_init',
			function () use ( $container ) {
				$controller = $container->get( 'session.cancellation.controller' );
				/**
				 * The Cancel controller.
				 *
				 * @var CancelController $controller
				 */
				$controller->run();
			}
		);
	}
}
