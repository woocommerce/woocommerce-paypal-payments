<?php
/**
 * The save payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\UserIdToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal;
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
					$id_token                                      = $api->id_token();
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
			function( $data ) {
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
				$payment_vault_attributes = $order->payment_source()->properties()->attributes->vault ?? null;
				if ( $payment_vault_attributes ) {
					update_user_meta( $wc_order->get_customer_id(), '_ppcp_target_customer_id', $payment_vault_attributes->customer->id );

					$payment_token_helper = $c->get( 'vaulting.payment-token-helper' );
					assert( $payment_token_helper instanceof PaymentTokenHelper );

					$wc_tokens = WC_Payment_Tokens::get_customer_tokens( $wc_order->get_customer_id(), PayPalGateway::ID );
					if ( $payment_token_helper->token_exist( $wc_tokens, $payment_vault_attributes->id ) ) {
						return;
					}

					$payment_token_factory = $c->get( 'vaulting.payment-token-factory' );
					assert( $payment_token_factory instanceof PaymentTokenFactory );

					$payment_token_paypal = $payment_token_factory->create( 'paypal' );
					assert( $payment_token_paypal instanceof PaymentTokenPayPal );

					$payment_token_paypal->set_token( $payment_vault_attributes->id );
					$payment_token_paypal->set_user_id( $wc_order->get_customer_id() );
					$payment_token_paypal->set_gateway_id( PayPalGateway::ID );

					$email = $order->payment_source()->properties()->email_address ?? '';
					if ( $email && is_email( $email ) ) {
						$payment_token_paypal->set_email( $email );
					}

					try {
						$payment_token_paypal->save();
					} catch ( Exception $exception ) {
						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						assert( $logger instanceof LoggerInterface );

						$logger->error(
							"Could not save WC payment token PayPal for order #{$wc_order->get_id()}. " . $exception->getMessage()
						);
					}
				}
			},
			10,
			2
		);
	}
}
