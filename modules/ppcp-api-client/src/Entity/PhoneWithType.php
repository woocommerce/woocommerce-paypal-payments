<?php

/**
 * The PhoneWithType object
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PhoneWithType
 */
class PhoneWithType
{
    const VALID_TYPES = array('FAX', 'HOME', 'MOBILE', 'OTHER', 'PAGER');
    /**
     * The type.
     *
     * @var string
     */
    private $type;
    /**
     * The phone.
     *
     * @var Phone
     */
    private $phone;
    /**
     * PhoneWithType constructor.
     *
     * @param string $type The type.
     * @param Phone  $phone The phone.
     */
    public function __construct(string $type, \WooCommerce\PayPalCommerce\ApiClient\Entity\Phone $phone)
    {
        $this->type = in_array($type, self::VALID_TYPES, \true) ? $type : 'OTHER';
        $this->phone = $phone;
    }
    /**
     * Returns the type.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }
    /**
     * Returns the phone.
     *
     * @return Phone
     */
    public function phone(): \WooCommerce\PayPalCommerce\ApiClient\Entity\Phone
    {
        return $this->phone;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('phone_type' => $this->type(), 'phone_number' => $this->phone()->to_array());
    }
}
