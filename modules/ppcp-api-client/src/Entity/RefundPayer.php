<?php

/**
 * The refund payer object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class RefundPayer
 * The customer who sends the money.
 */
class RefundPayer
{
    /**
     * The email address.
     *
     * @var string
     */
    private $email_address;
    /**
     * The merchant id.
     *
     * @var string
     */
    private $merchant_id;
    /**
     * RefundPayer constructor.
     *
     * @param string $email_address The email.
     * @param string $merchant_id The merchant id.
     */
    public function __construct(string $email_address, string $merchant_id)
    {
        $this->email_address = $email_address;
        $this->merchant_id = $merchant_id;
    }
    /**
     * Returns the email address.
     *
     * @return string
     */
    public function email_address(): string
    {
        return $this->email_address;
    }
    /**
     * Returns the merchant id.
     *
     * @return string
     */
    public function merchant_id(): string
    {
        return $this->merchant_id;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array()
    {
        $payer = array('email_address' => $this->email_address());
        if ($this->merchant_id) {
            $payer['merchant_id'] = $this->merchant_id();
        }
        return $payer;
    }
}
