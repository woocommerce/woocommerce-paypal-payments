<?php
/**
 * The subscription module.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use WC_Product_Subscription;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\CatalogProducts;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Subscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class SubscriptionModule
 */
class SubscriptionModule implements ModuleInterface {

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
		add_action(
			'woocommerce_scheduled_subscription_payment_' . PayPalGateway::ID,
			function ( $amount, $order ) use ( $c ) {
				$this->renew( $order, $c );
			},
			10,
			2
		);

		add_action(
			'woocommerce_scheduled_subscription_payment_' . CreditCardGateway::ID,
			function ( $amount, $order ) use ( $c ) {
				$this->renew( $order, $c );
			},
			10,
			2
		);

		add_action(
			'woocommerce_subscription_payment_complete',
			function ( $subscription ) use ( $c ) {
				$payment_token_repository = $c->get( 'vaulting.repository.payment-token' );
				$logger                   = $c->get( 'woocommerce.logger.woocommerce' );

				$this->add_payment_token_id( $subscription, $payment_token_repository, $logger );
			}
		);

		add_filter(
			'woocommerce_gateway_description',
			function ( $description, $id ) use ( $c ) {
				$payment_token_repository = $c->get( 'vaulting.repository.payment-token' );
				$settings                 = $c->get( 'wcgateway.settings' );
				$subscription_helper      = $c->get( 'subscription.helper' );

				return $this->display_saved_paypal_payments( $settings, (string) $id, $payment_token_repository, (string) $description, $subscription_helper );
			},
			10,
			2
		);

		add_filter(
			'woocommerce_credit_card_form_fields',
			function ( $default_fields, $id ) use ( $c ) {
				$payment_token_repository = $c->get( 'vaulting.repository.payment-token' );
				$settings                 = $c->get( 'wcgateway.settings' );
				$subscription_helper      = $c->get( 'subscription.helper' );

				return $this->display_saved_credit_cards( $settings, $id, $payment_token_repository, $default_fields, $subscription_helper );
			},
			20,
			2
		);

		add_filter(
			'ppcp_create_order_request_body_data',
			function( array $data ) use ( $c ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$wc_order_action = wc_clean( wp_unslash( $_POST['wc_order_action'] ?? '' ) );
				if (
					$wc_order_action === 'wcs_process_renewal'
					&& isset( $data['payment_source']['token'] ) && $data['payment_source']['token']['type'] === 'PAYMENT_METHOD_TOKEN'
					&& isset( $data['payment_source']['token']['source']->card )
				) {
					$renewal_order_id     = absint( $data['purchase_units'][0]['custom_id'] );
					$subscriptions        = wcs_get_subscriptions_for_renewal_order( $renewal_order_id );
					$subscriptions_values = array_values( $subscriptions );
					$latest_subscription  = array_shift( $subscriptions_values );
					if ( is_a( $latest_subscription, WC_Subscription::class ) ) {
						$related_renewal_orders           = $latest_subscription->get_related_orders( 'ids', 'renewal' );
						$latest_order_id_with_transaction = array_slice( $related_renewal_orders, 1, 1, false );
						$order_id                         = ! empty( $latest_order_id_with_transaction ) ? $latest_order_id_with_transaction[0] : 0;
						if ( count( $related_renewal_orders ) === 1 ) {
							$order_id = $latest_subscription->get_parent_id();
						}

						$wc_order = wc_get_order( $order_id );
						if ( is_a( $wc_order, WC_Order::class ) ) {
							$transaction_id                                       = $wc_order->get_transaction_id();
							$data['application_context']['stored_payment_source'] = array(
								'payment_initiator' => 'MERCHANT',
								'payment_type'      => 'RECURRING',
								'usage'             => 'SUBSEQUENT',
								'previous_transaction_reference' => $transaction_id,
							);
						}
					}
				}

				return $data;
			}
		);

		add_action(
			'save_post',
			function( $product_id ) use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					$subscriptions_mode = $settings->get( 'subscriptions_mode' );
				} catch ( NotFoundException $exception ) {
					return;
				}

