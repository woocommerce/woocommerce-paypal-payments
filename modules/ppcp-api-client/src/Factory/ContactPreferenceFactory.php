<?php

/**
 * Returns contact_preference for the given state.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\WcGateway\Helper\MerchantDetails;
/**
 * Class ContactPreferenceFactory
 */
class ContactPreferenceFactory
{
    /**
     * Whether the contact module toggle is enabled in the plugin settings.
     * Allows eligible merchants to opt out of the feature.
     */
    private bool $is_contact_module_active;
    /**
     * Used to determine if a merchant is eligible to use the contact preference.
     */
    private MerchantDetails $merchant_details;
    /**
     * Constructor.
     *
     * @param bool            $is_contact_module_active Whether custom contact details are enabled
     *                                                  in the plugin settings.
     * @param MerchantDetails $merchant_details         Service 'settings.merchant-details'.
     */
    public function __construct(bool $is_contact_module_active, MerchantDetails $merchant_details)
    {
        $this->is_contact_module_active = $is_contact_module_active;
        $this->merchant_details = $merchant_details;
    }
    /**
     * Returns contact_preference for the given state.
     *
     * @param string $payment_source_key Name of the payment_source.
     * @return string|null
     */
    public function from_state(string $payment_source_key): ?string
    {
        $payment_sources_with_contact = array('paypal', 'venmo');
        /**
         * In case the payment-source does not support the contact-info preference
         * we return null to remove the property from the context.
         */
        if (!in_array($payment_source_key, $payment_sources_with_contact, \true)) {
            return null;
        }
        if (!$this->is_contact_module_active) {
            return ExperienceContext::CONTACT_PREFERENCE_NO_CONTACT_INFO;
        }
        if (!$this->merchant_details->is_eligible_for(FeaturesDefinition::FEATURE_CONTACT_MODULE)) {
            return ExperienceContext::CONTACT_PREFERENCE_NO_CONTACT_INFO;
        }
        return ExperienceContext::CONTACT_PREFERENCE_UPDATE_CONTACT_INFO;
    }
}
