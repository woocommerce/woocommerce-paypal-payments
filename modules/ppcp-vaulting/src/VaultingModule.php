<?php
/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use RuntimeException;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WP_User_Query;

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

		add_filter(
			'woocommerce_payment_token_class',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $type ) {
				if ( $type === 'WC_Payment_Token_PayPal' ) {
					return PaymentTokenPayPal::class;
				}

				return $type;
			}
		);

		add_filter(
			'woocommerce_payment_methods_list_item',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $item, $payment_token ) {
				if ( ! is_array( $item ) || ! is_a( $payment_token, WC_Payment_Token::class ) ) {
					return $item;
				}

				if ( strtolower( $payment_token->get_type() ) !== 'paypal' ) {
					return $item;
				}

				$item['method']['brand'] = 'PayPal';

				return $item;
			},
			10,
			2
		);

		add_action(
			'wp',
			function() use ( $container ) {
				global $wp;

				if ( isset( $wp->query_vars['delete-payment-method'] ) ) {
					$token_id = absint( $wp->query_vars['delete-payment-method'] );
					$token    = WC_Payment_Tokens::get( $token_id );

					if (
						is_null( $token )
						|| ( $token->get_gateway_id() !== PayPalGateway::ID && $token->get_gateway_id() !== CreditCardGateway::ID )
					) {
						return;
					}

					$wpnonce         = wc_clean( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) );
					$token_id_string = (string) $token_id;
					$action          = 'delete-payment-method-' . $token_id_string;
					if (
						$token->get_user_id() !== get_current_user_id()
						|| ! isset( $wpnonce ) || ! is_string( $wpnonce )
						|| wp_verify_nonce( $wpnonce, $action ) === false
					) {
						wc_add_notice( __( 'Invalid payment method.', 'woocommerce-paypal-payments' ), 'error' );
						wp_safe_redirect( wc_get_account_endpoint_url( 'payment-methods' ) );
						exit();
					}

					try {
						$payment_token_endpoint = $container->get( 'api.endpoint.payment-token' );
						$payment_token_endpoint->delete_token_by_id( $token->get_token() );
					} catch ( RuntimeException $exception ) {
						wc_add_notice( __( 'Could not delete payment token. ', 'woocommerce-paypal-payments' ) . $exception->getMessage(), 'error' );
						return;
					}
				}
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			function () use ( $container ) {
				$customers = new WP_User_Query(
					array(
						'fields'     => 'ID',
						'limit'      => -1,
						'meta_key'   => 'ppcp-vault-token',
						'meta_query' => array(
							array(
								'key'     => 'ppcp_tokens_migrated',
								'compare' => 'NOT EXISTS',
							),
						),
					)
				);

				$migrate = $container->get( 'vaulting.payment-tokens-migration' );
				assert( $migrate instanceof PaymentTokensMigration );

				foreach ( $customers->get_results() as $id ) {
					$migrate->migrate_payment_tokens_for_user( (int) $id );
				}
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getKey() {  }
}
