<?php
/**
 * Create payment token for guest user.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

/**
 * Class UpdateCustomerId
 */
class CreatePaymentTokenForGuest implements EndpointInterface {

	const ENDPOINT = 'ppc-update-customer-id';

	/**
	 * The request data.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The payment method tokens endpoint.
	 *
	 * @var PaymentMethodTokensEndpoint
	 */
	private $payment_method_tokens_endpoint;

	/**
	 * CreatePaymentToken constructor.
	 *
	 * @param RequestData                 $request_data The request data.
	 * @param PaymentMethodTokensEndpoint $payment_method_tokens_endpoint The payment method tokens endpoint.
	 */
	public function __construct(
		RequestData $request_data,
		PaymentMethodTokensEndpoint $payment_method_tokens_endpoint
	) {
		$this->request_data                   = $request_data;
		$this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
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
	 * @throws Exception On Error.
	 */
	public function handle_request(): bool {
		$data = $this->request_data->read_request( $this->nonce() );

		/**
		 * Suppress ArgumentTypeCoercion
		 *
		 * @psalm-suppress ArgumentTypeCoercion
		 */
		$payment_source = new PaymentSource(
			'token',
			(object) array(
				'id'   => $data['vault_setup_token'],
				'type' => 'SETUP_TOKEN',
			)
		);

		$result = $this->payment_method_tokens_endpoint->create_payment_token( $payment_source );
		WC()->session->set( 'ppcp_guest_payment_for_free_trial', $result );

		wp_send_json_success();
		return true;
	}
}
