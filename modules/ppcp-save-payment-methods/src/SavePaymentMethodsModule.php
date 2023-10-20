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
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

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
			function( WC_Order $wc_order, Order $order ) {
				// vault payment here ...
			},
			10,
			2
		);
	}
}
