<?php

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

class CreatePaymentToken implements EndpointInterface {

	const ENDPOINT = 'ppc-create-payment-token';

	private RequestData $request_data;
	private PaymentMethodTokensEndpoint $payment_method_tokens_endpoint;

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
		try {
			$data = $this->request_data->read_request( $this->nonce() );

			$payment_source = new PaymentSource(
				'token',
				(object) array(
					'id'   => $data['vault_setup_token'],
					'type' => 'SETUP-TOKEN',
				)
			);

			$result = $this->payment_method_tokens_endpoint->payment_tokens( $payment_source );
			wp_send_json_success( $result );
			return true;
		} catch ( Exception $exception ) {
			wp_send_json_error();
			return false;
		}
	}
}
