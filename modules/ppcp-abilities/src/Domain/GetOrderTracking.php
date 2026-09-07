<?php

/**
 * Get Order Tracking ability definition.
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
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;
/**
 * Registers woocommerce-paypal-payments/get-order-tracking.
 *
 * Lists shipment tracking entries for a WooCommerce order. Backed by
 * OrderTrackingEndpoint::list_tracking_information() (Shape 3), which issues
 * two synchronous PayPal API calls per invocation.
 *
 * @internal
 */
class GetOrderTracking extends \WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility implements AbilityDefinition
{
    /**
     * Container service id; see modules/ppcp-order-tracking/services.php.
     *
     * @var string
     */
    private const SERVICE_ID = 'order-tracking.endpoint.controller';
    public static function get_name(): string
    {
        return 'woocommerce-paypal-payments/get-order-tracking';
    }
    public static function get_registration_args(): array
    {
        return array('label' => __('Get PayPal order tracking', 'woocommerce-paypal-payments'), 'description' => __('Returns the shipment tracking entries (carrier, tracking number, status) registered with PayPal for a WooCommerce order. Issues two synchronous PayPal API calls.', 'woocommerce-paypal-payments'), 'category' => self::CATEGORY_SLUG, 'input_schema' => array('type' => 'object', 'default' => (object) array(), 'properties' => array('wc_order_id' => array('type' => 'integer', 'minimum' => 1, 'description' => __('The WooCommerce order ID whose tracking entries should be returned.', 'woocommerce-paypal-payments'))), 'required' => array('wc_order_id'), 'additionalProperties' => \false), 'execute_callback' => array(self::class, 'execute'), 'permission_callback' => array(AbilitiesRegistrar::class, 'can_manage_woocommerce'), 'meta' => array('annotations' => array('readonly' => \true, 'destructive' => \false, 'idempotent' => \true), 'show_in_rest' => \true, 'mcp' => array('public' => \true)));
    }
    /**
     * Execute callback.
     *
     * @param mixed $input Expected shape: { wc_order_id: int }.
     * @return array|\WP_Error
     */
    public static function execute($input = null)
    {
        $input = is_array($input) ? $input : array();
        if (!isset($input['wc_order_id'])) {
            return new \WP_Error('woocommerce_paypal_payments_missing_wc_order_id', __('wc_order_id is required.', 'woocommerce-paypal-payments'));
        }
        $wc_order_id = (int) $input['wc_order_id'];
        if ($wc_order_id < 1) {
            return new \WP_Error('woocommerce_paypal_payments_invalid_input', __('wc_order_id must be a positive integer.', 'woocommerce-paypal-payments'));
        }
        // Pre-validate the order: the backing endpoint returns [] for an unknown
        // order, indistinguishable from "exists but untracked". Surface a
        // not_found instead, mirroring GetPaypalOrder::extract_identifier().
        if (!wc_get_order($wc_order_id) instanceof WC_Order) {
            return new \WP_Error('woocommerce_paypal_payments_not_found', __('WooCommerce order not found.', 'woocommerce-paypal-payments'), array('wc_order_id' => $wc_order_id));
        }
        $controller = self::resolve_service(self::SERVICE_ID, OrderTrackingEndpoint::class);
        if ($controller instanceof \WP_Error) {
            return $controller;
        }
        try {
            $shipments = $controller->list_tracking_information($wc_order_id);
        } catch (Throwable $e) {
            // Don't forward $e->getMessage() — PayPalApiException leaks
            // information_link URLs into LLM context. Log full detail server-side.
            self::logger()->error('[ppcp-abilities] get-order-tracking lookup threw ' . get_class($e) . ' for wc_order_id=' . $wc_order_id . ': ' . $e->getMessage());
            return new \WP_Error('woocommerce_paypal_payments_tracking_lookup_failed', __('PayPal tracking lookup failed; see server log for details.', 'woocommerce-paypal-payments'), array('wc_order_id' => $wc_order_id));
        }
        // list_tracking_information() returns null on any non-200 (commonly the
        // 404 "no trackers yet"). Treat as empty, matching MetaBoxRenderer's
        // `?? array()`. Genuine transport failures throw above.
        $shipments = $shipments ?? array();
        return array('wc_order_id' => $wc_order_id, 'shipments' => array_map(array(self::class, 'serialize_shipment'), $shipments));
    }
    /**
     * Serialize a ShipmentInterface for the agent payload via its own
     * to_array(). Public so tests can assert the shape without the container.
     *
     * @param ShipmentInterface $shipment The shipment entity.
     * @return array<string, mixed>
     */
    public static function serialize_shipment(ShipmentInterface $shipment): array
    {
        return $shipment->to_array();
    }
}
