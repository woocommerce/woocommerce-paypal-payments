<?php
/**
 * The subscription module.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class SubscriptionModule
 */
class SubscriptionModule implements ModuleInterface {

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
	 * Runs the module.
	 *
	 * @param ContainerInterface|null $container The container.
	 */
	public function run( ContainerInterface $container = null ) {
		add_action(
			'woocommerce_scheduled_subscription_payment_' . PayPalGateway::ID,
			static function ( $amount, $order ) use ( $container ) {
				if ( ! is_a( $order, \WC_Order::class ) ) {
					return;
				}
				$handler = $container->get( 'subscription.renewal-handler' );
				$handler->renew( $order );
			},
			10,
			2
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