				if (
					$subscriptions_mode !== 'subscriptions_api'
					|| empty( $_POST['_wcsnonce'] )
					|| ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['_wcsnonce'] ) ), 'wcs_subscription_meta' ) ) {
					return;
				}

				$product = wc_get_product( $product_id );
				if ( $product->get_type() === 'subscription' ) {
					if ( ! $product->meta_exists( 'ppcp_subscription_product_id' ) ) {
						$products_endpoint = $c->get( 'api.endpoint.catalog-products' );
						assert( $products_endpoint instanceof CatalogProducts );

						try {
							$subscription_product = $products_endpoint->create( $product->get_title() );
							$product->update_meta_data( 'ppcp_subscription_product_id', $subscription_product->id );
							$product->save();
						} catch ( RuntimeException $exception ) {
							$error = $exception->getMessage();
							if ( is_a( $exception, PayPalApiException::class ) ) {
								$error = $exception->get_details( $error );
							}

							$logger = $c->get( 'woocommerce.logger.woocommerce' );
							$logger->error( 'Could not create subscription product on PayPal. ' . $error );
						}
					}

					if ( $product->get_meta( 'ppcp_subscription_product_id' ) && ! $product->meta_exists( 'ppcp_subscription_plan' ) ) {
						$subscriptions_endpoint = $c->get( 'api.endpoint.subscriptions' );
						assert( $subscriptions_endpoint instanceof Subscriptions );

						$billing_cycles = array(
							'frequency'      => array(
								'interval_unit'  => $product->get_meta( '_subscription_period' ),
								'interval_count' => $product->get_meta( '_subscription_period_interval' ),
							),
							'tenure_type'    => 'REGULAR',
							'sequence'       => 1,
							'total_cycles'   => $product->get_meta( '_subscription_length' ),
							'pricing_scheme' => array(
								'fixed_price' => array(
									'value'         => $product->get_meta( '_subscription_price' ),
									'currency_code' => 'USD',
								),
							),
						);

						$payment_preferences = array(
							'auto_bill_outstanding'     => true,
							'setup_fee'                 => array(
								'value'         => $product->get_meta( '_subscription_sign_up_fee' ) ?: '0',
								'currency_code' => 'USD',
							),
							'setup_fee_failure_action'  => 'CONTINUE',
							'payment_failure_threshold' => 3,
						);

						try {
							$subscription_plan = $subscriptions_endpoint->create_plan(
								$product->get_meta( 'ppcp_subscription_product_id' ),
								$billing_cycles,
								$payment_preferences
							);

							$product->update_meta_data( 'ppcp_subscription_plan', $subscription_plan->id );
							$product->save();
						} catch ( RuntimeException $exception ) {
							$error = $exception->getMessage();
							if ( is_a( $exception, PayPalApiException::class ) ) {
								$error = $exception->get_details( $error );
							}

							$logger = $c->get( 'woocommerce.logger.woocommerce' );
							$logger->error( 'Could not create subscription plan on PayPal. ' . $error );
						}
					}
				}
			},
			12
		);

		add_action(
			'add_meta_boxes',
			function( string $post_type ) {
				if ( $post_type === 'product' ) {
					$post_id = wc_clean( wp_unslash( $_GET['post'] ?? '' ) );
					$product    = wc_get_product( $post_id );
					if ( is_a( $product, WC_Product_Subscription::class ) ) {
						$product_id = $product->get_meta( 'ppcp_subscription_product_id' );
						$plan_id         = $product->get_meta( 'ppcp_subscription_plan' );
						if ( $product_id && $plan_id ) {
							add_meta_box(
								'ppcp_subscription',
								__( 'PayPal Subscription', 'woocommerce-paypal-payments' ),
								function() use ( $product_id, $plan_id ) {
									echo '<p>Product ID: ' . esc_attr( $product_id ) . '</p>';
									echo '<p>Plan ID: ' . esc_attr( $plan_id ) . '</p>';
								},
								$post_type,
								'side',
								'high'
							);
						}
					}
				}
			}
		);

		add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			function( $query, $query_vars ) {
				if ( ! empty( $query_vars['ppcp_subscription'] ) ) {
					$query['meta_query'][] = array(
						'key'   => 'ppcp_subscription',
						'value' => esc_attr( $query_vars['ppcp_subscription'] ),
					);
				}

				return $query;
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

	/**
	 * Handles a Subscription product renewal.
	 *
	 * @param \WC_Order               $order WooCommerce order.
	 * @param ContainerInterface|null $container The container.
	 * @return void
	 */
	protected function renew( $order, $container ) {
		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		$handler = $container->get( 'subscription.renewal-handler' );
		$handler->renew( $order );
	}

	/**
	 * Adds Payment token ID to subscription.
	 *
	 * @param \WC_Subscription       $subscription The subscription.
	 * @param PaymentTokenRepository $payment_token_repository The payment repository.
	 * @param LoggerInterface        $logger The logger.
	 */
	protected function add_payment_token_id(
		\WC_Subscription $subscription,
		PaymentTokenRepository $payment_token_repository,
		LoggerInterface $logger
	) {
		try {
			$tokens = $payment_token_repository->all_for_user_id( $subscription->get_customer_id() );
			if ( $tokens ) {
				$latest_token_id = end( $tokens )->id() ? end( $tokens )->id() : '';
				$subscription->update_meta_data( 'payment_token_id', $latest_token_id );
				$subscription->save();
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

	/**
	 * Displays saved PayPal payments.
	 *
	 * @param Settings               $settings The settings.
	 * @param string                 $id The payment gateway Id.
	 * @param PaymentTokenRepository $payment_token_repository The payment token repository.
	 * @param string                 $description The payment gateway description.
	 * @param SubscriptionHelper     $subscription_helper The subscription helper.
	 * @return string
	 */
	protected function display_saved_paypal_payments(
		Settings $settings,
		string $id,
		PaymentTokenRepository $payment_token_repository,
		string $description,
		SubscriptionHelper $subscription_helper
	): string {
		if ( $settings->has( 'vault_enabled' )
			&& $settings->get( 'vault_enabled' )
			&& PayPalGateway::ID === $id
			&& $subscription_helper->is_subscription_change_payment()
		) {
			$tokens = $payment_token_repository->all_for_user_id( get_current_user_id() );
			if ( ! $tokens || ! $payment_token_repository->tokens_contains_paypal( $tokens ) ) {
				return esc_html__(
					'No PayPal payments saved, in order to use a saved payment you first need to create it through a purchase.',
					'woocommerce-paypal-payments'
				);
			}

			$output = sprintf(
				'<p class="form-row form-row-wide"><label>%1$s</label><select id="saved-paypal-payment" name="saved_paypal_payment">',
				esc_html__( 'Select a saved PayPal payment', 'woocommerce-paypal-payments' )
			);
			foreach ( $tokens as $token ) {
				if ( isset( $token->source()->paypal ) ) {
					$output .= sprintf(
						'<option value="%1$s">%2$s</option>',
						$token->id(),
						$token->source()->paypal->payer->email_address
					);
				}
			}
				$output .= '</select></p>';

				return $output;
		}

		return $description;
	}

	/**
	 * Displays saved credit cards.
	 *
	 * @param Settings               $settings The settings.
	 * @param string                 $id The payment gateway Id.
	 * @param PaymentTokenRepository $payment_token_repository The payment token repository.
	 * @param array                  $default_fields Default payment gateway fields.
	 * @param SubscriptionHelper     $subscription_helper The subscription helper.
	 * @return array|mixed|string
	 * @throws NotFoundException When setting was not found.
	 */
	protected function display_saved_credit_cards(
		Settings $settings,
		string $id,
		PaymentTokenRepository $payment_token_repository,
		array $default_fields,
		SubscriptionHelper $subscription_helper
	) {

		if ( $settings->has( 'vault_enabled_dcc' )
			&& $settings->get( 'vault_enabled_dcc' )
			&& $subscription_helper->is_subscription_change_payment()
			&& CreditCardGateway::ID === $id
		) {
			$tokens = $payment_token_repository->all_for_user_id( get_current_user_id() );
			if ( ! $tokens || ! $payment_token_repository->tokens_contains_card( $tokens ) ) {
				$default_fields                      = array();
				$default_fields['saved-credit-card'] = esc_html__(
					'No Credit Card saved, in order to use a saved Credit Card you first need to create it through a purchase.',
					'woocommerce-paypal-payments'
				);
				return $default_fields;
			}

			$output = sprintf(
				'<p class="form-row form-row-wide"><label>%1$s</label><select id="saved-credit-card" name="saved_credit_card">',
				esc_html__( 'Select a saved Credit Card payment', 'woocommerce-paypal-payments' )
			);
			foreach ( $tokens as $token ) {
				if ( isset( $token->source()->card ) ) {
					$output .= sprintf(
						'<option value="%1$s">%2$s ...%3$s</option>',
						$token->id(),
						$token->source()->card->brand,
						$token->source()->card->last_digits
					);
				}
			}
			$output .= '</select></p>';

			$default_fields                      = array();
			$default_fields['saved-credit-card'] = $output;
			return $default_fields;
		}

		return $default_fields;
	}
}
