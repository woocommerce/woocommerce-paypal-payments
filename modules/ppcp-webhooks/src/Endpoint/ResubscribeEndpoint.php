<?php
/**
 * The endpoint for resubscribing webhooks.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;

/**
 * Class ResubscribeEndpoint
 */
class ResubscribeEndpoint {

	const ENDPOINT = 'ppc-webhooks-resubscribe';

	/**
	 * The webhooks registrar.
	 *
	 * @var WebhookRegistrar
	 */
	private $registrar;

	/**
	 * The Request Data helper object.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * ResubscribeEndpoint constructor.
	 *
	 * @param WebhookRegistrar $registrar The webhooks registrar.
	 * @param RequestData      $request_data The Request Data helper object.
	 */
	public function __construct( WebhookRegistrar $registrar, RequestData $request_data ) {
		$this->registrar    = $registrar;
		$this->request_data = $request_data;
	}

	/**
	 * Returns the nonce for the endpoint.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the incoming request.
	 */
	public function handle_request() {
		try {
			// Validate nonce.
			$this->request_data->read_request( $this->nonce() );

			if ( ! $this->registrar->register() ) {
				wp_send_json_error( 'Webhook subscription failed.', 500 );
				return false;
			}

			wp_send_json_success();
			return true;
		} catch ( Exception $error ) {
			wp_send_json_error( $error->getMessage(), 403 );
			return false;
		}
	}
}
