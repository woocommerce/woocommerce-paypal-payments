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
use WooCommerce\PayPalCommerce\Abilities\AbilityHandlers;
use WooCommerce\PayPalCommerce\Abilities\AbilityNames;
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
    public static function get_name(): string
    {
        return AbilityNames::GET_PAYMENT_METHODS;
    }
    public static function get_registration_args(): array
    {
        return array(
            'label' => __('Get PayPal Payments payment methods', 'woocommerce-paypal-payments'),
            'description' => __('Returns every PayPal payment gateway (PayPal, Pay Later, Card Fields/ACDC, Apple Pay, Google Pay, Venmo, Fastlane, APMs) with its enabled state, dependency edges, and any warning messages currently surfaced in the admin UI.', 'woocommerce-paypal-payments'),
            'category' => AbilityNames::CATEGORY_SLUG,
            'input_schema' => array('type' => 'object', 'default' => (object) array(), 'properties' => array(), 'additionalProperties' => \false),
            'execute_callback' => AbilityHandlers::callback(self::get_name()),
            'permission_callback' => AbilityHandlers::permission_callback(),
            // output_schema omitted — the heterogeneous shape is documented in
            // the audit doc; duplicating it would couple to the filterable output.
            'meta' => array('annotations' => array('readonly' => \true, 'destructive' => \false, 'idempotent' => \true), 'show_in_rest' => \true, 'mcp' => array('public' => \true)),
        );
    }
}
