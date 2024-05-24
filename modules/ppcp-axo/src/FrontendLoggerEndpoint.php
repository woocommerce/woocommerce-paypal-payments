<?php
/**
 * The endpoint to log entries from frontend.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

class FrontendLoggerEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-frontend-logger';

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

	public function __construct(RequestData $request_data, LoggerInterface $logger){
		$this->request_data = $request_data;
		$this->logger = $logger;
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

		$this->logger->info("[AXO] " . $data['log']['message']);

		wp_send_json_success();
		return true;
	}
}
