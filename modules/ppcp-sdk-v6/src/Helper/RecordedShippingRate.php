<?php

/**
 * The shipping rate a wallet sheet chose, held against WooCommerce's re-picking.
 *
 * `wc_get_chosen_shipping_method_for_package()` falls back to the package default
 * whenever the recomputed rate keys differ from the last calculation, which can drop
 * a rate the sheet already showed and charged for.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

class RecordedShippingRate extends \WooCommerce\PayPalCommerce\SdkV6\Helper\SessionRecord
{
    protected const SESSION_KEY = 'ppcp_wallet_chosen_rate';
    /**
     * Records the rate for the rest of this payment.
     *
     * @param string $rate_id The WC rate id, e.g. flat_rate:3.
     */
    public function set(string $rate_id): void
    {
        if ('' !== $rate_id) {
            $this->remember($rate_id);
        }
    }
    /**
     * The recorded rate, or an empty string when none applies.
     */
    public function get(): string
    {
        $rate_id = $this->remembered();
        return is_string($rate_id) ? $rate_id : '';
    }
    /**
     * Restores the recorded rate whenever WooCommerce is about to pick a default.
     *
     * A shopper picking a rate on the page still wins: that writes the session
     * directly, and no reset follows for this filter to act on.
     *
     * @param string                           $default The rate WooCommerce would choose.
     * @param array<string, \WC_Shipping_Rate> $rates   The package's rates.
     * @return string
     */
    public function filter_chosen_method($default, $rates = array()): string
    {
        $rate_id = $this->get();
        if ($rate_id && is_array($rates) && isset($rates[$rate_id])) {
            return $rate_id;
        }
        return (string) $default;
    }
}
