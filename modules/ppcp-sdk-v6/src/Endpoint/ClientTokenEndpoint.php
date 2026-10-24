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
use WooCommerce\PayPalCommerce\ApiClient\Exception\TokenUnavailableException;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;

/**
 * Answers POST requests without verifying a nonce.
 *
 * The token is browser-safe, bound to the shop's domain and the same for every
 * visitor, so a caller gets nothing a visitor's browser does not already get.
 * An unauthenticated caller can at most trigger the existing once-per-60-seconds
 * rotation per domain in SdkClientToken, plus a short burst while its cache is
 * cold. A nonce adds no protection on top: for logged-out visitors it is created
 * for user 0 (see RequestData::nonce_fix()), so every visitor holds the same
 * usable value (as modules/ppcp-sdk-v6/docs/wallets.md notes for the frontend
 * log), and CartScriptParamsEndpoint is a read-only endpoint without one too.
 * What it does do is expire in pages served from a full-page cache, which then
 * cannot load the SDK.
 */
class ClientTokenEndpoint implements EndpointInterface {

	public const ENDPOINT = 'ppc-sdk-v6-client-token';

	private const REFRESH_BEFORE_EXPIRY = 60;

	/**
	 * Minimum seconds until the next browser refresh, also after a failed one.
	 */
	private const MIN_REFRESH_DELAY = 10;

	private LoggerInterface $logger;
	private SdkClientToken $sdk_client_token;

	public function __construct(
		LoggerInterface $logger,
		SdkClientToken $sdk_client_token
	) {
		$this->logger           = $logger;
		$this->sdk_client_token = $sdk_client_token;
	}

	public function handle_request(): void {
		// A GET would let crawlers, prefetchers and image tags start token requests.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->send_token();
			return;
		}

		wp_send_json_error( array( 'message' => 'Method not allowed.' ), 405 );
	}

	private function send_token(): void {
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
		} catch ( TokenUnavailableException $exception ) {
			// An expected state that anonymous requests reach in volume, so no error log per request.
			$this->logger->debug( 'SDK v6 client token unavailable: ' . $exception->getMessage() );
			wp_send_json_error(
				array(
					'message'  => 'Client token temporarily unavailable.',
					'retry_in' => max( self::MIN_REFRESH_DELAY, $exception->retry_after() ),
				),
				503
			);
		} catch ( PayPalApiException $exception ) {
			$this->logger->error( 'SDK v6 client token PayPal API error: ' . $exception->getMessage() );
			wp_send_json_error( array( 'message' => 'Failed to generate client token.' ), 500 );
		} catch ( RuntimeException $exception ) {
			$this->logger->error( 'SDK v6 client token runtime error: ' . $exception->getMessage() );
			wp_send_json_error( array( 'message' => 'Failed to generate client token.' ), 500 );
		}
	}

	/**
	 * Required by EndpointInterface; this endpoint verifies no nonce.
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}
}
