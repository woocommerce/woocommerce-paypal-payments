<?php

/**
 * The address a wallet payment's tax is calculated from.
 *
 * No sheet learns the card's billing address before the shopper authorizes, so a
 * store that taxes on billing can only quote against the destination and reconcile
 * once the real address arrives. Applied through WooCommerce's taxable-address
 * filter, so that a later recalculation prices this rather than the customer
 * record.
 *
 * Never written onto WC()->customer: wallets report a partial billing address, and
 * merging it into the one WooCommerce seeded from the shop base would tax against an
 * address that exists nowhere.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WC_Customer;
class RecordedTaxBasis extends \WooCommerce\PayPalCommerce\SdkV6\Helper\WalletPaymentRecord
{
    protected const SESSION_KEY = 'ppcp_wallet_tax_basis';
    /**
     * Records the address this payment's tax is calculated from.
     *
     * @param array<string, string> $address WC address fields.
     */
    public function set(array $address): void
    {
        if (!empty($address['country'])) {
            $this->remember($address);
        }
    }
    /**
     * The recorded address, or an empty array when none applies.
     *
     * @return array<string, string>
     */
    public function get(): array
    {
        $basis = $this->remembered();
        return is_array($basis) && !empty($basis['country']) ? $basis : array();
    }
    /**
     * Substitutes the recorded address as the one tax is calculated from.
     *
     * Stands aside for local pickup, and for anything that already replaced the
     * address before this ran: a block-cart pickup location, or a tax plugin.
     *
     * @param array<int, string> $address The country, state, postcode and city.
     * @return array<int, string>
     */
    public function filter_taxable_address($address): array
    {
        if (!is_array($address) || $this->is_local_pickup()) {
            return (array) $address;
        }
        $basis = $this->get();
        if (!$basis) {
            return $address;
        }
        $customer = WC()->customer;
        if ($customer && !$this->matches_customer($address, $customer)) {
            return $address;
        }
        return array($basis['country'] ?? '', $basis['state'] ?? '', $basis['postcode'] ?? '', $basis['city'] ?? '');
    }
    /**
     * Whether the shopper is collecting the order themselves.
     */
    private function is_local_pickup(): bool
    {
        $methods = apply_filters('woocommerce_local_pickup_methods', array('legacy_local_pickup', 'local_pickup'));
        return (bool) array_intersect(wc_get_chosen_shipping_method_ids(), (array) $methods);
    }
    /**
     * Whether the address still is the one WooCommerce itself derived.
     *
     * A difference means something else already substituted one, which this must
     * not overrule.
     *
     * @param array<int, string> $address  The filtered address.
     * @param WC_Customer        $customer The customer it came from.
     */
    private function matches_customer(array $address, WC_Customer $customer): bool
    {
        $expected = 'billing' === get_option('woocommerce_tax_based_on') ? array($customer->get_billing_country(), $customer->get_billing_state(), $customer->get_billing_postcode(), $customer->get_billing_city()) : array($customer->get_shipping_country(), $customer->get_shipping_state(), $customer->get_shipping_postcode(), $customer->get_shipping_city());
        return array_values($address) === $expected;
    }
}
