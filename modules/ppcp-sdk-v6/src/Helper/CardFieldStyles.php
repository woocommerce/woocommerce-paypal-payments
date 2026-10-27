<?php

/**
 * Merchant style overrides for the hosted card fields.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

class CardFieldStyles
{
    /**
     * The styles a merchant wants applied to the text inside the hosted fields.
     *
     * A card field's own box sits on the merchant's page and is stylable with
     * plain CSS through the .ppcp-sdk-v6-card-field class. Its text is not: that
     * lives in a PayPal-hosted iframe CSS cannot reach across, so the only way
     * in is the SDK's style.input object, which has to travel as script data.
     *
     * Values are camelCase CSS properties (fontSize, not font-size) and are
     * merged over the styles read from the input being replaced, so an override
     * wins. The SDK rejects vendor-prefixed properties and logs a DevError per
     * unsupported one.
     *
     * Read per call rather than once per request, so a filter registered after
     * this service was resolved still applies.
     *
     * @return array<string, string>
     */
    public function overrides(): array
    {
        /**
         * Filters the text styles applied inside the hosted card fields.
         *
         * @param array<string, string> $styles camelCase CSS property map.
         */
        $styles = apply_filters('woocommerce_paypal_payments_card_fields_styles', array());
        if (!is_array($styles)) {
            return array();
        }
        $sanitized = array();
        foreach ($styles as $property => $value) {
            if (is_string($property) && is_string($value)) {
                $sanitized[$property] = $value;
            }
        }
        return $sanitized;
    }
}
