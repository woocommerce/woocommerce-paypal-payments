<?php
/**
 * The subscription module.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use Exception;
use WC_Product;
use WC_Product_Subscription_Variation;
use WC_Product_Variable;
use WC_Product_Variable_Subscription;
use WC_Subscriptions_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
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
use WP_Post;

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
				$paypal_subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $paypal_subscription_id ) {
					return;
				}

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

				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$subscription_id = wc_clean( wp_unslash( $_POST['post_ID'] ?? '' ) );
				if ( ! $subscription_id ) {
					return $data;
				}
				$subscription = wc_get_order( $subscription_id );
				if ( ! is_a( $subscription, WC_Subscription::class ) ) {
					return $data;
				}

				if (
					$wc_order_action === 'wcs_process_renewal' && $subscription->get_payment_method() === CreditCardGateway::ID
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

		if ( defined( 'PPCP_FLAG_SUBSCRIPTIONS_API' ) && PPCP_FLAG_SUBSCRIPTIONS_API ) {
			$this->subscriptions_api_integration( $c );
		}

		add_action(
			'admin_enqueue_scripts',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $hook ) use ( $c ) {
				if ( ! is_string( $hook ) ) {
					return;
				}
				$settings          = $c->get( 'wcgateway.settings' );
				$subscription_mode = $settings->has( 'subscriptions_mode' ) ? $settings->get( 'subscriptions_mode' ) : '';
				if ( $hook !== 'post.php' || $subscription_mode !== 'subscriptions_api' ) {
					return;
				}

				//phpcs:disable WordPress.Security.NonceVerification.Recommended
				$post_id = wc_clean( wp_unslash( $_GET['post'] ?? '' ) );
				$product = wc_get_product( $post_id );
				if ( ! ( is_a( $product, WC_Product::class ) || is_a( $product, WC_Product_Subscription_Variation::class ) ) || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
					return;
				}

				$module_url = $c->get( 'subscription.module.url' );
				wp_enqueue_script(
					'ppcp-paypal-subscription',
					untrailingslashit( $module_url ) . '/assets/js/paypal-subscription.js',
					array( 'jquery' ),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				$products = array( $this->set_product_config( $product ) );
				if ( $product->get_type() === 'variable-subscription' ) {
					$products = array();
					assert( $product instanceof WC_Product_Variable );
					$available_variations = $product->get_available_variations();
					foreach ( $available_variations as $variation ) {
						/**
						 * The method is defined in WooCommerce.
						 *
						 * @psalm-suppress UndefinedMethod
						 */
						$variation  = wc_get_product_object( 'variation', $variation['variation_id'] );
						$products[] = $this->set_product_config( $variation );
					}
				}

				wp_localize_script(
					'ppcp-paypal-subscription',
					'PayPalCommerceGatewayPayPalSubscriptionProducts',
					$products
				);
			}
		);

		$endpoint = $c->get( 'subscription.deactivate-plan-endpoint' );
		assert( $endpoint instanceof DeactivatePlanEndpoint );
		add_action(
			'wc_ajax_' . DeactivatePlanEndpoint::ENDPOINT,
			array( $endpoint, 'handle_request' )
		);

		add_action(
			'add_meta_boxes',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( string $post_type, $post_or_order_object ) use ( $c ) {
				if ( ! function_exists( 'wcs_get_subscription' ) ) {
					return;
				}

				$order = ( $post_or_order_object instanceof WP_Post )
					? wc_get_order( $post_or_order_object->ID )
					: $post_or_order_object;

				if ( ! is_a( $order, WC_Order::class ) ) {
					return;
				}

				$subscription = wcs_get_subscription( $order->get_id() );
				if ( ! is_a( $subscription, WC_Subscription::class ) ) {
					return;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( ! $subscription_id ) {
					return;
				}

				$screen_id = wc_get_page_screen_id( 'shop_subscription' );
				remove_meta_box( 'woocommerce-subscription-schedule', $screen_id, 'side' );

				$environment = $c->get( 'onboarding.environment' );
				add_meta_box(
					'ppcp_paypal_subscription',
					__( 'PayPal Subscription', 'woocommerce-paypal-payments' ),
					function() use ( $subscription_id, $environment ) {
						$host = $environment->current_environment_is( Environment::SANDBOX ) ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
						$url  = trailingslashit( $host ) . 'billing/subscriptions/' . $subscription_id;
						echo '<p>' . esc_html__( 'This subscription is linked to a PayPal Subscription, Cancel it to unlink.', 'woocommerce-paypal-payments' ) . '</p>';
						echo '<p><strong>' . esc_html__( 'Subscription:', 'woocommerce-paypal-payments' ) . '</strong> <a href="' . esc_url( $url ) . '" target="_blank">' . esc_attr( $subscription_id ) . '</a></p>';
					},
					$post_type,
					'side'
				);

			},
			30,
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

	/**
	 * Adds PayPal subscriptions API integration.
	 *
	 * @param ContainerInterface $c The container.
	 * @return void
	 * @throws Exception When something went wrong.
	 */
	protected function subscriptions_api_integration( ContainerInterface $c ): void {
		add_action(
			'save_post',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $product_id ) use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					$subscriptions_mode = $settings->get( 'subscriptions_mode' );
				} catch ( NotFoundException $exception ) {
					return;
				}

				$nonce = wc_clean( wp_unslash( $_POST['_wcsnonce'] ?? '' ) );
				if (
					$subscriptions_mode !== 'subscriptions_api'
					|| ! is_string( $nonce )
					|| ! wp_verify_nonce( $nonce, 'wcs_subscription_meta' ) ) {
					return;
				}

				$product = wc_get_product( $product_id );
				if ( ! is_a( $product, WC_Product::class ) ) {
					return;
				}

				$subscriptions_api_handler = $c->get( 'subscription.api-handler' );
				assert( $subscriptions_api_handler instanceof SubscriptionsApiHandler );
				$this->update_subscription_product_meta( $product, $subscriptions_api_handler );
			},
			12
		);

		add_action(
			'woocommerce_save_product_variation',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $variation_id ) use ( $c ) {
				$wcsnonce_save_variations = wc_clean( wp_unslash( $_POST['_wcsnonce_save_variations'] ?? '' ) );
				if (
					! WC_Subscriptions_Product::is_subscription( $variation_id )
					|| ! is_string( $wcsnonce_save_variations )
					|| ! wp_verify_nonce( $wcsnonce_save_variations, 'wcs_subscription_variations' )
				) {
					return;
				}

				$product = wc_get_product( $variation_id );
				if ( ! is_a( $product, WC_Product_Subscription_Variation::class ) ) {
					return;
				}

				$subscriptions_api_handler = $c->get( 'subscription.api-handler' );
				assert( $subscriptions_api_handler instanceof SubscriptionsApiHandler );
				$this->update_subscription_product_meta( $product, $subscriptions_api_handler );
			}
		);

		add_action(
			'woocommerce_process_shop_subscription_meta',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $id, $subscription ) use ( $c ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
					assert( $subscriptions_endpoint instanceof BillingSubscriptions );

					if ( $subscription->get_status() === 'cancelled' ) {
						try {
							$subscriptions_endpoint->cancel( $subscription_id );
						} catch ( RuntimeException $exception ) {
							$error = $exception->getMessage();
							if ( is_a( $exception, PayPalApiException::class ) ) {
								$error = $exception->get_details( $error );
							}

							$logger = $c->get( 'woocommerce.logger.woocommerce' );
							$logger->error( 'Could not cancel subscription product on PayPal. ' . $error );
						}
					}

					if ( $subscription->get_status() === 'pending-cancel' ) {
						try {
							$subscriptions_endpoint->suspend( $subscription_id );
						} catch ( RuntimeException $exception ) {
							$error = $exception->getMessage();
							if ( is_a( $exception, PayPalApiException::class ) ) {
								$error = $exception->get_details( $error );
							}

							$logger = $c->get( 'woocommerce.logger.woocommerce' );
							$logger->error( 'Could not suspend subscription product on PayPal. ' . $error );
						}
					}

					if ( $subscription->get_status() === 'active' ) {
						try {
							$current_subscription = $subscriptions_endpoint->subscription( $subscription_id );
							if ( $current_subscription->status === 'SUSPENDED' ) {
								$subscriptions_endpoint->activate( $subscription_id );
							}
						} catch ( RuntimeException $exception ) {
							$error = $exception->getMessage();
							if ( is_a( $exception, PayPalApiException::class ) ) {
								$error = $exception->get_details( $error );
							}

							$logger = $c->get( 'woocommerce.logger.woocommerce' );
							$logger->error( 'Could not reactivate subscription product on PayPal. ' . $error );
						}
					}
				}
			},
			20,
			2
		);

		add_filter(
			'woocommerce_order_actions',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $actions, $subscription ): array {
				if ( ! is_a( $subscription, WC_Subscription::class ) ) {
					return $actions;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id && isset( $actions['wcs_process_renewal'] ) ) {
					unset( $actions['wcs_process_renewal'] );
				}

				return $actions;
			},
			20,
			2
		);

		add_filter(
			'wcs_view_subscription_actions',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $actions, $subscription ): array {
				if ( ! is_a( $subscription, WC_Subscription::class ) ) {
					return $actions;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id && $subscription->get_status() === 'active' ) {
					$url = wp_nonce_url(
						add_query_arg(
							array(
								'change_subscription_to'   => 'cancelled',
								'ppcp_cancel_subscription' => $subscription->get_id(),
							)
						),
						'ppcp_cancel_subscription_nonce'
					);

					array_unshift(
						$actions,
						array(
							'url'  => esc_url( $url ),
							'name' => esc_html__( 'Cancel', 'woocommerce-paypal-payments' ),
						)
					);

					$actions['cancel']['name'] = esc_html__( 'Suspend', 'woocommerce-paypal-payments' );
					unset( $actions['subscription_renewal_early'] );
				}

				return $actions;
			},
			11,
			2
		);

		add_action(
			'wp_loaded',
			function() use ( $c ) {
				if ( ! function_exists( 'wcs_get_subscription' ) ) {
					return;
				}

				$cancel_subscription_id = wc_clean( wp_unslash( $_GET['ppcp_cancel_subscription'] ?? '' ) );
				$subscription           = wcs_get_subscription( absint( $cancel_subscription_id ) );
				if ( ! wcs_is_subscription( $subscription ) || $subscription === false ) {
					return;
				}

				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				$nonce           = wc_clean( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
				if ( ! is_string( $nonce ) ) {
					return;
				}

				if (
					$subscription_id
					&& $cancel_subscription_id
					&& $nonce
				) {
					if (
						! wp_verify_nonce( $nonce, 'ppcp_cancel_subscription_nonce' )
						|| ! user_can( get_current_user_id(), 'edit_shop_subscription_status', $subscription->get_id() )
					) {
						return;
					}

					$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
					$subscription_id        = $subscription->get_meta( 'ppcp_subscription' );
					try {
						$subscriptions_endpoint->cancel( $subscription_id );

						$subscription->update_status( 'cancelled' );
						$subscription->add_order_note( __( 'Subscription cancelled by the subscriber from their account page.', 'woocommerce-paypal-payments' ) );
						wc_add_notice( __( 'Your subscription has been cancelled.', 'woocommerce-paypal-payments' ) );

						wp_safe_redirect( $subscription->get_view_order_url() );
						exit;
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not cancel subscription product on PayPal. ' . $error );
					}
				}
			},
			100
		);

		add_action(
			'woocommerce_subscription_before_actions',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $subscription ) use ( $c ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					$environment = $c->get( 'onboarding.environment' );
					$host        = $environment->current_environment_is( Environment::SANDBOX ) ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
					?>
					<tr>
						<td><?php esc_html_e( 'PayPal Subscription', 'woocommerce-paypal-payments' ); ?></td>
						<td>
							<a href="<?php echo esc_url( $host . "/myaccount/autopay/connect/{$subscription_id}" ); ?>" id="ppcp-subscription-id" target="_blank"><?php echo esc_html( $subscription_id ); ?></a>
						</td>
					</tr>
					<?php
				}
			}
		);

		add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $query, $query_vars ): array {
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

		add_action(
			'woocommerce_customer_changed_subscription_to_cancelled',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $subscription ) use ( $c ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
					assert( $subscriptions_endpoint instanceof BillingSubscriptions );

					try {
						$subscriptions_endpoint->suspend( $subscription_id );
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not suspend subscription product on PayPal. ' . $error );
					}
				}
			}
		);

		add_action(
			'woocommerce_customer_changed_subscription_to_active',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $subscription ) use ( $c ) {
				$subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $subscription_id ) {
					$subscriptions_endpoint = $c->get( 'api.endpoint.billing-subscriptions' );
					assert( $subscriptions_endpoint instanceof BillingSubscriptions );

					try {
						$subscriptions_endpoint->activate( $subscription_id );
					} catch ( RuntimeException $exception ) {
						$error = $exception->getMessage();
						if ( is_a( $exception, PayPalApiException::class ) ) {
							$error = $exception->get_details( $error );
						}

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						$logger->error( 'Could not active subscription product on PayPal. ' . $error );
					}
				}
			}
		);

		add_action(
			'woocommerce_product_options_general_product_data',
			function() use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					$subscriptions_mode = $settings->get( 'subscriptions_mode' );
					if ( $subscriptions_mode === 'subscriptions_api' ) {
						/**
						 * Needed for getting global post object.
						 *
						 * @psalm-suppress InvalidGlobal
						 */
						global $post;
						$product = wc_get_product( $post->ID );
						if ( ! is_a( $product, WC_Product::class ) ) {
							return;
						}

						$environment = $c->get( 'onboarding.environment' );
						echo '<div class="options_group subscription_pricing show_if_subscription hidden">';
						$this->render_paypal_subscription_fields( $product, $environment );
						echo '</div>';

					}
				} catch ( NotFoundException $exception ) {
					return;
				}
			}
		);

		add_action(
			'woocommerce_variation_options_pricing',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $loop, $variation_data, $variation ) use ( $c ) {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				try {
					$subscriptions_mode = $settings->get( 'subscriptions_mode' );
					if ( $subscriptions_mode === 'subscriptions_api' ) {
						$product = wc_get_product( $variation->ID );
						if ( ! is_a( $product, WC_Product_Subscription_Variation::class ) ) {
							return;
						}

						$environment = $c->get( 'onboarding.environment' );
						$this->render_paypal_subscription_fields( $product, $environment );

					}
				} catch ( NotFoundException $exception ) {
					return;
				}
			},
			10,
			3
		);
	}

	/**
	 * Render PayPal Subscriptions fields.
	 *
	 * @param WC_Product  $product WC Product.
	 * @param Environment $environment The environment.
	 * @return void
	 */
	private function render_paypal_subscription_fields( WC_Product $product, Environment $environment ): void {
		$enable_subscription_product = $product->get_meta( '_ppcp_enable_subscription_product' );
		$style                       = $product->get_type() === 'subscription_variation' ? 'float:left; width:150px;' : '';

		echo '<p class="form-field">';
		echo sprintf(
		// translators: %1$s and %2$s are label open and close tags.
			esc_html__( '%1$sConnect to PayPal%2$s', 'woocommerce-paypal-payments' ),
			'<label for="_ppcp_enable_subscription_product" style="' . esc_attr( $style ) . '">',
			'</label>'
		);
		echo '<input type="checkbox" id="ppcp_enable_subscription_product" name="_ppcp_enable_subscription_product" value="yes" ' . checked( $enable_subscription_product, 'yes', false ) . '/>';
		echo sprintf(
		// translators: %1$s and %2$s are label open and close tags.
			esc_html__( '%1$sConnect Product to PayPal Subscriptions Plan%2$s', 'woocommerce-paypal-payments' ),
			'<span class="description">',
			'</span>'
		);

		echo wc_help_tip( esc_html__( 'Create a subscription product and plan to bill customers at regular intervals. Be aware that certain subscription settings cannot be modified once the PayPal Subscription is linked to this product. Unlink the product to edit disabled fields.', 'woocommerce-paypal-payments' ) );
		echo '</p>';

		$subscription_product   = $product->get_meta( 'ppcp_subscription_product' );
		$subscription_plan      = $product->get_meta( 'ppcp_subscription_plan' );
		$subscription_plan_name = $product->get_meta( '_ppcp_subscription_plan_name' );
		if ( $subscription_product && $subscription_plan ) {
			if ( $enable_subscription_product !== 'yes' ) {
				echo sprintf(
				// translators: %1$s and %2$s are button and wrapper html tags.
					esc_html__( '%1$sUnlink PayPal Subscription Plan%2$s', 'woocommerce-paypal-payments' ),
					'<p class="form-field" id="ppcp-enable-subscription"><label></label><button class="button" id="ppcp-unlink-sub-plan-' . esc_attr( (string) $product->get_id() ) . '">',
					'</button><span class="spinner is-active" id="spinner-unlink-plan" style="float: none; display:none;"></span></p>'
				);
				echo sprintf(
				// translators: %1$s and %2$s is open and closing paragraph tag.
					esc_html__( '%1$sPlan unlinked successfully ✔️%2$s', 'woocommerce-paypal-payments' ),
					'<p class="form-field" id="pcpp-plan-unlinked" style="display: none;">',
					'</p>'
				);
			}

			$host = $environment->current_environment_is( Environment::SANDBOX ) ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
			echo sprintf(
			// translators: %1$s and %2$s are wrapper html tags.
				esc_html__( '%1$sProduct%2$s', 'woocommerce-paypal-payments' ),
				'<p class="form-field" id="pcpp-product"><label style="' . esc_attr( $style ) . '">',
				'</label><a href="' . esc_url( $host . '/billing/plans/products/' . $subscription_product['id'] ) . '" target="_blank">' . esc_attr( $subscription_product['id'] ) . '</a></p>'
			);
			echo sprintf(
			// translators: %1$s and %2$s are wrapper html tags.
				esc_html__( '%1$sPlan%2$s', 'woocommerce-paypal-payments' ),
				'<p class="form-field" id="pcpp-plan"><label style="' . esc_attr( $style ) . '">',
				'</label><a href="' . esc_url( $host . '/billing/plans/' . $subscription_plan['id'] ) . '" target="_blank">' . esc_attr( $subscription_plan['id'] ) . '</a></p>'
			);
		} else {
			echo sprintf(
			// translators: %1$s and %2$s are wrapper html tags.
				esc_html__( '%1$sPlan Name%2$s', 'woocommerce-paypal-payments' ),
				'<p class="form-field"><label for="_ppcp_subscription_plan_name">',
				'</label><input type="text" class="short" id="ppcp_subscription_plan_name" name="_ppcp_subscription_plan_name" value="' . esc_attr( $subscription_plan_name ) . '"></p>'
			);
		}
	}

	/**
	 * Updates subscription product meta.
	 *
	 * @param WC_Product              $product The product.
	 * @param SubscriptionsApiHandler $subscriptions_api_handler The subscription api handler.
	 * @return void
	 */
	private function update_subscription_product_meta( WC_Product $product, SubscriptionsApiHandler $subscriptions_api_handler ): void {
		// phpcs:ignore WordPress.Security.NonceVerification
		$enable_subscription_product = wc_clean( wp_unslash( $_POST['_ppcp_enable_subscription_product'] ?? '' ) );
		$product->update_meta_data( '_ppcp_enable_subscription_product', $enable_subscription_product );
		$product->save();

		if ( ( $product->get_type() === 'subscription' || $product->get_type() === 'subscription_variation' ) && $enable_subscription_product === 'yes' ) {
			if ( $product->meta_exists( 'ppcp_subscription_product' ) && $product->meta_exists( 'ppcp_subscription_plan' ) ) {
				$subscriptions_api_handler->update_product( $product );
				$subscriptions_api_handler->update_plan( $product );
				return;
			}

			if ( ! $product->meta_exists( 'ppcp_subscription_product' ) ) {
				$subscriptions_api_handler->create_product( $product );
			}

			if ( $product->meta_exists( 'ppcp_subscription_product' ) && ! $product->meta_exists( 'ppcp_subscription_plan' ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification
				$subscription_plan_name = wc_clean( wp_unslash( $_POST['_ppcp_subscription_plan_name'] ?? '' ) );
				if ( ! is_string( $subscription_plan_name ) ) {
					return;
				}

				$product->update_meta_data( '_ppcp_subscription_plan_name', $subscription_plan_name );
				$product->save();

				$subscriptions_api_handler->create_plan( $subscription_plan_name, $product );
			}
		}
	}

	/**
	 * Returns subscription product configuration.
	 *
	 * @param WC_Product $product The product.
	 * @return array
	 */
	private function set_product_config( WC_Product $product ): array {
		$plan    = $product->get_meta( 'ppcp_subscription_plan' ) ?? array();
		$plan_id = $plan['id'] ?? '';

		return array(
			'product_connected' => $product->get_meta( '_ppcp_enable_subscription_product' ) ?? '',
			'plan_id'           => $plan_id,
			'product_id'        => $product->get_id(),
			'ajax'              => array(
				'deactivate_plan' => array(
					'endpoint' => \WC_AJAX::get_endpoint( DeactivatePlanEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( DeactivatePlanEndpoint::ENDPOINT ),
				),
			),
		);
	}
}
