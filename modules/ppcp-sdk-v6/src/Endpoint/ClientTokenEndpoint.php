<?php
/**
 * Handles the request for the SDK v6 browser-safe client token.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RateLimiter;

/**
 * Class ClientTokenEndpoint
 */
class ClientTokenEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-sdk-v6-client-token';

	/**
	 * The request data helper.
	 *
	 * @var RequestData
	 */
	private RequestData $request_data; // @phpstan-ignore property.onlyWritten

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger; // @phpstan-ignore property.onlyWritten

	/**
	 * The SDK client token generator.
	 *
	 * @var SdkClientToken
	 */
	private SdkClientToken $sdk_client_token; // @phpstan-ignore property.onlyWritten

	/**
	 * The rate limiter.
	 *
	 * @var RateLimiter
	 */
	private RateLimiter $rate_limiter; // @phpstan-ignore property.onlyWritten

	/**
	 * ClientTokenEndpoint constructor.
	 *
	 * @param RequestData     $request_data The request data helper.
	 * @param LoggerInterface $logger The logger.
	 * @param SdkClientToken  $sdk_client_token The SDK client token generator.
	 * @param RateLimiter     $rate_limiter The rate limiter.
	 */
	public function __construct(
		RequestData $request_data,
		LoggerInterface $logger,
		SdkClientToken $sdk_client_token,
		RateLimiter $rate_limiter
	) {
		$this->request_data     = $request_data;
		$this->logger           = $logger;
		$this->sdk_client_token = $sdk_client_token;
		$this->rate_limiter     = $rate_limiter;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle_request(): void {
		wp_send_json_error( 'Not implemented.', 501 );
	}
}
