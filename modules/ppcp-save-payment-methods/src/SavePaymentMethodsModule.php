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
use WooCommerce\PayPalCommerce\ApiClient\Authentication\UserIdToken;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingAgreementsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\ContextTrait;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentTokenForGuest;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreateSetupToken;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

/**
 * Class SavePaymentMethodsModule
 */
class SavePaymentMethodsModule implements ServiceModule, ExtendingModule, ExecutableModule {
	use ModuleClassNameIdTrait;
	use ContextTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function extensions(): array {
		return require __DIR__ . '/../extensions.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): bool {
		if ( ! $c->get( 'save-payment-methods.eligible' ) ) {
			return true;
		}

		$settings = $c->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		$billing_agreements_endpoint = $c->get( 'api.endpoint.billing-agreements' );
		assert( $billing_agreements_endpoint instanceof BillingAgreementsEndpoint );

		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			function() use ( $settings, $billing_agreements_endpoint ) {
				$reference_transaction_enabled = $billing_agreements_endpoint->reference_transaction_enabled();
				if ( $reference_transaction_enabled !== true ) {
					$settings->set( 'vault_enabled', false );
					$settings->persist();
				}
			}
		);

		if (
			( ! $settings->has( 'vault_enabled' ) || ! $settings->get( 'vault_enabled' ) )
			&& ( ! $settings->has( 'vault_enabled_dcc' ) || ! $settings->get( 'vault_enabled_dcc' ) )
		) {
			return true;
		}

		add_filter(
			'woocommerce_paypal_payments_localized_script_data',
			function( array $localized_script_data ) use ( $c ) {
				$subscriptions_helper = $c->get( 'wc-subscriptions.helper' );
				assert( $subscriptions_helper instanceof SubscriptionHelper );
				if ( ! is_user_logged_in() && ! $subscriptions_helper->cart_contains_subscription() ) {
					return $localized_script_data;
				}

				$api = $c->get( 'api.user-id-token' );
				assert( $api instanceof UserIdToken );

				$logger = $c->get( 'woocommerce.logger.woocommerce' );
				assert( $logger instanceof LoggerInterface );

				return $this->add_id_token_to_script_data( $api, $logger, $localized_script_data );
			}
		);

		// Adds attributes needed to save payment method.
		add_filter(
			'ppcp_create_order_request_body_data',
			function( array $data, string $payment_method, array $request_data ) use ( $settings ): array {
				if ( $payment_method === CreditCardGateway::ID ) {
					if ( ! $settings->has( 'vault_enabled_dcc' ) || ! $settings->get( 'vault_enabled_dcc' ) ) {
						return $data;
					}

					$save_payment_method = $request_data['save_payment_method'] ?? false;
					if ( $save_payment_method ) {
						$data['payment_source'] = array(
							'card' => array(
								'attributes' => array(
									'vault' => array(
										'store_in_vault' => 'ON_SUCCESS',
									),
								),
							),
						);

						$target_customer_id = get_user_meta( get_current_user_id(), '_ppcp_target_customer_id', true );
						if ( ! $target_customer_id ) {
							$target_customer_id = get_user_meta( get_current_user_id(), 'ppcp_customer_id', true );
						}

						if ( $target_customer_id ) {
							$data['payment_source']['card']['attributes']['customer'] = array(
								'id' => $target_customer_id,
							);
						}
					}
				}

				if ( $payment_method === PayPalGateway::ID ) {
					if ( ! $settings->has( 'vault_enabled' ) || ! $settings->get( 'vault_enabled' ) ) {
						return $data;
					}

					$funding_source = $request_data['funding_source'] ?? null;

					if ( $funding_source && $funding_source === 'venmo' ) {
						$data['payment_source'] = array(
							'venmo' => array(
								'attributes' => array(
									'vault' => array(
										'store_in_vault' => 'ON_SUCCESS',
										'usage_type'     => 'MERCHANT',
										'permit_multiple_payment_tokens' => apply_filters( 'woocommerce_paypal_payments_permit_multiple_payment_tokens', false ),
									),
								),
							),
						);
					} elseif ( $funding_source && $funding_source === 'apple_pay' ) {
						$data['payment_source'] = array(
							'apple_pay' => array(
								'stored_credential' => array(
									'payment_initiator' => 'CUSTOMER',
									'payment_type'      => 'RECURRING',
								),
								'attributes'        => array(
									'vault' => array(
										'store_in_vault' => 'ON_SUCCESS',
									),
								),
							),
						);
					} else {
						$data['payment_source'] = array(
							'paypal' => array(
								'attributes' => array(
									'vault' => array(
										'store_in_vault' => 'ON_SUCCESS',
										'usage_type'     => 'MERCHANT',
										'permit_multiple_payment_tokens' => apply_filters( 'woocommerce_paypal_payments_permit_multiple_payment_tokens', false ),
									),
								),
							),
						);
					}
				}

				return $data;
			},
			10,
			3
		);

