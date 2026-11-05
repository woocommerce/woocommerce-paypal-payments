<?php

/**
 * The PlatformFee Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PlatformFee;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class PayeeFactory
 */
class PlatformFeeFactory
{
    /**
     * The Money factory.
     *
     * @var MoneyFactory
     */
    private $money_factory;
    /**
     * The Payee factory.
     *
     * @var PayeeFactory
     */
    private $payee_factory;
    /**
     * PlatformFeeFactory constructor.
     *
     * @param MoneyFactory $money_factory The Money factory.
     * @param PayeeFactory $payee_factory The Payee factory.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Factory\MoneyFactory $money_factory, \WooCommerce\PayPalCommerce\ApiClient\Factory\PayeeFactory $payee_factory)
    {
        $this->money_factory = $money_factory;
        $this->payee_factory = $payee_factory;
    }
    /**
     * Returns a Payee object based off a PayPal Response.
     *
     * @param stdClass $data The JSON object.
     *
     * @return PlatformFee
     * @throws RuntimeException When JSON object is malformed.
     */
    public function from_paypal_response(stdClass $data): PlatformFee
    {
        if (!isset($data->amount)) {
            throw new RuntimeException('Platform fee amount not found');
        }
        $amount = $this->money_factory->from_paypal_response($data->amount);
        $payee = isset($data->payee) ? $this->payee_factory->from_paypal_response($data->payee) : null;
        return new PlatformFee($amount, $payee);
    }
}
