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
use WooCommerce\PayPalCommerce\Abilities\AbilityHandlers;
use WooCommerce\PayPalCommerce\Abilities\AbilityNames;
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
    public static function get_name(): string
    {
        return AbilityNames::GET_CONNECTION_STATUS;
    }
    public static function get_registration_args(): array
    {
        return array(
            'label' => __('Get PayPal Payments connection status', 'woocommerce-paypal-payments'),
            'description' => __('Returns the merchant PayPal connection state (connected, sandbox, merchant ID, email, seller type) for the current store. API credentials are intentionally redacted.', 'woocommerce-paypal-payments'),
            'category' => AbilityNames::CATEGORY_SLUG,
            'input_schema' => array('type' => 'object', 'default' => (object) array(), 'properties' => array(), 'additionalProperties' => \false),
            'execute_callback' => AbilityHandlers::callback(self::get_name()),
            'permission_callback' => AbilityHandlers::permission_callback(),
            // output_schema omitted — the merchant shape is defined by the
            // plugin's $merchant_info_map; the projection documents the contract.
            'meta' => array('annotations' => array('readonly' => \true, 'destructive' => \false, 'idempotent' => \true), 'show_in_rest' => \true, 'mcp' => array('public' => \true)),
        );
    }
}
