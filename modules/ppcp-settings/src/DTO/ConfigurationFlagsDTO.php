<?php

/**
 * Data transfer object. Stores flags that are relevant for the default plugin
 * configuration at the end of the onboarding process.
 *
 * @package WooCommerce\PayPalCommerce\Settings\DTO;
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\DTO;

/**
 * DTO that represents a plugin configuration.
 *
 * Intentionally has no internal logic, sanitation or validation.
 */
class ConfigurationFlagsDTO
{
    /**
     * The merchant's country, which indicates availability of certain features.
     *
     * @var string
     */
    public string $country_code = '';
    /**
     * Whether we configure a business account.
     * If false, a personal (casual seller) account is set up.
     *
     * @var bool
     */
    public bool $is_business_seller = \false;
    /**
     * Whether credit card payments should be handled by our plugin.
     *
     * @var bool
     */
    public bool $use_card_payments = \false;
    /**
     * If the shop needs to process subscription payments.
     *
     * @var bool
     */
    public bool $use_subscriptions = \false;
}
