<?php
/**
 * The save payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\UserIdToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreateSetupToken;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class SavePaymentMethodsModule
 */
class SavePaymentMethodsModule implements ModuleInterface {

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
		if ( ! $c->get( 'save-payment-methods.eligible' ) ) {
			return;
		}

		// Adds `id_token` to localized script data.
		add_filter(
			'woocommerce_paypal_payments_localized_script_data',
			function( array $localized_script_data ) use ( $c ) {
				$api = $c->get( 'api.user-id-token' );
				assert( $api instanceof UserIdToken );

				try {
					$target_customer_id = '';
					if ( is_user_logged_in() ) {
						$target_customer_id = get_user_meta( get_current_user_id(), '_ppcp_target_customer_id', true );
					}

					$id_token                                      = $api->id_token( $target_customer_id );
					$localized_script_data['save_payment_methods'] = array(
						'id_token' => $id_token,
					);

					$localized_script_data['data_client_id']['set_attribute'] = false;

				} catch ( RuntimeException $exception ) {
					$logger = $c->get( 'woocommerce.logger.woocommerce' );
					assert( $logger instanceof LoggerInterface );

					$error = $exception->getMessage();
					if ( is_a( $exception, PayPalApiException::class ) ) {
						$error = $exception->get_details( $error );
					}

					$logger->error( $error );
				}

				return $localized_script_data;
			}
		);

		// Adds attributes needed to save payment method.
		add_filter(
			'ppcp_create_order_request_body_data',
			function( array $data ): array {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$wc_order_action = wc_clean( wp_unslash( $_POST['wc_order_action'] ?? '' ) );
				if ( $wc_order_action === 'wcs_process_renewal' ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$subscription_id = wc_clean( wp_unslash( $_POST['post_ID'] ?? '' ) );
					$subscription    = wcs_get_subscription( (int) $subscription_id );
					if ( $subscription ) {
						$customer_id = $subscription->get_customer_id();
						$wc_tokens   = WC_Payment_Tokens::get_customer_tokens( $customer_id, PayPalGateway::ID );
						foreach ( $wc_tokens as $token ) {
							$data['payment_source'] = array(
								'paypal' => array(
									'vault_id' => $token->get_token(),
								),
							);

							return $data;
						}
					}
				}

				$data['payment_source'] = array(
					'paypal' => array(
						'attributes' => array(
							'vault' => array(
								'store_in_vault' => 'ON_SUCCESS',
								'usage_type'     => 'MERCHANT',
							),
						),
					),
				);

				return $data;
			}
		);

		add_action(
			'woocommerce_paypal_payments_after_order_processor',
			function( WC_Order $wc_order, Order $order ) use ( $c ) {
				$payment_source = $order->payment_source();
				assert( $payment_source instanceof PaymentSource );

				$payment_vault_attributes = $payment_source->properties()->attributes->vault ?? null;
				if ( $payment_vault_attributes ) {
					update_user_meta( $wc_order->get_customer_id(), '_ppcp_target_customer_id', $payment_vault_attributes->customer->id );

					$payment_token_helper = $c->get( 'vaulting.payment-token-helper' );
					assert( $payment_token_helper instanceof PaymentTokenHelper );

					$payment_token_factory = $c->get( 'vaulting.payment-token-factory' );
					assert( $payment_token_factory instanceof PaymentTokenFactory );

					$logger = $c->get( 'woocommerce.logger.woocommerce' );
					assert( $logger instanceof LoggerInterface );

					$wc_payment_tokens = $c->get( 'save-payment-methods.wc-payment-tokens' );
					assert( $wc_payment_tokens instanceof WooCommercePaymentTokens );

					$wc_payment_tokens->create_payment_token_paypal(
						$wc_order->get_customer_id(),
						$payment_vault_attributes->id,
						$payment_source->properties()->email_address ?? ''
					);
				}
			},
			10,
			2
		);

		add_filter( 'woocommerce_paypal_payments_disable_add_payment_method', '__return_false' );

		add_filter('woocommerce_paypal_payments_subscription_renewal_return_before_create_order_without_token', '__return_false');

		add_action(
			'wp_enqueue_scripts',
			function() use ( $c ) {
				if ( ! is_user_logged_in() || ! is_add_payment_method_page() ) {
					return;
				}

				$module_url = $c->get( 'save-payment-methods.module.url' );
				wp_enqueue_script(
					'ppcp-add-payment-method',
					untrailingslashit( $module_url ) . '/assets/js/add-payment-method.js',
					array( 'jquery' ),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				$api = $c->get( 'api.user-id-token' );
				assert( $api instanceof UserIdToken );

				try {
					$target_customer_id = '';
					if ( is_user_logged_in() ) {
						$target_customer_id = get_user_meta( get_current_user_id(), '_ppcp_target_customer_id', true );
					}

					$id_token = $api->id_token( $target_customer_id );

					wp_localize_script(
						'ppcp-add-payment-method',
						'ppcp_add_payment_method',
						array(
							'client_id'   => $c->get( 'button.client_id' ),
							'merchant_id' => $c->get( 'api.merchant_id' ),
							'id_token'    => $id_token,
							'ajax'        => array(
								'create_setup_token'   => array(
									'endpoint' => \WC_AJAX::get_endpoint( CreateSetupToken::ENDPOINT ),
									'nonce'    => wp_create_nonce( CreateSetupToken::nonce() ),
								),
								'create_payment_token' => array(
									'endpoint' => \WC_AJAX::get_endpoint( CreatePaymentToken::ENDPOINT ),
									'nonce'    => wp_create_nonce( CreatePaymentToken::nonce() ),
								),
							),
						)
					);
				} catch ( RuntimeException $exception ) {
					$logger = $c->get( 'woocommerce.logger.woocommerce' );
					assert( $logger instanceof LoggerInterface );

					$error = $exception->getMessage();
					if ( is_a( $exception, PayPalApiException::class ) ) {
						$error = $exception->get_details( $error );
					}

					$logger->error( $error );
				}
			}
		);

		add_action(
			'woocommerce_add_payment_method_form_bottom',
			function () {
				if ( ! is_user_logged_in() || ! is_add_payment_method_page() ) {
					return;
				}

				echo '<div id="ppc-button-' . esc_attr( PayPalGateway::ID ) . '-save-payment-method"></div>';
			}
		);

		add_action(
			'wc_ajax_' . CreateSetupToken::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'save-payment-methods.endpoint.create-setup-token' );
				assert( $endpoint instanceof CreateSetupToken );

				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . CreatePaymentToken::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'save-payment-methods.endpoint.create-payment-token' );
				assert( $endpoint instanceof CreatePaymentToken );

				$endpoint->handle_request();
			}
		);
	}
}
