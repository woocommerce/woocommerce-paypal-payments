<?php
/**
 * The session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class SessionModule
 */
class SessionModule implements ModuleInterface {

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
	public function run( ContainerInterface $c ): void {
		add_action(
			'woocommerce_init',
			function () use ( $c ) {
				$controller = $c->get( 'session.cancellation.controller' );
				/**
				 * The Cancel controller.
				 *
				 * @var CancelController $controller
				 */
				$controller->run();
			}
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
