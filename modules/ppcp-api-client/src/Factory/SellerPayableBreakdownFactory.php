<?php

/**
 * The SellerPayableBreakdownFactory Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PlatformFee;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerPayableBreakdown;
/**
 * Class SellerPayableBreakdownFactory
 */
class SellerPayableBreakdownFactory
{
    /**
     * The Money factory.
     *
     * @var MoneyFactory
     */
    private $money_factory;
    /**
     * The PlatformFee factory.
     *
     * @var PlatformFeeFactory
     */
    private $platform_fee_factory;
    /**
     * SellerPayableBreakdownFactory constructor.
     *
     * @param MoneyFactory       $money_factory The Money factory.
     * @param PlatformFeeFactory $platform_fee_factory The PlatformFee factory.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Factory\MoneyFactory $money_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\PlatformFeeFactory $platform_fee_factory)
    {
        $this->money_factory = $money_factory;
        $this->platform_fee_factory = $platform_fee_factory;
    }
    /**
     * Returns a SellerPayableBreakdownFactory object based off a PayPal Response.
     *
     * @param stdClass $data The JSON object.
     *
     * @return SellerPayableBreakdown
     */
    public function from_paypal_response(stdClass $data): SellerPayableBreakdown
    {
        $gross_amount = isset($data->gross_amount) ? $this->money_factory->from_paypal_response($data->gross_amount) : null;
        $paypal_fee = isset($data->paypal_fee) ? $this->money_factory->from_paypal_response($data->paypal_fee) : null;
        $paypal_fee_in_receivable_currency = isset($data->paypal_fee_in_receivable_currency) ? $this->money_factory->from_paypal_response($data->paypal_fee_in_receivable_currency) : null;
        $net_amount = isset($data->net_amount) ? $this->money_factory->from_paypal_response($data->net_amount) : null;
        $net_amount_in_receivable_currency = isset($data->net_amount_in_receivable_currency) ? $this->money_factory->from_paypal_response($data->net_amount_in_receivable_currency) : null;
        $total_refunded_amount = isset($data->total_refunded_amount) ? $this->money_factory->from_paypal_response($data->total_refunded_amount) : null;
        $platform_fees = isset($data->platform_fees) ? array_map(function (stdClass $fee_data): PlatformFee {
            return $this->platform_fee_factory->from_paypal_response($fee_data);
        }, $data->platform_fees) : array();
        return new SellerPayableBreakdown($gross_amount, $paypal_fee, $paypal_fee_in_receivable_currency, $net_amount, $net_amount_in_receivable_currency, $total_refunded_amount, $platform_fees);
    }
}
