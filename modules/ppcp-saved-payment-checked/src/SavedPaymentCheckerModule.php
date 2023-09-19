<?php
/**
 * The SavedPaymentChecker module.
 *
 * @package WooCommerce\PayPalCommerce\SavedPaymentChecker
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavedPaymentChecker;

use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class SavedPaymentCheckerModule
 */
class SavedPaymentCheckerModule implements ModuleInterface {

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

		/**
		 * Set authorize intent for vaulted subscriptions, so we can void if payment not saved.
		 */
		add_filter(
			'woocommerce_paypal_payments_saved_payment_subscription_intent',
			function( string $intent ) use ( $c ) {
				$subscription_helper = $c->get( 'subscription.helper' );
				assert( $subscription_helper instanceof SubscriptionHelper );

				if ( $subscription_helper->cart_contains_subscription() || $subscription_helper->current_product_is_subscription() ) {
					return 'AUTHORIZE';
				}

				return $intent;
			}
		);
	}
}
