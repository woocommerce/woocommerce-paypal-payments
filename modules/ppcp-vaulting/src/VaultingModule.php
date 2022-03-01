<?php
/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Exception;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\Vaulting\Endpoint\DeletePaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

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
	 */
	public function run( ContainerInterface $container ): void {

		$settings = $container->get( 'wcgateway.settings' );
		if ( ! $settings->has( 'vault_enabled' ) || ! $settings->get( 'vault_enabled' ) ) {
			return;
		}

		add_filter(
			'woocommerce_account_menu_items',
			function( $menu_links ) {
				$menu_links = array_slice( $menu_links, 0, 5, true )
				+ array( 'ppcp-paypal-payment-tokens' => 'PayPal payments' )
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
				$guest_customer_id = WC()->session->get( 'ppcp_guest_customer_id' );
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
			function ( $order_id, $customer_id ) use ( $container ) {
				$payment_token_repository      = $container->get( 'vaulting.repository.payment-token' );
				$settings                      = $container->get( 'wcgateway.settings' );
				$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
				$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
				$order_endpoint                = $container->get( 'api.endpoint.order' );
				$payments_endpoint             = $container->get( 'api.endpoint.payments' );

				$tokens = $payment_token_repository->all_for_user_id( $customer_id );
				if ( $tokens ) {
					$this->capture_authorized_payment(
						$settings,
						$order_id,
						$authorized_payments_processor,
						$logger,
						$customer_id
					);

					return;
				}

				$logger->error( "Payment for subscription parent order #{$order_id} was not saved on PayPal." );

				$wc_order = wc_get_order( $order_id );
				$order    = $this->getOrder( $wc_order, $order_endpoint );

				try {
					$this->void_authorizations( $order, $payments_endpoint );
				} catch ( RuntimeException $exception ) {
					$logger->warning($exception->getMessage());
				}

				$this->updateFailedStatus( $wc_order, $order_id, $logger );
			},
			10,
			2
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

	/**
	 * @param $settings
	 * @param $order_id
	 * @param $authorized_payments_processor
	 * @param $logger
	 * @param $customer_id
	 */
	protected function capture_authorized_payment(
		$settings,
		$order_id,
		$authorized_payments_processor,
		$logger,
		$customer_id
	): void {
		if ( $settings->has( 'intent' ) && strtoupper( (string) $settings->get( 'intent' ) ) === 'CAPTURE' ) {
			$wc_order = wc_get_order( $order_id );
			$authorized_payments_processor->capture_authorized_payment( $wc_order );
			$logger->info( "Order: #{$order_id} for user: {$customer_id} captured successfully." );
		}
	}

	/**
	 * @param $order
	 * @param $payments_endpoint
	 * @throws RuntimeException
	 */
	protected function void_authorizations( $order, $payments_endpoint ): void {
		$purchase_units = $order->purchase_units();
		if ( ! $purchase_units ) {
			throw new RuntimeException( 'No purchase units.' );
		}

		$payments = $purchase_units[0]->payments();
		if ( ! $payments ) {
			throw new RuntimeException( 'No payments.' );
		}

		$voidable_authorizations = array_filter(
			$payments->authorizations(),
			function ( Authorization $authorization ): bool {
				return $authorization->is_voidable();
			}
		);
		if ( ! $voidable_authorizations ) {
			throw new RuntimeException( 'No voidable authorizations.' );
		}

		foreach ( $voidable_authorizations as $authorization ) {
			$payments_endpoint->void( $authorization );
		}
	}

	/**
	 * @param $wc_order
	 * @param $order_endpoint
	 * @return mixed
	 */
	protected function getOrder( $wc_order, $order_endpoint ) {
		$paypal_order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		if ( ! $paypal_order_id ) {
			throw new RuntimeException( 'PayPal order ID not found in meta.' );
		}

		return $order_endpoint->order( $paypal_order_id );
	}

	/**
	 * @param $wc_order
	 * @param $order_id
	 * @param $logger
	 */
	protected function updateFailedStatus( $wc_order, $order_id, $logger ): void {
		$error_message = __( 'Could not process order because it was not possible to save the payment on PayPal.', 'woocommerce-paypal-payments' );
		$wc_order->update_status( 'failed', $error_message );

		$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		foreach ( $subscriptions as $key => $subscription ) {
			if ( $subscription->get_parent_id() === $order_id ) {
				try {
					$subscription->update_status( 'cancelled' );
					break;
				} catch ( Exception $exception ) {
					$logger->error( "Could not update cancelled status on subscription #{$subscription->get_id()} " . $exception->getMessage() );
				}
			}
		}
	}
}
