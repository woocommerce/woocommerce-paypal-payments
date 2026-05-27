<?php

/**
 * Handles the request for the SDK v6 browser-safe client token.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RateLimiter;
/**
 * Class ClientTokenEndpoint
 */
class ClientTokenEndpoint implements EndpointInterface
{
    const ENDPOINT = 'ppc-sdk-v6-client-token';
    /**
     * The request data helper.
     *
     * @var RequestData
     */
    private RequestData $request_data;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * The SDK client token generator.
     *
     * @var SdkClientToken
     */
    private SdkClientToken $sdk_client_token;
    /**
     * The rate limiter.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;
    /**
     * ClientTokenEndpoint constructor.
     *
     * @param RequestData     $request_data The request data helper.
     * @param LoggerInterface $logger The logger.
     * @param SdkClientToken  $sdk_client_token The SDK client token generator.
     * @param RateLimiter     $rate_limiter The rate limiter.
     */
    public function __construct(RequestData $request_data, LoggerInterface $logger, SdkClientToken $sdk_client_token, RateLimiter $rate_limiter)
    {
        $this->request_data = $request_data;
        $this->logger = $logger;
        $this->sdk_client_token = $sdk_client_token;
        $this->rate_limiter = $rate_limiter;
    }
    /**
     * {@inheritDoc}
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * {@inheritDoc}
     */
    public function handle_request(): void
    {
        try {
            $this->request_data->read_request($this->nonce());
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        }
        if ($this->rate_limiter->is_limited()) {
            $this->logger->warning('SDK v6 client token rate limit exceeded.');
            wp_send_json_error(array('message' => 'Rate limit exceeded. Please try again later.'), 429);
        }
        $this->rate_limiter->hit();
        try {
            $token = $this->sdk_client_token->sdk_client_token();
            wp_send_json_success(array('client_token' => $token));
        } catch (PayPalApiException $exception) {
            $this->logger->error('SDK v6 client token PayPal API error: ' . $exception->getMessage());
            wp_send_json_error(array('message' => 'Failed to generate client token.'), 500);
        } catch (RuntimeException $exception) {
            $this->logger->error('SDK v6 client token runtime error: ' . $exception->getMessage());
            wp_send_json_error(array('message' => 'Failed to generate client token.'), 500);
        }
    }
}
