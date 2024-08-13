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
	 * @throws RuntimeException When a validation fails.
	 */
	public function handle_request(): bool {
		try {
			$data = $this->request_data->read_request( $this->nonce() );

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

			$this->update_addresses( $payment_data );
			$this->update_shipping_method( $payment_data );

			WC()->cart->calculate_shipping();
			WC()->cart->calculate_fees();
			WC()->cart->calculate_totals();

			$total        = (float) WC()->cart->get_total( 'numeric' );
			$shipping_fee = (float) WC()->cart->get_shipping_total();

			// Shop settings.
			$base_location     = wc_get_base_location();
			$shop_country_code = $base_location['country'];
			$currency_code     = get_woocommerce_currency();

			wp_send_json_success(
				array(
					'total'            => $total,
					'shipping_fee'     => $shipping_fee,
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
		$shipping_options = array();

		$calculated_packages = WC()->shipping->calculate_shipping(
			WC()->cart->get_shipping_packages()
		);

		if ( ! isset( $calculated_packages[0] ) && ! isset( $calculated_packages[0]['rates'] ) ) {
			return array();
		}

		foreach ( $calculated_packages[0]['rates'] as $rate ) {
			/**
			 * The shipping rate.
			 *
			 * @var \WC_Shipping_Rate $rate
			 */
			$shipping_options[] = array(
				'id'          => $rate->get_id(),
				'label'       => $rate->get_label(),
				'description' => html_entity_decode(
					wp_strip_all_tags(
						wc_price( (float) $rate->get_cost(), array( 'currency' => get_woocommerce_currency() ) )
					)
				),
				'cost'        => $rate->get_cost(),
			);
		}

		if ( ! isset( $shipping_options[0] ) ) {
			return array();
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		return array(
			'defaultSelectedOptionId' => ( $chosen_shipping_methods[0] ?? null ) ? $chosen_shipping_methods[0] : $shipping_options[0]['id'],
			'shippingOptions'         => $shipping_options,
		);
	}

	/**
	 * Update addresses.
	 *
	 * @param array $payment_data The payment data.
	 * @return void
	 */
	private function update_addresses( array $payment_data ): void {
		if ( ! in_array( $payment_data['callbackTrigger'] ?? '', array( 'SHIPPING_ADDRESS', 'INITIALIZE' ), true ) ) {
			return;
		}

		/**
		 * The shipping methods.
		 *
		 * @var \WC_Customer|null $customer
		 */
		$customer = WC()->customer;

		if ( ! $customer ) {
			return;
		}

		$customer->set_billing_postcode( $payment_data['shippingAddress']['postalCode'] ?? '' );
		$customer->set_billing_country( $payment_data['shippingAddress']['countryCode'] ?? '' );
		$customer->set_billing_state( '' );
		$customer->set_billing_city( $payment_data['shippingAddress']['locality'] ?? '' );

		$customer->set_shipping_postcode( $payment_data['shippingAddress']['postalCode'] ?? '' );
		$customer->set_shipping_country( $payment_data['shippingAddress']['countryCode'] ?? '' );
		$customer->set_shipping_state( '' );
		$customer->set_shipping_city( $payment_data['shippingAddress']['locality'] ?? '' );

		// Save the data.
		$customer->save();

		WC()->session->set( 'customer', WC()->customer->get_data() );
	}

	/**
	 * Update shipping method.
	 *
	 * @param array $payment_data The payment data.
	 * @return void
	 */
	private function update_shipping_method( array $payment_data ): void {
		$rate_id             = $payment_data['shippingOptionData']['id'];
		$calculated_packages = WC()->shipping->calculate_shipping(
			WC()->cart->get_shipping_packages()
		);

		if ( $rate_id && isset( $calculated_packages[0]['rates'][ $rate_id ] ) ) {
			WC()->session->set( 'chosen_shipping_methods', array( $rate_id ) );
		}
	}

}
