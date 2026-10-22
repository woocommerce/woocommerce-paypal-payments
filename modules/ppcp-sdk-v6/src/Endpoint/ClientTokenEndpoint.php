<?php
/**
 * Handles the request for the SDK v6 browser-safe client token.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;

class ClientTokenEndpoint implements EndpointInterface {

	public const ENDPOINT = 'ppc-sdk-v6-client-token';

	private const REFRESH_BEFORE_EXPIRY = 60;

	/**
	 * Minimum seconds until the next browser refresh, also after a failed one.
	 */
	private const MIN_REFRESH_DELAY = 10;

	private RequestData $request_data;
	private LoggerInterface $logger;
	private SdkClientToken $sdk_client_token;

	public function __construct(
		RequestData $request_data,
		LoggerInterface $logger,
		SdkClientToken $sdk_client_token
	) {
		$this->request_data     = $request_data;
		$this->logger           = $logger;
		$this->sdk_client_token = $sdk_client_token;
	}

	public function handle_request(): void {
		try {
			$this->request_data->read_request( self::nonce() );
		} catch ( NonceValidationException $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 400 );
		}

		try {
			$token = $this->sdk_client_token->sdk_client_token_data();

			$refresh_in = max( self::MIN_REFRESH_DELAY, $token['expires_in'] - self::REFRESH_BEFORE_EXPIRY );

			/**
			 * Filters the seconds until the browser gets a new client token.
			 *
			 * Only a shorter value takes effect, down to a fixed minimum.
			 *
			 * @param int $refresh_in Seconds until the refresh.
			 */
			$filtered_refresh_in = apply_filters(
				'woocommerce_paypal_payments_sdk_v6_client_token_refresh_in',
				$refresh_in
			);
			if ( is_numeric( $filtered_refresh_in ) ) {
				$refresh_in = min( $refresh_in, max( self::MIN_REFRESH_DELAY, (int) $filtered_refresh_in ) );
			}

			wp_send_json_success(
				array(
					'client_token' => $token['token'],
					'refresh_in'   => $refresh_in,
					'retry_in'     => self::MIN_REFRESH_DELAY,
				)
			);
		} catch ( PayPalApiException $exception ) {
			$this->logger->error( 'SDK v6 client token PayPal API error: ' . $exception->getMessage() );
			wp_send_json_error( array( 'message' => 'Failed to generate client token.' ), 500 );
		} catch ( RuntimeException $exception ) {
			$this->logger->error( 'SDK v6 client token runtime error: ' . $exception->getMessage() );
			wp_send_json_error( array( 'message' => 'Failed to generate client token.' ), 500 );
		}
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}
}
