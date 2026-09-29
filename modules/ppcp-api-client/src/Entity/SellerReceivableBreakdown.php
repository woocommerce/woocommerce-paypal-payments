<?php

/**
 * The info about fees and amount that will be received by the seller.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class SellerReceivableBreakdown
 */
class SellerReceivableBreakdown
{
    /**
     * The amount for this captured payment in the currency of the transaction.
     *
     * @var Money
     */
    private $gross_amount;
    /**
     * The applicable fee for this captured payment in the currency of the transaction.
     *
     * @var Money|null
     */
    private $paypal_fee;
    /**
     * The applicable fee for this captured payment in the receivable currency.
     *
     * Present only in cases the fee is charged in the receivable currency.
     *
     * @var Money|null
     */
    private $paypal_fee_in_receivable_currency;
    /**
     * The net amount that the payee receives for this captured payment in their PayPal account.
     *
     * Computed as gross_amount minus the paypal_fee minus the platform_fees.
     *
     * @var Money|null
     */
    private $net_amount;
    /**
     * The net amount that is credited to the payee's PayPal account.
     *
     * Present only when the currency of the captured payment is different from the currency
     * of the PayPal account where the payee wants to credit the funds. Computed as net_amount times exchange_rate.
     *
     * @var Money|null
     */
    private $receivable_amount;
    /**
     * The exchange rate that determines the amount that is credited to the payee's PayPal account.
     *
     * Present when the currency of the captured payment is different from the currency of the PayPal account where the payee wants to credit the funds.
     *
     * @var ExchangeRate|null
     */
    private $exchange_rate;
    /**
     * An array of platform or partner fees, commissions, or brokerage fees that associated with the captured payment.
     *
     * @var PlatformFee[]
     */
    private $platform_fees;
    /**
     * SellerReceivableBreakdown constructor.
     *
     * @param Money             $gross_amount The amount for this captured payment in the currency of the transaction.
     * @param Money|null        $paypal_fee The applicable fee for this captured payment in the currency of the transaction.
     * @param Money|null        $paypal_fee_in_receivable_currency The applicable fee for this captured payment in the receivable currency.
     * @param Money|null        $net_amount The net amount that the payee receives for this captured payment in their PayPal account.
     * @param Money|null        $receivable_amount The net amount that is credited to the payee's PayPal account.
     * @param ExchangeRate|null $exchange_rate The exchange rate that determines the amount that is credited to the payee's PayPal account.
     * @param PlatformFee[]     $platform_fees An array of platform or partner fees, commissions, or brokerage fees that associated with the captured payment.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $gross_amount, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $paypal_fee, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $paypal_fee_in_receivable_currency, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $net_amount, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money $receivable_amount, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\ExchangeRate $exchange_rate, array $platform_fees)
    {
        $this->gross_amount = $gross_amount;
        $this->paypal_fee = $paypal_fee;
        $this->paypal_fee_in_receivable_currency = $paypal_fee_in_receivable_currency;
        $this->net_amount = $net_amount;
        $this->receivable_amount = $receivable_amount;
        $this->exchange_rate = $exchange_rate;
        $this->platform_fees = $platform_fees;
    }
    /**
     * The amount for this captured payment in the currency of the transaction.
     *
     * @return Money
     */
    public function gross_amount(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money
    {
        return $this->gross_amount;
    }
    /**
     * The applicable fee for this captured payment in the currency of the transaction.
     *
     * @return Money|null
     */
    public function paypal_fee(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money
    {
        return $this->paypal_fee;
    }
    /**
     * The applicable fee for this captured payment in the receivable currency.
     *
     * Present only in cases the fee is charged in the receivable currency.
     *
     * @return Money|null
     */
    public function paypal_fee_in_receivable_currency(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money
    {
        return $this->paypal_fee_in_receivable_currency;
    }
    /**
     * The net amount that the payee receives for this captured payment in their PayPal account.
     *
     * Computed as gross_amount minus the paypal_fee minus the platform_fees.
     *
     * @return Money|null
     */
    public function net_amount(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money
    {
        return $this->net_amount;
    }
    /**
     * The net amount that is credited to the payee's PayPal account.
     *
     * Present only when the currency of the captured payment is different from the currency
     * of the PayPal account where the payee wants to credit the funds. Computed as net_amount times exchange_rate.
     *
     * @return Money|null
     */
    public function receivable_amount(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Money
    {
        return $this->receivable_amount;
    }
    /**
     * The exchange rate that determines the amount that is credited to the payee's PayPal account.
     *
     * Present when the currency of the captured payment is different from the currency of the PayPal account where the payee wants to credit the funds.
     *
     * @return ExchangeRate|null
     */
    public function exchange_rate(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\ExchangeRate
    {
        return $this->exchange_rate;
    }
    /**
     * An array of platform or partner fees, commissions, or brokerage fees that associated with the captured payment.
     *
     * @return PlatformFee[]
     */
    public function platform_fees(): array
    {
        return $this->platform_fees;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        $data = array('gross_amount' => $this->gross_amount->to_array());
        if ($this->paypal_fee) {
            $data['paypal_fee'] = $this->paypal_fee->to_array();
        }
        if ($this->paypal_fee_in_receivable_currency) {
            $data['paypal_fee_in_receivable_currency'] = $this->paypal_fee_in_receivable_currency->to_array();
        }
        if ($this->net_amount) {
            $data['net_amount'] = $this->net_amount->to_array();
        }
        if ($this->receivable_amount) {
            $data['receivable_amount'] = $this->receivable_amount->to_array();
        }
        if ($this->exchange_rate) {
            $data['exchange_rate'] = $this->exchange_rate->to_array();
        }
        if ($this->platform_fees) {
            $data['platform_fees'] = array_map(function (\WooCommerce\PayPalCommerce\ApiClient\Entity\PlatformFee $fee) {
                return $fee->to_array();
            }, $this->platform_fees);
        }
        return $data;
    }
}
