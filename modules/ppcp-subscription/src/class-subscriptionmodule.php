<?php
/**
 * The subscription module.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Subscription\Repository\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class SubscriptionModule
 */
class SubscriptionModule implements ModuleInterface {

	/**
	 * Setup the module.
	 *
	 * @return ServiceProviderInterface
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * Runs the module.
	 *
	 * @param ContainerInterface|null $container The container.
	 */
	public function run( ContainerInterface $container = null ) {
		add_action(
			'woocommerce_scheduled_subscription_payment_' . PayPalGateway::ID,
			function ( $amount, $order ) use ( $container ) {
				$this->renew( $order, $container );
			},
			10,
			2
		);

		add_action(
			'woocommerce_scheduled_subscription_payment_' . CreditCardGateway::ID,
			function ( $amount, $order ) use ( $container ) {
				$this->renew( $order, $container );
			},
			10,
			2
		);

		add_action(
			'woocommerce_subscription_payment_complete',
			function ( $subscription ) use ( $container ) {
				$payment_token_repository = $container->get( 'subscription.repository.payment-token' );
				$logger                   = $container->get( 'woocommerce.logger.woocommerce' );

				$this->add_payment_token_id( $subscription, $payment_token_repository, $logger );
			}
		);

		add_filter(
			'woocommerce_gateway_description',
			function ( $description, $id ) use ( $container ) {
				$payment_token_repository = $container->get( 'subscription.repository.payment-token' );
				$settings                 = $container->get( 'wcgateway.settings' );
				$subscription_helper      = $container->get( 'subscription.helper' );

				return $this->display_saved_paypal_payments( $settings, $id, $payment_token_repository, $description, $subscription_helper );
			},
			10,
			2
		);

		add_filter(
			'woocommerce_credit_card_form_fields',
			function ( $default_fields, $id ) use ( $container ) {
				$payment_token_repository = $container->get( 'subscription.repository.payment-token' );
				$settings                 = $container->get( 'wcgateway.settings' );
				$subscription_helper      = $container->get( 'subscription.helper' );

				return $this->display_saved_credit_cards( $settings, $id, $payment_token_repository, $default_fields, $subscription_helper );
			},
			20,
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
		if ( ! is_a( $order, \WC_Order::class ) ) {
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
				$subscription_id = $subscription->get_id();
				$latest_token_id = end( $tokens )->id() ? end( $tokens )->id() : '';
				update_post_meta( $subscription_id, 'payment_token_id', $latest_token_id, true );
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

		if ( $settings->has( 'vault_enabled' )
			&& $settings->get( 'vault_enabled' )
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