		add_action(
			'woocommerce_paypal_payments_after_order_processor',
			function( WC_Order $wc_order, Order $order ) use ( $c ) {
				$payment_source = $order->payment_source();
				assert( $payment_source instanceof PaymentSource );

				$payment_vault_attributes = $payment_source->properties()->attributes->vault ?? null;
				if ( $payment_vault_attributes ) {
					$customer_id = $payment_vault_attributes->customer->id ?? '';
					$token_id    = $payment_vault_attributes->id ?? '';
					if ( ! $customer_id || ! $token_id ) {
						return;
					}

					update_user_meta( $wc_order->get_customer_id(), '_ppcp_target_customer_id', $customer_id );

					$wc_payment_tokens = $c->get( 'vaulting.wc-payment-tokens' );
					assert( $wc_payment_tokens instanceof WooCommercePaymentTokens );

					if ( $wc_order->get_payment_method() === CreditCardGateway::ID ) {
						$token = new \WC_Payment_Token_CC();
						$token->set_token( $token_id );
						$token->set_user_id( $wc_order->get_customer_id() );
						$token->set_gateway_id( CreditCardGateway::ID );

						$token->set_last4( $payment_source->properties()->last_digits ?? '' );
						$expiry = explode( '-', $payment_source->properties()->expiry ?? '' );
						$token->set_expiry_year( $expiry[0] ?? '' );
						$token->set_expiry_month( $expiry[1] ?? '' );
						$token->set_card_type( $payment_source->properties()->brand ?? '' );

						$token->save();
					}

					if ( $wc_order->get_payment_method() === PayPalGateway::ID ) {
						switch ( $payment_source->name() ) {
							case 'venmo':
								$wc_payment_tokens->create_payment_token_venmo(
									$wc_order->get_customer_id(),
									$token_id,
									$payment_source->properties()->email_address ?? ''
								);
								break;
							case 'apple_pay':
								$wc_payment_tokens->create_payment_token_applepay(
									$wc_order->get_customer_id(),
									$token_id
								);
								break;
							case 'paypal':
							default:
								$wc_payment_tokens->create_payment_token_paypal(
									$wc_order->get_customer_id(),
									$token_id,
									$payment_source->properties()->email_address ?? ''
								);
								break;
						}
					}
				}
			},
			10,
			2
		);

		add_filter( 'woocommerce_paypal_payments_disable_add_payment_method', '__return_false' );
		add_filter( 'woocommerce_paypal_payments_should_render_card_custom_fields', '__return_false' );

