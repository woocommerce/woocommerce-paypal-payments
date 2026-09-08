<?php

/**
 * Handler for the get-connection-status ability.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\Abilities\Helper\EnvelopeParser;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;
/**
 * Returns the merchant PayPal connection state, with the API credentials
 * stripped. Calls the injected CommonRestEndpoint directly.
 *
 * @internal
 */
class GetConnectionStatusHandler
{
    /**
     * Fields dropped before returning to the agent. clientId/clientSecret are
     * the OAuth API credentials (admin-only); an agent could log them verbatim.
     * The merchant `id`/email stay — agents need them to reason about the account.
     *
     * @var array<int, string>
     */
    private const REDACTED_FIELDS = array('clientId', 'clientSecret');
    /**
     * @var CommonRestEndpoint
     */
    private $endpoint;
    /**
     * @var EnvelopeParser
     */
    private $envelope;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @param CommonRestEndpoint $endpoint The backing merchant-details endpoint.
     * @param EnvelopeParser     $envelope The shared envelope parser.
     * @param LoggerInterface    $logger   The plugin's PSR-3 logger.
     */
    public function __construct(CommonRestEndpoint $endpoint, EnvelopeParser $envelope, LoggerInterface $logger)
    {
        $this->endpoint = $endpoint;
        $this->envelope = $envelope;
        $this->logger = $logger;
    }
    /**
     * Execute callback.
     *
     * @param mixed $input Optional; ignored.
     * @return array|\WP_Error
     */
    public function execute($input = null)
    {
        unset($input);
        try {
            $response = $this->endpoint->get_merchant_details();
        } catch (Throwable $e) {
            // Don't forward $e->getMessage() — PayPalApiException leaks
            // information_link URLs into LLM context. Log full detail server-side.
            $this->logger->error('[ppcp-abilities] get-connection-status lookup threw ' . get_class($e) . ': ' . $e->getMessage());
            return new \WP_Error('woocommerce_paypal_payments_endpoint_error', __('PayPal Payments endpoint returned an error; see server log for details.', 'woocommerce-paypal-payments'));
        }
        $payload = $response instanceof \WP_REST_Response ? $response->get_data() : $response;
        if (is_wp_error($payload)) {
            return $payload;
        }
        if (!is_array($payload)) {
            return new \WP_Error('woocommerce_paypal_payments_unexpected_response', __('Unexpected response shape from the merchant endpoint.', 'woocommerce-paypal-payments'));
        }
        // CommonRestEndpoint puts merchant/features at the envelope top level
        // alongside `data`, so unwrap() (which extracts `data`) would drop
        // them — use the failure-branch handler only.
        $envelope_error = $this->envelope->error_or_null($payload);
        if ($envelope_error instanceof \WP_Error) {
            return $envelope_error;
        }
        return $this->project_merchant_payload($payload);
    }
    /**
     * Project the success response to the agent payload: the merchant
     * subobject (API credentials stripped) plus optional features.
     *
     * @param array $payload Decoded REST response array (success branch).
     * @return array Agent-facing payload.
     */
    private function project_merchant_payload(array $payload): array
    {
        $merchant = isset($payload['merchant']) && is_array($payload['merchant']) ? $payload['merchant'] : array();
        foreach (self::REDACTED_FIELDS as $field) {
            unset($merchant[$field]);
        }
        $result = array('merchant' => $merchant);
        if (isset($payload['features'])) {
            $result['features'] = $payload['features'];
        }
        return $result;
    }
}
