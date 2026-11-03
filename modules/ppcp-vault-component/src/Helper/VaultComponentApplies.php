<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
class VaultComponentApplies
{
    private string $country;
    private ReferenceTransactionStatus $reference_transaction_status;
    public function __construct(string $country, ReferenceTransactionStatus $reference_transaction_status)
    {
        $this->country = $country;
        $this->reference_transaction_status = $reference_transaction_status;
    }
    public function for_country(): bool
    {
        return in_array($this->country, $this->supported_countries(), \true);
    }
    /**
     * The countries where the vault component is supported.
     *
     * @return string[]
     */
    private function supported_countries(): array
    {
        return apply_filters('woocommerce_paypal_payments_vault_component_supported_countries', array('US'));
    }
    /**
     * Checks PAYPAL_WALLET_VAULTING_ADVANCED capability.
     */
    public function for_merchant(): bool
    {
        return $this->reference_transaction_status->reference_transaction_enabled();
    }
}
