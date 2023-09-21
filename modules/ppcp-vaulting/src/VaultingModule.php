<?php
/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
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

				if ( strtolower( $payment_token->get_type() ) === 'paypal' ) {
					assert( $payment_token instanceof PaymentTokenPayPal );
					$item['method']['brand'] = $payment_token->get_email();

					return $item;
				}

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
				$settings = $container->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );
				if ( $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' ) && $settings->has( 'vault_enabled_dcc' ) ) {
					$settings->set( 'vault_enabled_dcc', true );
					$settings->persist();
				}

				$logger = $container->get( 'woocommerce.logger.woocommerce' );
				assert( $logger instanceof LoggerInterface );

				$this->migrate_payment_tokens( $logger );
			}
		);

		/**
		 * Allows running migration externally via `do_action('pcp_migrate_payment_tokens')`.
		 */
		add_action(
			'pcp_migrate_payment_tokens',
			function() use ( $container ) {
				$logger = $container->get( 'woocommerce.logger.woocommerce' );
				assert( $logger instanceof LoggerInterface );

				$this->migrate_payment_tokens( $logger );
			}
		);

		add_action(
			'woocommerce_paypal_payments_payment_tokens_migration',
			function( int $customer_id ) use ( $container ) {
				$migration = $container->get( 'vaulting.payment-tokens-migration' );
				assert( $migration instanceof PaymentTokensMigration );

				$migration->migrate_payment_tokens_for_user( $customer_id );
			}
		);

		add_filter(
			'woocommerce_available_payment_gateways',
			function( array $methods ): array {
				global $wp;
				if ( isset( $wp->query_vars['add-payment-method'] ) ) {
					unset( $methods[ PayPalGateway::ID ] );
				}

				return $methods;
			}
		);
	}

	/**
	 * Runs the payment tokens migration for users with saved payments.
	 *
	 * @param LoggerInterface $logger The logger.
	 * @return void
	 */
	public function migrate_payment_tokens( LoggerInterface $logger ): void {
		$initialized = get_option( 'ppcp_payment_tokens_migration_initialized', null );
		if ( $initialized ) {
			return;
		}
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$customers = new WP_User_Query(
			array(
				'fields'   => 'ID',
				'limit'    => -1,
				'meta_key' => 'ppcp-vault-token',
			)
		);
		// phpcs:enable

		$customers = $customers->get_results();
		if ( count( $customers ) === 0 ) {
			$logger->info( 'No customers for payment tokens migration.' );
			return;
		}

		$logger->info( 'Identified ' . (string) count( $customers ) . ' users with payment tokens. Initiating token migration.' );
		update_option( 'ppcp_payment_tokens_migration_initialized', true );

		$interval_in_seconds = 5;
		$timestamp           = time();

		foreach ( $customers as $id ) {
			$tokens                   = array_filter( get_user_meta( $id, 'ppcp-vault-token' ) );
			$skip_empty_key_migration = apply_filters( 'ppcp_skip_payment_tokens_empty_key_migration', true );
			if ( empty( $tokens ) && $skip_empty_key_migration ) {
				continue;
			}

			/**
			 * Function already exist in WooCommerce
			 *
			 * @psalm-suppress UndefinedFunction
			 */
			as_schedule_single_action(
				$timestamp,
				'woocommerce_paypal_payments_payment_tokens_migration',
				array( 'customer_id' => $id )
			);

			$timestamp += $interval_in_seconds;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getKey() {  }
}
