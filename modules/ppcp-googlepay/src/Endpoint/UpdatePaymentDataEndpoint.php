<?php
/**
 * Endpoint to update payment data like shipping method and address.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Endpoint;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;

/**
 * Class UpdatePaymentDataEndpoint
 */
class UpdatePaymentDataEndpoint {

	const ENDPOINT = 'ppc-googlepay-update-payment-data';

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * UpdatePaymentDataEndpoint constructor.
	 *
	 * @param RequestData     $request_data The request data helper.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		LoggerInterface $logger
	) {
		$this->request_data = $request_data;
		$this->logger       = $logger;
	}

	/**
	 * Returns the nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 * @throws RuntimeException
	 */
	public function handle_request(): bool {
		try {
			$data = $this->request_data->read_request( $this->nonce() );

			// Validate nonce.
			if (
				! isset( $data['nonce'] )
				|| ! wp_verify_nonce( $data['nonce'], self::nonce() )
			) {
				throw new RuntimeException(
					__( 'Could not validate nonce.', 'woocommerce-paypal-payments' )
				);
			}

			// Validate payment data.
			if ( ! isset( $data['paymentData'] ) ) {
				throw new RuntimeException(
					__( 'No paymentData provided.', 'woocommerce-paypal-payments' )
				);
			}

			$payment_data = $data['paymentData'];

			// Set context as cart.
			if ( is_callable( 'wc_maybe_define_constant' ) ) {
				wc_maybe_define_constant( 'WOOCOMMERCE_CART', true );
			}

			// Update shipping address.
			if ( $payment_data['callbackTrigger'] === 'SHIPPING_ADDRESS' ) {
				$customer = WC()->customer;

				if ( $customer ) {
					$customer->set_billing_postcode( $payment_data['shippingAddress']['postalCode'] ?? '' );
					$customer->set_billing_country( $payment_data['shippingAddress']['countryCode'] ?? '' );
					$customer->set_billing_state( $payment_data['shippingAddress']['locality'] ?? '' );

					$customer->set_shipping_postcode( $payment_data['shippingAddress']['postalCode'] ?? '' );
					$customer->set_shipping_country( $payment_data['shippingAddress']['countryCode'] ?? '' );
					$customer->set_shipping_state( $payment_data['shippingAddress']['locality'] ?? '' );

					// Save the data.
					$customer->save();

					WC()->session->set( 'customer', WC()->customer->get_data() );
				}
			}

			// Set shipping method.
			WC()->shipping->calculate_shipping( WC()->cart->get_shipping_packages() );

			$chosen_shipping_methods    = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping_methods[0] = $payment_data['shippingOptionData']['id'];
			WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

			WC()->cart->calculate_shipping();
			WC()->cart->calculate_fees();
			WC()->cart->calculate_totals();

			$total = (float) WC()->cart->get_total( 'numeric' );

			// Shop settings.
			$base_location     = wc_get_base_location();
			$shop_country_code = $base_location['country'];
			$currency_code     = get_woocommerce_currency();

			wp_send_json_success(
				array(
					'total'            => $total,
					'total_str'        => ( new Money( $total, $currency_code ) )->value_str(),
					'currency_code'    => $currency_code,
					'country_code'     => $shop_country_code,
					'shipping_options' => $this->get_shipping_options(),
				)
			);

			return true;
		} catch ( Throwable $error ) {
			$this->logger->error( "UpdatePaymentDataEndpoint execution failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}" );

			wp_send_json_error();
			return false;
		}
	}

	/**
	 * Returns the array of available shipping methods.
	 *
	 * @return array
	 */
	public function get_shipping_options(): array {
		$shipping_methods = array();

		$packages = WC()->cart->get_shipping_packages();
		$zone     = \WC_Shipping_Zones::get_zone_matching_package( $packages[0] );

		/** @var \WC_Shipping_Method[] $methods The shipping methods. */
		$methods = $zone->get_shipping_methods( true );

		foreach ( $methods as $method ) {
			if ( ! $method->is_available( $packages[0] ) ) {
				continue;
			}

			$shipping_methods[] = array(
				'id'          => $method->get_rate_id(),
				'label'       => $method->get_title(),
				'description' => '',
			);
		}

		if ( ! isset( $shipping_methods[0] ) ) {
			return array();
		}

		return array(
			'defaultSelectedOptionId' => $shipping_methods[0]['id'],
			'shippingOptions'         => $shipping_methods,
		);
	}

}
