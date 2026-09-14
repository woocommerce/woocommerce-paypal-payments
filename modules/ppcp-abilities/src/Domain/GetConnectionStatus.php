<?php

/**
 * Get Connection Status ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;
/**
 * Registers woocommerce-paypal-payments/get-connection-status.
 *
 * Reference ability: zero-arg, read-only. Backs onto
 * CommonRestEndpoint::get_merchant_details and STRIPS the API credentials
 * (clientId, clientSecret) before returning.
 *
 * @internal Only loaded on WC 10.9+; the registrar short-circuits before
 *           referencing this class (and the AbilityDefinition FQN) on older WC.
 */
class GetConnectionStatus extends \WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility implements AbilityDefinition
{
    /**
     * Backing REST route for the merchant payload.
     *
     * @var string
     */
    private const REST_ROUTE = '/wc/v3/wc_paypal/common/merchant';
    /**
     * Fields dropped before returning to the agent. clientId/clientSecret are
     * the OAuth API credentials (admin-only); an agent could log them verbatim.
     * The merchant `id`/email stay — agents need them to reason about the account.
     *
     * @var array<int, string>
     */
    private const REDACTED_FIELDS = array('clientId', 'clientSecret');
    public static function get_name(): string
    {
        return 'woocommerce-paypal-payments/get-connection-status';
    }
    public static function get_registration_args(): array
    {
        return array(
            'label' => __('Get PayPal Payments connection status', 'woocommerce-paypal-payments'),
            'description' => __('Returns the merchant PayPal connection state (connected, sandbox, merchant ID, email, seller type) for the current store. API credentials are intentionally redacted.', 'woocommerce-paypal-payments'),
            'category' => self::CATEGORY_SLUG,
            'input_schema' => array('type' => 'object', 'default' => (object) array(), 'properties' => array(), 'additionalProperties' => \false),
            'execute_callback' => array(self::class, 'execute'),
            'permission_callback' => array(AbilitiesRegistrar::class, 'can_manage_woocommerce'),
            // output_schema omitted — the merchant shape is defined by the
            // plugin's $merchant_info_map; the projection documents the contract.
            'meta' => array('annotations' => array('readonly' => \true, 'destructive' => \false, 'idempotent' => \true), 'show_in_rest' => \true, 'mcp' => array('public' => \true)),
        );
    }
    /**
     * Delegate to CommonRestEndpoint::get_merchant_details (Shape 2) and
     * project to the agent-facing shape via {@see self::project_merchant_payload()}.
     *
     * @param mixed $input Unused; kept for the execute_callback signature.
     * @return array|\WP_Error
     */
    public static function execute($input = null)
    {
        unset($input);
        $response = self::delegate_to_rest_controller(CommonRestEndpoint::class, 'GET', self::REST_ROUTE);
        if (is_wp_error($response)) {
            return $response;
        }
        if (!is_array($response)) {
            return new \WP_Error('woocommerce_paypal_payments_unexpected_response', __('Unexpected response shape from the merchant endpoint.', 'woocommerce-paypal-payments'));
        }
        // CommonRestEndpoint puts merchant/features at the envelope top level
        // alongside `data`, so unwrap_envelope() (which extracts `data`) would
        // drop them — use the failure-branch handler only.
        $envelope_error = self::envelope_error_or_null($response);
        if ($envelope_error instanceof \WP_Error) {
            return $envelope_error;
        }
        return self::project_merchant_payload($response);
    }
    /**
     * Project the CommonRestEndpoint success response to the agent payload:
     * the merchant subobject (API credentials stripped) plus optional features.
     * Public so tests can assert redaction without a REST server.
     *
     * @param array $payload Decoded REST response array (success branch).
     * @return array Agent-facing payload.
     */
    public static function project_merchant_payload(array $payload): array
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
