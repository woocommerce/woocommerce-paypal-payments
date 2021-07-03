<?php
/**
 * The Data Client ID endpoint.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class DataClientIdEndpoint
 */
class DataClientIdEndpoint implements EndpointInterface {


	const ENDPOINT = 'ppc-data-client-id';

	/**
	 * The Request Data Helper.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The Identity Token.
	 *
	 * @var IdentityToken
	 */
	private $identity_token;

	/**
	 * DataClientIdEndpoint constructor.
	 *
	 * @param RequestData   $request_data The Request Data Helper.
	 * @param IdentityToken $identity_token The Identity Token.
	 */
	public function __construct(
		RequestData $request_data,
		IdentityToken $identity_token
	) {

		$this->request_data   = $request_data;
		$this->identity_token = $identity_token;
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
	 */
	public function handle_request(): bool {
		try {
			$this->request_data->read_request( $this->nonce() );
			$user_id = get_current_user_id();
			$token   = $this->identity_token->generate_for_customer( $user_id );
			wp_send_json(
				array(
					'token'      => $token->token(),
					'expiration' => $token->expiration_timestamp(),
					'user'       => $user_id,
				)
			);
			return true;
		} catch ( RuntimeException $error ) {
			wp_send_json_error(
				array(
					'name'    => is_a( $error, PayPalApiException::class ) ? $error->name() : '',
					'message' => $error->getMessage(),
					'code'    => $error->getCode(),
					'details' => is_a( $error, PayPalApiException::class ) ? $error->details() : array(),
				)
			);
			return false;
		}
	}
}
