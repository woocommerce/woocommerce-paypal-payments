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
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Subscription\Repository\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
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
			function ( $amount, $order ) use ( $container ) {
				$this->renew( $order, $container );
			},
			10,
			2
		);

		add_action(
			'woocommerce_scheduled_subscription_payment_' . CreditCardGateway::ID,
			function ( $amount, $order ) use ( $container ) {
				$this->renew( $order, $container );
			},
			10,
			2
		);

		add_action(
			'woocommerce_subscription_payment_complete',
			function ( $subscription ) use ( $container ) {
				$payment_token_repository = $container->get( 'subscription.repository.payment-token' );
				$logger                   = $container->get( 'woocommerce.logger.woocommerce' );

				$this->add_payment_token_id( $subscription, $payment_token_repository, $logger );
			}
		);
	}

	/**
	 * Handles a Subscription product renewal.
	 *
	 * @param \WC_Order               $order WooCommerce order.
	 * @param ContainerInterface|null $container The container.
	 * @return void
	 */
	protected function renew( $order, $container ) {
		if ( ! is_a( $order, \WC_Order::class ) ) {
			return;
		}

		$handler = $container->get( 'subscription.renewal-handler' );
		$handler->renew( $order );
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}

	/**
	 * Adds Payment token ID to subscription.
	 *
	 * @param \WC_Subscription       $subscription The subscription.
	 * @param PaymentTokenRepository $payment_token_repository The payment repository.
	 * @param LoggerInterface        $logger The logger.
	 */
	private function add_payment_token_id(
		\WC_Subscription $subscription,
		PaymentTokenRepository $payment_token_repository,
		LoggerInterface $logger
	) {
		try {
			$tokens = $payment_token_repository->all_for_user_id( $subscription->get_customer_id() );
			if ( $tokens ) {
				$subscription_id = $subscription->get_id();
				$latest_token_id = end( $tokens )->id() ? end( $tokens )->id() : '';
				update_post_meta( $subscription_id, 'payment_token_id', $latest_token_id, true );
			}
		} catch ( RuntimeException $error ) {
			$message = sprintf(
				// translators: %1$s is the payment token Id, %2$s is the error message.
				__(
					'Could not add token Id to subscription %1$s: %2$s',
					'woocommerce-paypal-payments'
				),
				$subscription->get_id(),
				$error->getMessage()
			);

			$logger->log( 'warning', $message );
		}
	}
}
