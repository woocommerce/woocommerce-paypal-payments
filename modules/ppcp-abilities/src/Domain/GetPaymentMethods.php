<?php

/**
 * Get Payment Methods ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PaymentRestEndpoint;
/**
 * Registers woocommerce-paypal-payments/get-payment-methods.
 *
 * Lists every PayPal payment gateway with enabled state, dependency edges,
 * and warnings. Backs onto PaymentRestEndpoint::get_details (Shape 2). The
 * heterogeneous output (gateway map + `__meta` + flat config flags) passes
 * through the `woocommerce_paypal_payments_payment_methods` filter.
 *
 * @internal
 */
class GetPaymentMethods extends \WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility implements AbilityDefinition
{
    private const REST_ROUTE = '/wc/v3/wc_paypal/payment';
    public static function get_name(): string
    {
        return 'woocommerce-paypal-payments/get-payment-methods';
    }
    public static function get_registration_args(): array
    {
        return array(
            'label' => __('Get PayPal Payments payment methods', 'woocommerce-paypal-payments'),
            'description' => __('Returns every PayPal payment gateway (PayPal, Pay Later, Card Fields/ACDC, Apple Pay, Google Pay, Venmo, Fastlane, APMs) with its enabled state, dependency edges, and any warning messages currently surfaced in the admin UI.', 'woocommerce-paypal-payments'),
            'category' => self::CATEGORY_SLUG,
            'input_schema' => array('type' => 'object', 'default' => (object) array(), 'properties' => array(), 'additionalProperties' => \false),
            'execute_callback' => array(self::class, 'execute'),
            'permission_callback' => array(AbilitiesRegistrar::class, 'can_manage_woocommerce'),
            // output_schema omitted — the heterogeneous shape is documented in
            // the audit doc; duplicating it would couple to the filterable output.
            'meta' => array('annotations' => array('readonly' => \true, 'destructive' => \false, 'idempotent' => \true), 'show_in_rest' => \true, 'mcp' => array('public' => \true)),
        );
    }
    /**
     * Execute callback.
     *
     * @param mixed $input Optional; ignored.
     * @return array|\WP_Error The unwrapped payment-methods payload or
     *                         WP_Error on transport / envelope failure.
     */
    public static function execute($input = null)
    {
        unset($input);
        $response = self::delegate_to_rest_controller(PaymentRestEndpoint::class, 'GET', self::REST_ROUTE);
        if (is_wp_error($response)) {
            return $response;
        }
        $unwrapped = self::unwrap_envelope($response);
        if (is_wp_error($unwrapped)) {
            return $unwrapped;
        }
        return is_array($unwrapped) ? $unwrapped : array('data' => $unwrapped);
    }
}