		add_action(
			'wp_enqueue_scripts',
			function() use ( $c ) {
				if ( ! is_user_logged_in() || ! ( $this->is_add_payment_method_page() || $this->is_subscription_change_payment_method_page() ) ) {
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
						if ( ! $target_customer_id ) {
							$target_customer_id = get_user_meta( get_current_user_id(), 'ppcp_customer_id', true );
						}
					}

					$id_token = $api->id_token( $target_customer_id );

					$settings = $c->get( 'wcgateway.settings' );
					assert( $settings instanceof Settings );

					$verification_method =
						$settings->has( '3d_secure_contingency' )
							? apply_filters( 'woocommerce_paypal_payments_three_d_secure_contingency', $settings->get( '3d_secure_contingency' ) )
							: '';

					$change_payment_method = wc_clean( wp_unslash( $_GET['change_payment_method'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

					wp_localize_script(
						'ppcp-add-payment-method',
						'ppcp_add_payment_method',
						array(
							'client_id'               => $c->get( 'button.client_id' ),
							'merchant_id'             => $c->get( 'api.merchant_id' ),
							'id_token'                => $id_token,
							'payment_methods_page'    => wc_get_account_endpoint_url( 'payment-methods' ),
							'view_subscriptions_page' => wc_get_account_endpoint_url( 'view-subscription' ),
							'is_subscription_change_payment_page' => $this->is_subscription_change_payment_method_page(),
							'subscription_id_to_change_payment' => $this->is_subscription_change_payment_method_page() ? (int) $change_payment_method : 0,
							'error_message'           => __( 'Could not save payment method.', 'woocommerce-paypal-payments' ),
							'verification_method'     => $verification_method,
							'ajax'                    => array(
								'create_setup_token'   => array(
									'endpoint' => \WC_AJAX::get_endpoint( CreateSetupToken::ENDPOINT ),
									'nonce'    => wp_create_nonce( CreateSetupToken::nonce() ),
								),
								'create_payment_token' => array(
									'endpoint' => \WC_AJAX::get_endpoint( CreatePaymentToken::ENDPOINT ),
									'nonce'    => wp_create_nonce( CreatePaymentToken::nonce() ),
								),
								'subscription_change_payment_method' => array(
									'endpoint' => \WC_AJAX::get_endpoint( SubscriptionChangePaymentMethod::ENDPOINT ),
									'nonce'    => wp_create_nonce( SubscriptionChangePaymentMethod::nonce() ),
								),
							),
							'labels'                  => array(
								'error' => array(
									'generic' => __(
										'Something went wrong. Please try again or choose another payment source.',
										'woocommerce-paypal-payments'
									),
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

		add_action(
			'wc_ajax_' . CreatePaymentTokenForGuest::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'save-payment-methods.endpoint.create-payment-token-for-guest' );
				assert( $endpoint instanceof CreatePaymentTokenForGuest );

				$endpoint->handle_request();
			}
		);

		add_action(
			'woocommerce_paypal_payments_before_delete_payment_token',
			function( string $token_id ) use ( $c ) {
				try {
					$endpoint = $c->get( 'api.endpoint.payment-tokens' );
					assert( $endpoint instanceof PaymentTokensEndpoint );

					$endpoint->delete( $token_id );
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

		add_filter(
			'woocommerce_paypal_payments_credit_card_gateway_vault_supports',
			function( array $supports ) use ( $c ): array {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof ContainerInterface );

				if ( $settings->has( 'vault_enabled_dcc' ) && $settings->get( 'vault_enabled_dcc' ) ) {
					$supports[] = 'tokenization';
				}

				return $supports;
			}
		);

		add_filter(
			'woocommerce_paypal_payments_save_payment_methods_eligible',
			function() {
				return true;
			}
		);

		return true;
	}

	/**
	 * Adds id token to localized script data.
	 *
	 * @param UserIdToken     $api User id token api.
	 * @param LoggerInterface $logger The logger.
	 * @param array           $localized_script_data The localized script data.
	 * @return array
	 */
	private function add_id_token_to_script_data(
		UserIdToken $api,
		LoggerInterface $logger,
		array $localized_script_data
	): array {
		try {
			$target_customer_id = '';
			if ( is_user_logged_in() ) {
				$target_customer_id = get_user_meta( get_current_user_id(), '_ppcp_target_customer_id', true );
				if ( ! $target_customer_id ) {
					$target_customer_id = get_user_meta( get_current_user_id(), 'ppcp_customer_id', true );
				}
			}

			$id_token                                      = $api->id_token( $target_customer_id );
			$localized_script_data['save_payment_methods'] = array(
				'id_token' => $id_token,
			);

			$localized_script_data['data_client_id']['set_attribute'] = false;

		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$logger->error( $error );
		}

		return $localized_script_data;
	}
}
