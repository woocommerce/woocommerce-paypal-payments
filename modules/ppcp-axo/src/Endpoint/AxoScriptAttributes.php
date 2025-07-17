<?php

namespace WooCommerce\PayPalCommerce\Axo\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

class AxoScriptAttributes implements EndpointInterface {

	const ENDPOINT = 'ppc-axo-script-attributes';

	private RequestData $request_data;
	private LoggerInterface $logger;
	private SdkClientToken $sdk_client_token;

	public function __construct(
		RequestData $request_data,
		LoggerInterface $logger,
		SdkClientToken $sdk_client_token
	) {
		$this->request_data = $request_data;
		$this->logger       = $logger;
		$this->sdk_client_token = $sdk_client_token;
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}

	public function handle_request(): bool {
		$this->request_data->read_request( $this->nonce() );

		try {
			$token = $this->sdk_client_token->sdk_client_token();
		} catch (PayPalApiException $exception) {
			$this->logger->error($exception->getMessage());
			wp_send_json_error($exception->getMessage());
			return false;
		}

		wp_send_json_success(
			array(
				'sdk_client_token'   => $token,
				'client_metadata_id' => str_replace('-', '', wp_generate_uuid4()),
			)
		);

		return true;
	}
}
