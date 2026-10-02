<?php

/**
 * The RefundPayerFactory factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PayerName;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PayerTaxInfo;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Phone;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PhoneWithType;
use WooCommerce\PayPalCommerce\ApiClient\Entity\RefundPayer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class RefundPayerFactory
 */
class RefundPayerFactory
{
    /**
     * Returns a Refund Payer object based off a PayPal Response.
     *
     * @param \stdClass $data The JSON object.
     *
     * @return RefundPayer
     */
    public function from_paypal_response(\stdClass $data): RefundPayer
    {
        return new RefundPayer(isset($data->email_address) ? $data->email_address : '', isset($data->merchant_id) ? $data->merchant_id : '');
    }
}
