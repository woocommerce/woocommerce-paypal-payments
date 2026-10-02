<?php

/**
 * The payee object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Payee
 * The entity, which receives the money.
 */
class Payee
{
    /**
     * The email address.
     *
     * @var string
     */
    private $email;
    /**
     * The merchant id.
     *
     * @var string
     */
    private $merchant_id;
    /**
     * Payee constructor.
     *
     * @param string $email The email.
     * @param string $merchant_id The merchant id.
     */
    public function __construct(string $email, string $merchant_id)
    {
        $this->email = $email;
        $this->merchant_id = $merchant_id;
    }
    /**
     * Returns the email.
     *
     * @return string
     */
    public function email(): string
    {
        return $this->email;
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
    public function to_array(): array
    {
        $data = array();
        if ($this->merchant_id) {
            $data['merchant_id'] = $this->merchant_id();
        } else {
            $data['email_address'] = $this->email();
        }
        return $data;
    }
}
