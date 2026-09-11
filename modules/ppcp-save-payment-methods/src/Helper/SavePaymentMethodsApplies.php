<?php

/**
 * Properties of the Save Payment Methods module.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Helper;

/**
 * Class SavePaymentMethodsApplies
 */
class SavePaymentMethodsApplies
{
    /**
     * The countries can be used for Save Payment Methods.
     *
     * @var array
     */
    private array $allowed_countries;
    /**
     * 2-letter country code of the shop.
     *
     * @var string
     */
    private string $country;
    /**
     * SavePaymentMethodsApplies constructor.
     *
     * @param array  $allowed_countries The matrix which countries and currency combinations can be used for Save Payment Methods.
     * @param string $country 2-letter country code of the shop.
     */
    public function __construct(array $allowed_countries, string $country)
    {
        $this->allowed_countries = $allowed_countries;
        $this->country = $country;
    }
    /**
     * Returns whether Save Payment Methods can be used in the current country and the current currency used.
     *
     * @return bool
     */
    public function for_country(): bool
    {
        return in_array($this->country, $this->allowed_countries, \true);
    }
    /**
     * Indicates, whether the current merchant is eligible for Save Payment Methods. Always true,
     * but the filter allows other modules to disable Save Payment Methods site-wide.
     *
     * @return bool
     */
    public function for_merchant(): bool
    {
        return apply_filters('woocommerce_paypal_payments_is_eligible_for_save_payment_methods', \true);
    }
}
