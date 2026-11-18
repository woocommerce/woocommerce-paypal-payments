<?php

/**
 * Provides a central information source for details about the current PayPal merchant.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

/**
 * Main information source about merchant details.
 */
class MerchantDetails
{
    /**
     * The merchant's country according to PayPal, which might be different from
     * the WooCommerce country.
     *
     * #legacy-ui uses the Woo store country for this.
     *
     * @var string
     */
    private string $merchant_country;
    /**
     * The WooCommerce store country.
     *
     * @var string
     */
    private string $store_country;
    /**
     * A collection of feature eligibility checks. The value can be either a
     * boolean (static eligibility) or a callback that returns a boolean (lazy check).
     *
     * @var array
     */
    private array $eligibility_checks;
    /**
     * Constructor.
     *
     * @param string $merchant_country   Merchant country provided by PayPal's API. Not editable.
     * @param string $store_country      WooCommerce store country, can be changed by the site
     *                                   admin via the WooCommerce settings.
     * @param array  $eligibility_checks Array of eligibility checks. Default service:
     *                                   'wcgateway.feature-eligibility.list'.
     */
    public function __construct(string $merchant_country, string $store_country, array $eligibility_checks)
    {
        $this->merchant_country = $merchant_country;
        $this->store_country = $store_country;
        $this->eligibility_checks = $eligibility_checks;
    }
    /**
     * Returns the merchant's country. This country is used by PayPal to decide
     * which features the merchant can access.
     *
     * This country is provided by PayPal and defines the operating country of
     * the merchant.
     *
     * @return string
     */
    public function get_merchant_country(): string
    {
        return $this->merchant_country;
    }
    /**
     * The WooCommerce store's country, which could be different from the
     * merchant's country in some cases. This country is used by WooCommerce.
     *
     * @return string
     */
    public function get_shop_country(): string
    {
        return $this->store_country;
    }
    /**
     * Tests, if the merchant is eligible to use a certain feature.
     * Feature checks are reliable _after_ the "plugins_loaded" action finished.
     *
     * Note:
     * To register features for detection by this method, the features must be
     * present in the service `wcgateway.contact-module.eligibility.check`, and
     * also define a public FEATURE_* const in the class header.
     * Adding all features is an ongoing task.
     *
     * @param string $feature One of the public self::FEATURE_* values.
     * @return bool Whether the merchant can use the relevant feature.
     */
    public function is_eligible_for(string $feature): bool
    {
        if (!array_key_exists($feature, $this->eligibility_checks)) {
            return \false;
        }
        $check = $this->eligibility_checks[$feature];
        if (is_bool($check)) {
            return $check;
        }
        if (is_callable($check)) {
            return (bool) $check();
        }
        return \false;
    }
}
