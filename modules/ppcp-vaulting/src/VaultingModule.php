<?php
/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\Endpoint\DeletePaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class StatusReportModule
 */
class VaultingModule implements ModuleInterface {


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
	 *
	 * @param ContainerInterface $container A services container instance.
	 * @throws NotFoundException When service could not be found.
	 */
	public function run( ContainerInterface $container ): void {

		$settings = $container->get( 'wcgateway.settings' );
		if ( ! $settings->has( 'vault_enabled' ) || ! $settings->get( 'vault_enabled' ) ) {
			return;
		}

		$listener = $container->get( 'vaulting.customer-approval-listener' );
		assert( $listener instanceof CustomerApprovalListener );

		$listener->listen();

		add_filter(
			'woocommerce_account_menu_items',
			function( $menu_links ) {
				$menu_links = array_slice( $menu_links, 0, 5, true )
				+ array( 'ppcp-paypal-payment-tokens' => __( 'PayPal payments', 'woocommerce-paypal-payments' ) )
				+ array_slice( $menu_links, 5, null, true );

				return $menu_links;
			},
			40
		);

		add_action(
			'init',
			function () {
				add_rewrite_endpoint( 'ppcp-paypal-payment-tokens', EP_PAGES );
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_migrate',
			function () {
				add_action(
					'init',
					function () {
						add_rewrite_endpoint( 'ppcp-paypal-payment-tokens', EP_PAGES );
						flush_rewrite_rules();
					}
				);
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_activate',
			function () {
				add_rewrite_endpoint( 'ppcp-paypal-payment-tokens', EP_PAGES );
				flush_rewrite_rules();
			}
		);

		add_action(
			'woocommerce_account_ppcp-paypal-payment-tokens_endpoint',
			function () use ( $container ) {
				$payment_token_repository = $container->get( 'vaulting.repository.payment-token' );
				$renderer                 = $container->get( 'vaulting.payment-tokens-renderer' );

				$tokens = $payment_token_repository->all_for_user_id( get_current_user_id() );
				if ( $tokens ) {
					echo wp_kses_post( $renderer->render( $tokens ) );
				} else {
					echo wp_kses_post( $renderer->render_no_tokens() );
				}
			}
		);

		$subscription_helper = $container->get( 'subscription.helper' );
		add_action(
			'woocommerce_created_customer',
			function( int $customer_id ) use ( $subscription_helper ) {
				$session = WC()->session;
				if ( ! $session ) {
					return;
				}
				$guest_customer_id = $session->get( 'ppcp_guest_customer_id' );
				if ( $guest_customer_id && $subscription_helper->cart_contains_subscription() ) {
					update_user_meta( $customer_id, 'ppcp_guest_customer_id', $guest_customer_id );
				}
			}
		);

		$asset_loader = $container->get( 'vaulting.assets.myaccount-payments' );
		add_action(
			'wp_enqueue_scripts',
			function () use ( $asset_loader ) {
				if ( is_account_page() && $this->is_payments_page() ) {
					$asset_loader->enqueue();
					$asset_loader->localize();
				}
			}
		);

		add_action(
			'wc_ajax_' . DeletePaymentTokenEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'vaulting.endpoint.delete' );
				assert( $endpoint instanceof DeletePaymentTokenEndpoint );

				$endpoint->handle_request();
			}
		);

		add_action(
			'woocommerce_paypal_payments_check_saved_payment',
			function ( int $order_id, int $customer_id, string $intent ) use ( $container ) {
				$payment_token_checker = $container->get( 'vaulting.payment-token-checker' );
				$payment_token_checker->check_and_update( $order_id, $customer_id, $intent );
			},
			10,
			3
		);

		$this->filterFailedVaultingEmailsForSubscriptionOrders( $container );
	}

	/**
	 * Filters the emails when vaulting is failed for subscription orders.
	 *
	 * @param ContainerInterface $container A services container instance.
	 * @throws NotFoundException When service could not be found.
	 */
	protected function filterFailedVaultingEmailsForSubscriptionOrders( ContainerInterface $container ):void {
		add_action(
			'woocommerce_email_before_order_table',
			function( WC_Order $order ) use ( $container ) {
				/**
				 * The SubscriptionHelper.
				 *
				 * @var SubscriptionHelper $subscription_helper
				 */
				$subscription_helper = $container->get( 'subscription.helper' );

				/**
				 * The logger.
				 *
				 * @var LoggerInterface $logger
				 */
				$logger = $container->get( 'woocommerce.logger.woocommerce' );

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

		add_action(
			'woocommerce_email_after_order_table',
			function( WC_Order $order ) use ( $container ) {
				/**
				 * The SubscriptionHelper.
				 *
				 * @var SubscriptionHelper $subscription_helper
				 */
				$subscription_helper = $container->get( 'subscription.helper' );

				/**
				 * The logger.
				 *
				 * @var LoggerInterface $logger
				 */
				$logger = $container->get( 'woocommerce.logger.woocommerce' );

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

	/**
	 * {@inheritDoc}
	 */
	public function getKey() {  }

	/**
	 * Check if is payments page.
	 *
	 * @return bool Whethen page is payments or not.
	 */
	private function is_payments_page(): bool {
		global $wp;
		$request = explode( '/', wp_parse_url( $wp->request, PHP_URL_PATH ) );
		if ( end( $request ) === 'ppcp-paypal-payment-tokens' ) {
			return true;
		}

		return false;
	}
}
