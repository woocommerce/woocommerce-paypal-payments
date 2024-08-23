<?php
/**
 * The subscription module.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

/**
 * Class SubscriptionModule
 */
class WcSubscriptionsModule implements ServiceModule, ExtendingModule, ExecutableModule {
	use ModuleClassNameIdTrait;
	use TransactionIdHandlingTrait;

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
		$this->add_gateways_support( $c );
		add_action(
			'woocommerce_scheduled_subscription_payment_' . PayPalGateway::ID,
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $amount, $order ) use ( $c ) {
				$this->renew( $order, $c );
			},
			10,
			2
		);

		add_action(
			'woocommerce_scheduled_subscription_payment_' . CreditCardGateway::ID,
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $amount, $order ) use ( $c ) {
				$this->renew( $order, $c );
			},
			10,
			2
		);

		add_action(
			'woocommerce_subscription_payment_complete',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $subscription ) use ( $c ) {
				if ( ! in_array( $subscription->get_payment_method(), array( PayPalGateway::ID, CreditCardGateway::ID, CardButtonGateway::ID ), true ) ) {
					return;
				}

				$paypal_subscription_id = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
				if ( $paypal_subscription_id ) {
					return;
				}

				$payment_token_repository = $c->get( 'vaulting.repository.payment-token' );
				$logger                   = $c->get( 'woocommerce.logger.woocommerce' );

				if ( ! $c->has( 'save-payment-methods.eligible' ) || ! $c->get( 'save-payment-methods.eligible' ) ) {
					$this->add_payment_token_id( $subscription, $payment_token_repository, $logger );
				}

				if ( count( $subscription->get_related_orders() ) === 1 ) {
					$parent_order = $subscription->get_parent();
					if ( is_a( $parent_order, WC_Order::class ) ) {
						// Update the initial payment method title if not the same as the first order.
						$payment_method_title = $parent_order->get_payment_method_title();
						if (
							$payment_method_title
							&& $subscription instanceof \WC_Subscription
							&& $subscription->get_payment_method_title() !== $payment_method_title
						) {
							$subscription->set_payment_method_title( $payment_method_title );
							$subscription->save();
						}
					}
				}
			}
		);

		add_filter(
			'woocommerce_gateway_description',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $description, $id ) use ( $c ) {
				$payment_token_repository = $c->get( 'vaulting.repository.payment-token' );
				$settings                 = $c->get( 'wcgateway.settings' );
				$subscription_helper      = $c->get( 'wc-subscriptions.helper' );

				return $this->display_saved_paypal_payments( $settings, (string) $id, $payment_token_repository, (string) $description, $subscription_helper );
			},
			10,
			2
		);

		add_filter(
			'woocommerce_credit_card_form_fields',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $default_fields, $id ) use ( $c ) {
				if ( $c->has( 'save-payment-methods.eligible' ) && $c->get( 'save-payment-methods.eligible' ) ) {
					return $default_fields;
				}

				$payment_token_repository = $c->get( 'vaulting.repository.payment-token' );
				$settings                 = $c->get( 'wcgateway.settings' );
				$subscription_helper      = $c->get( 'wc-subscriptions.helper' );

				return $this->display_saved_credit_cards( $settings, $id, $payment_token_repository, $default_fields, $subscription_helper );
			},
			20,
			2
		);

		add_filter(
			'woocommerce_available_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $methods ) use ( $c ) {
				if ( ! is_array( $methods ) ) {
					return $methods;
				}

				//phpcs:disable WordPress.Security.NonceVerification.Recommended
				if ( ! ( isset( $_GET['change_payment_method'] ) && is_wc_endpoint_url( 'order-pay' ) ) ) {
					return $methods;
				}

				$paypal_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), PayPalGateway::ID );
				if ( ! $paypal_tokens ) {
					unset( $methods[ PayPalGateway::ID ] );
				}

				if ( $c->has( 'save-payment-methods.eligible' ) && $c->get( 'save-payment-methods.eligible' ) ) {
					return $methods;
				}

				$card_tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), CreditCardGateway::ID );
				if ( ! $card_tokens ) {
					unset( $methods[ CreditCardGateway::ID ] );
				}

				return $methods;
			}
		);

		add_filter(
			'woocommerce_subscription_payment_method_to_display',
			/**
			 * Corrects the payment method name for subscriptions.
			 *
			 * @param string $payment_method_to_display The payment method string.
			 * @param \WC_Subscription $subscription The subscription instance.
			 * @param string $context The context, ex: view.
			 * @return string
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $payment_method_to_display, $subscription, $context ) {
				$payment_gateway = wc_get_payment_gateway_by_order( $subscription );

				if ( $payment_gateway instanceof \WC_Payment_Gateway && $payment_gateway->id === PayPalGateway::ID ) {
					return $subscription->get_payment_method_title( $context );
				}

				return $payment_method_to_display;
			},
			10,
			3
		);

		add_action(
			'wc_ajax_' . SubscriptionChangePaymentMethod::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'wc-subscriptions.endpoint.subscription-change-payment-method' );
				assert( $endpoint instanceof SubscriptionChangePaymentMethod );

				$endpoint->handle_request();
			}
		);

		// Remove `gateway_scheduled_payments` feature support for non PayPal Subscriptions at subscription level.
		add_filter(
			'woocommerce_subscription_payment_gateway_supports',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function( $is_supported, $feature, $subscription ) {
				if (
				$subscription->get_payment_method() === PayPalGateway::ID
				&& $feature === 'gateway_scheduled_payments'
				) {
					$subscription_connected = $subscription->get_meta( 'ppcp_subscription' ) ?? '';
					if ( ! $subscription_connected ) {
						$is_supported = false;
					}
				}

				return $is_supported;
			},
			10,
			3
		);

		return true;
	}

	/**
	 * Handles a Subscription product renewal.
	 *
	 * @param WC_Order           $order WooCommerce order.
	 * @param ContainerInterface $container The container.
	 * @return void
	 */
	protected function renew( WC_Order $order, ContainerInterface $container ) {
		$handler = $container->get( 'wc-subscriptions.renewal-handler' );
		assert( $handler instanceof RenewalHandler );

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
	): void {
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
			$tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), PayPalGateway::ID );

			$output = '<ul class="wc-saved-payment-methods">';
			foreach ( $tokens as $token ) {
				$output     .= '<li>';
					$output .= sprintf( '<input name="saved_paypal_payment" type="radio" value="%s" style="width:auto;" checked="checked">', $token->get_id() );
					$output .= sprintf( '<label for="saved_paypal_payment">%s / %s</label>', $token->get_type(), $token->get_meta( 'email' ) ?? '' );
				$output     .= '</li>';
			}
			$output .= '</ul>';

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
			$tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), CreditCardGateway::ID );
			$output = sprintf(
				'<p class="form-row form-row-wide"><label>%1$s</label><select id="saved-credit-card" name="saved_credit_card">',
				esc_html__( 'Select a saved Credit Card payment', 'woocommerce-paypal-payments' )
			);
			foreach ( $tokens as $token ) {
				if ( $token instanceof WC_Payment_Token_CC ) {
					$output .= sprintf(
						'<option value="%1$s">%2$s ...%3$s</option>',
						$token->get_id(),
						$token->get_card_type(),
						$token->get_last4()
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
	 * Groups all filters for adding WC Subscriptions gateway support.
	 *
	 * @param ContainerInterface $c The container.
	 * @return void
	 */
	private function add_gateways_support( ContainerInterface $c ): void {
		add_filter(
			'woocommerce_paypal_payments_paypal_gateway_supports',
			function ( array $supports ) use ( $c ): array {
				$subscriptions_helper = $c->get( 'wc-subscriptions.helper' );
				assert( $subscriptions_helper instanceof SubscriptionHelper );

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				$subscriptions_mode = $settings->has( 'subscriptions_mode' ) ? $settings->get( 'subscriptions_mode' ) : '';

				if ( 'disable_paypal_subscriptions' !== $subscriptions_mode && $subscriptions_helper->plugin_is_active() ) {
					$supports = array(
						'subscriptions',
						'subscription_cancellation',
						'subscription_suspension',
						'subscription_reactivation',
						'subscription_amount_changes',
						'subscription_date_changes',
						'subscription_payment_method_change',
						'subscription_payment_method_change_customer',
						'subscription_payment_method_change_admin',
						'multiple_subscriptions',
						'gateway_scheduled_payments',
					);
				}

				return $supports;
			}
		);

		add_filter(
			'woocommerce_paypal_payments_credit_card_gateway_supports',
			function ( array $supports ) use ( $c ): array {
				$subscriptions_helper = $c->get( 'wc-subscriptions.helper' );
				assert( $subscriptions_helper instanceof SubscriptionHelper );

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				$vaulting_enabled = $settings->has( 'vault_enabled_dcc' ) && $settings->get( 'vault_enabled_dcc' );

				if ( $vaulting_enabled && $subscriptions_helper->plugin_is_active() ) {
					$supports = array(
						'subscriptions',
						'subscription_cancellation',
						'subscription_suspension',
						'subscription_reactivation',
						'subscription_amount_changes',
						'subscription_date_changes',
						'subscription_payment_method_change',
						'subscription_payment_method_change_customer',
						'subscription_payment_method_change_admin',
						'multiple_subscriptions',
					);
				}

				return $supports;
			}
		);

		add_filter(
			'woocommerce_paypal_payments_card_button_gateway_supports',
			function ( array $supports ) use ( $c ): array {
				$subscriptions_helper = $c->get( 'wc-subscriptions.helper' );
				assert( $subscriptions_helper instanceof SubscriptionHelper );

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				$subscriptions_mode = $settings->has( 'subscriptions_mode' ) ? $settings->get( 'subscriptions_mode' ) : '';

				if ( 'disable_paypal_subscriptions' !== $subscriptions_mode && $subscriptions_helper->plugin_is_active() ) {
					$supports = array(
						'subscriptions',
						'subscription_cancellation',
						'subscription_suspension',
						'subscription_reactivation',
						'subscription_amount_changes',
						'subscription_date_changes',
						'subscription_payment_method_change',
						'subscription_payment_method_change_customer',
						'subscription_payment_method_change_admin',
						'multiple_subscriptions',
					);
				}

				return $supports;
			}
		);
	}
}
