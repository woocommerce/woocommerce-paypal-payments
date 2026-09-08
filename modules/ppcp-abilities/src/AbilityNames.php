<?php

/**
 * The registered ability slugs and the category they register under.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities;

/**
 * The four ability slugs and the shared category slug, as plain constants.
 *
 * This class deliberately implements and extends nothing and names no
 * Domain class: the boot path (AbilitiesModule::run()) needs the slugs to key
 * the handler map, and it must be able to reach them on WooCommerce < 10.9,
 * where the Domain shells cannot be autoloaded at all — each one is declared
 * `implements AbilityDefinition`, and PHP resolves that interface while
 * linking the class, fataling with "Interface not found" when WC 10.9 is
 * absent. Domain\Get*::get_name() returns the constant from here, so the
 * registered slug and the handler-map key cannot drift, and
 * get_registration_args() reads CATEGORY_SLUG from here for the same reason.
 *
 * @internal
 */
final class AbilityNames
{
    /**
     * Ability category slug shared by every ability this module registers.
     * `woocommerce` is owned/registered by Woo Core 10.9+; plugin ownership
     * lives in the ability namespace, not here.
     */
    public const CATEGORY_SLUG = 'woocommerce';
    /**
     * Merchant connection state.
     */
    public const GET_CONNECTION_STATUS = 'woocommerce-paypal-payments/get-connection-status';
    /**
     * PayPal payment gateway inventory.
     */
    public const GET_PAYMENT_METHODS = 'woocommerce-paypal-payments/get-payment-methods';
    /**
     * Shipment tracking registered with PayPal for a WooCommerce order.
     */
    public const GET_ORDER_TRACKING = 'woocommerce-paypal-payments/get-order-tracking';
    /**
     * A single PayPal order.
     */
    public const GET_PAYPAL_ORDER = 'woocommerce-paypal-payments/get-paypal-order';
    /**
     * Every slug this module registers.
     *
     * @var array<int, string>
     */
    public const ALL = array(self::GET_CONNECTION_STATUS, self::GET_PAYMENT_METHODS, self::GET_ORDER_TRACKING, self::GET_PAYPAL_ORDER);
}
