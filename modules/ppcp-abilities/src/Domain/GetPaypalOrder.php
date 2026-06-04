<?php

/**
 * Get PayPal Order ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpointCached;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
/**
 * Registers woocommerce-paypal-payments/get-paypal-order.
 *
 * Looks up a PayPal order (id, intent, status, purchase units with embedded
 * captures/authorizations/refunds, timestamps, links) by PayPal order ID OR
 * WooCommerce order ID. Backed by OrderEndpointCached::order() (Shape 3);
 * cached to amortize repeat lookups in a session.
 *
 * Security: payer PII (top-level `payer`) and per-purchase-unit `shipping`
 * are STRIPPED unless include_payer_pii is true. `payment_source` is stripped
 * defensively — Order::to_array() does not serialize it today, but a future
 * change that did would leak through the denylist gap (pinned by
 * test_project_order_does_not_leak_synthetic_payment_source).
 *
 * @internal
 */
class GetPaypalOrder extends \WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility implements AbilityDefinition
{
    /**
     * Container service id; see modules/ppcp-api-client/services.php.
     *
     * @var string
     */
    private const SERVICE_ID = 'api.endpoint.order.cached';
    /**
     * Valid PayPal v2 order ID format. Constraining the input blocks
     * path-traversal payloads (e.g. "ID/../refunds") from reaching
     * OrderEndpoint::order(), which concatenates the id without rawurlencode().
     *
     * @var string
     */
    private const PAYPAL_ORDER_ID_PATTERN = '/^[A-Z0-9]{1,64}$/';
    public static function get_name(): string
    {
        return 'woocommerce-paypal-payments/get-paypal-order';
    }
    public static function get_registration_args(): array
    {
        return array('label' => __('Get PayPal order', 'woocommerce-paypal-payments'), 'description' => __('Returns the PayPal order (id, intent, status, purchase units with embedded captures / authorizations / refunds, create/update timestamps, links) for a given PayPal order ID or WooCommerce order ID. Payer PII (email, name, address, phone) and per-purchase-unit shipping addresses are stripped by default; pass include_payer_pii: true to opt in when the calling context legitimately needs them.', 'woocommerce-paypal-payments'), 'category' => self::CATEGORY_SLUG, 'input_schema' => array('type' => 'object', 'default' => (object) array(), 'properties' => array('paypal_order_id' => array('type' => 'string', 'description' => __('PayPal v2 order ID (alphanumeric uppercase, up to 64 chars). Either this or wc_order_id is required.', 'woocommerce-paypal-payments')), 'wc_order_id' => array('type' => 'integer', 'minimum' => 1, 'description' => __('WooCommerce order ID; the PayPal order ID is resolved from order meta. Either this or paypal_order_id is required.', 'woocommerce-paypal-payments')), 'include_payer_pii' => array('type' => 'boolean', 'default' => \false, 'description' => __('When true, returns the payer block (email, name, address, phone) and per-purchase-unit shipping addresses. Defaults to false; only opt in when the calling context legitimately needs payer identity.', 'woocommerce-paypal-payments'))), 'additionalProperties' => \false), 'execute_callback' => array(self::class, 'execute'), 'permission_callback' => array(AbilitiesRegistrar::class, 'can_manage_woocommerce'), 'meta' => array('annotations' => array('readonly' => \true, 'destructive' => \false, 'idempotent' => \true), 'show_in_rest' => \true, 'mcp' => array('public' => \true)));
    }
    /**
     * Execute callback. Accepts EITHER paypal_order_id OR wc_order_id (exactly
     * one); with wc_order_id the endpoint resolves the PayPal id from order meta.
     *
     * @param mixed $input Expected shape: { paypal_order_id?: string, wc_order_id?: int, include_payer_pii?: bool }.
     * @return array|\WP_Error
     */
    public static function execute($input = null)
    {
        $input = is_array($input) ? $input : array();
        $identifier = self::extract_identifier($input);
        if ($identifier instanceof \WP_Error) {
            return $identifier;
        }
        $endpoint = self::resolve_service(self::SERVICE_ID, OrderEndpointCached::class);
        if ($endpoint instanceof \WP_Error) {
            return $endpoint;
        }
        try {
            $order = $endpoint->order($identifier);
        } catch (Throwable $e) {
            // Don't forward $e->getMessage() — PayPalApiException leaks
            // information_link URLs into LLM context. Log full detail server-side.
            self::logger()->error('[ppcp-abilities] get-paypal-order lookup threw ' . get_class($e) . ': ' . $e->getMessage());
            return new \WP_Error('woocommerce_paypal_payments_order_lookup_failed', __('PayPal order lookup failed; see server log for details.', 'woocommerce-paypal-payments'), array('identifier' => is_object($identifier) ? get_class($identifier) : $identifier));
        }
        if (!$order instanceof Order) {
            return new \WP_Error('woocommerce_paypal_payments_unexpected_response', __('PayPal order lookup returned an unexpected response shape.', 'woocommerce-paypal-payments'));
        }
        return self::project_order($order->to_array(), (bool) ($input['include_payer_pii'] ?? \false));
    }
    /**
     * Top-level keys stripped unless include_payer_pii. `payer` is the only one
     * Order::to_array() emits today; `payment_source` is defensive against a
     * future serialization change (pinned by the synthetic-payment-source test).
     *
     * @var array<int, string>
     */
    private const REDACTED_TOP_LEVEL_KEYS = array('payer', 'payment_source');
    /**
     * Project the PayPal Order payload to the agent shape, stripping the payer
     * block, payment_source, and per-purchase-unit shipping unless
     * $include_payer_pii. Public so tests can assert redaction without the container.
     *
     * @param array $payload           Decoded PayPal Order payload.
     * @param bool  $include_payer_pii Pass payer + shipping through when true.
     * @return array
     */
    public static function project_order(array $payload, bool $include_payer_pii): array
    {
        if ($include_payer_pii) {
            return $payload;
        }
        foreach (self::REDACTED_TOP_LEVEL_KEYS as $key) {
            unset($payload[$key]);
        }
        if (isset($payload['purchase_units']) && is_array($payload['purchase_units'])) {
            foreach ($payload['purchase_units'] as $i => $unit) {
                if (is_array($unit) && isset($unit['shipping'])) {
                    unset($payload['purchase_units'][$i]['shipping']);
                }
            }
        }
        return $payload;
    }
    /**
     * Extract the identifier OrderEndpoint expects: a string PayPal order id,
     * a WC_Order, or WP_Error when neither resolves.
     *
     * @param array<string, mixed> $input
     * @return string|WC_Order|\WP_Error
     */
    private static function extract_identifier(array $input)
    {
        $paypal_order_id = isset($input['paypal_order_id']) ? (string) $input['paypal_order_id'] : '';
        $wc_order_id = isset($input['wc_order_id']) ? (int) $input['wc_order_id'] : 0;
        if ('' !== $paypal_order_id) {
            if (!preg_match(self::PAYPAL_ORDER_ID_PATTERN, $paypal_order_id)) {
                return new \WP_Error('woocommerce_paypal_payments_invalid_input', __('paypal_order_id must be an alphanumeric uppercase PayPal order ID (1-64 chars).', 'woocommerce-paypal-payments'));
            }
            return $paypal_order_id;
        }
        if ($wc_order_id < 1) {
            return new \WP_Error('woocommerce_paypal_payments_missing_identifier', __('Either paypal_order_id or wc_order_id is required.', 'woocommerce-paypal-payments'));
        }
        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order instanceof WC_Order) {
            return new \WP_Error('woocommerce_paypal_payments_not_found', __('WooCommerce order not found.', 'woocommerce-paypal-payments'), array('wc_order_id' => $wc_order_id));
        }
        return $wc_order;
    }
}
