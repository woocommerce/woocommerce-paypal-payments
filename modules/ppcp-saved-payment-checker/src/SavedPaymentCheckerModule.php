<?php
/**
 * The SavedPaymentChecker module.
 *
 * @package WooCommerce\PayPalCommerce\SavedPaymentChecker
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavedPaymentChecker;

use Psr\Log\LoggerInterface;
use WC_Order;
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
			'woocommerce_paypal_payments_order_intent',
			function( string $intent ) use ( $c ) {
				$subscription_helper = $c->get( 'subscription.helper' );
				assert( $subscription_helper instanceof SubscriptionHelper );

				if ( $subscription_helper->cart_contains_subscription() || $subscription_helper->current_product_is_subscription() ) {
					return 'AUTHORIZE';
				}

				return $intent;
			}
		);

		/**
		 * Schedules saved payment checker before payment success handler.
		 */
		add_action(
			'woocommerce_paypal_payments_before_handle_payment_success',
			function( WC_Order $wc_order ) use ( $c ) {
				$subscription_helper = $c->get( 'subscription.helper' );
				assert( $subscription_helper instanceof SubscriptionHelper );

				if ( $subscription_helper->has_subscription( $wc_order->get_id() ) ) {
					$payment_token_checker = $c->get( 'saved-payment-checker.payment-token-checker' );
					assert( $payment_token_checker instanceof PaymentTokenChecker );

					$payment_token_checker->schedule_saved_payment_check( $wc_order->get_id(), $wc_order->get_customer_id() );
				}
			}
		);

		/**
		 * Triggers a payment token check for the given order and customer id.
		 */
		add_action(
			'woocommerce_paypal_payments_check_saved_payment',
			function ( int $order_id, int $customer_id, string $intent ) use ( $c ) {
				$payment_token_checker = $c->get( 'vaulting.payment-token-checker' );
				assert( $payment_token_checker instanceof PaymentTokenChecker );

				$payment_token_checker->check_and_update( $order_id, $customer_id, $intent );
			},
			10,
			3
		);

		/**
		 * Adds email content for vaulting failure.
		 */
		add_action(
			'woocommerce_email_before_order_table',
			function( WC_Order $order ) use ( $c ) {
				$subscription_helper = $c->get( 'subscription.helper' );
				assert( $subscription_helper instanceof SubscriptionHelper );
				$logger = $c->get( 'woocommerce.logger.woocommerce' );
				assert( $logger instanceof LoggerInterface );

				$vault_failed = $order->get_meta( PaymentTokenChecker::VAULTING_FAILED_META_KEY );
				if ( $subscription_helper->has_subscription( $order->get_id() ) && ! empty( $vault_failed ) ) {
					$logger->info( "Adding vaulting failure info to email for order #{$order->get_id()}." );

					if ( $vault_failed === 'void_auth' ) {
						echo wp_kses_post( '<p>' . __( 'The subscription payment failed because the payment method could not be saved. Please try again with a different payment method.', 'woocommerce-paypal-payments' ) . '</p>' );
					}

					if ( $vault_failed === 'capture_auth' ) {
						echo wp_kses_post( '<p>' . __( 'The subscription has been activated, but the payment method could not be saved. Please contact the merchant to save a payment method for automatic subscription renewal payments.', 'woocommerce-paypal-payments' ) . '</p>' );
					}
				}
			}
		);

		/**
		 * Adds email content for vaulting changing manual renewal order.
		 */
		add_action(
			'woocommerce_email_after_order_table',
			function( WC_Order $order ) use ( $c ) {
				$subscription_helper = $c->get( 'subscription.helper' );
				assert( $subscription_helper instanceof SubscriptionHelper );
				$logger = $c->get( 'woocommerce.logger.woocommerce' );
				assert( $logger instanceof LoggerInterface );

				$vault_failed = $order->get_meta( PaymentTokenChecker::VAULTING_FAILED_META_KEY );
				if ( $subscription_helper->has_subscription( $order->get_id() ) && ! empty( $vault_failed ) ) {
					$logger->info( "Changing subscription auto-renewal status for order #{$order->get_id()}." );

					if ( $vault_failed === 'capture_auth' ) {
						$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $order->get_id() ) : array();
						foreach ( $subscriptions as $subscription ) {
							$subscription->set_requires_manual_renewal( true );
							$subscription->save();
						}
					}
				}
			}
		);
	}
}
