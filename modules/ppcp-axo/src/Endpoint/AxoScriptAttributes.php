<?php

namespace WooCommerce\PayPalCommerce\Axo\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

class AxoScriptAttributes implements EndpointInterface {

	const ENDPOINT = 'ppc-axo-script-attributes';

	private RequestData $request_data;
	private LoggerInterface $logger;

	public function __construct( RequestData $request_data, LoggerInterface $logger ) {
		$this->request_data = $request_data;
		$this->logger       = $logger;
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}

	public function handle_request(): bool {
		$this->request_data->read_request( $this->nonce() );

		wp_send_json_success(
			array(
				'sdk_client_token'   => 'abc123',
				'client_metadata_id' => 'xyz789',
			)
		);

		return true;
	}
}
