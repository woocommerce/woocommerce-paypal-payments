<?php

/**
 * The Shipping object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Shipping
 */
class Shipping
{
    /**
     * The name.
     *
     * @var string|null
     */
    private ?string $name;
    /**
     * The address.
     *
     * @var Address|null
     */
    private ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Address $address;
    /**
     * Custom contact email address, usually added via the Contact Module.
     */
    private ?string $email_address;
    /**
     * Custom contact phone number, usually added via the Contact Module.
     */
    private ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Phone $phone_number;
    /**
     * Shipping methods.
     *
     * @var ShippingOption[]
     */
    private array $options;
    /**
     * Shipping constructor.
     *
     * @param string|null      $name          The name.
     * @param Address|null     $address       The address.
     * @param string|null      $email_address Contact email.
     * @param Phone|null       $phone_number  Contact phone.
     * @param ShippingOption[] $options       Shipping methods.
     */
    public function __construct(?string $name = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Address $address = null, ?string $email_address = null, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Phone $phone_number = null, array $options = array())
    {
        $this->name = $name;
        $this->address = $address;
        $this->email_address = $email_address;
        $this->phone_number = $phone_number;
        $this->options = $options;
    }
    /**
     * Returns the name.
     *
     * @return null|string
     */
    public function name(): ?string
    {
        return $this->name;
    }
    /**
     * Returns the shipping address.
     *
     * @return null|Address
     */
    public function address(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Address
    {
        return $this->address;
    }
    /**
     * Returns the contact email address, or null.
     *
     * @return null|string
     */
    public function email_address(): ?string
    {
        return $this->email_address;
    }
    /**
     * Returns the contact phone number, or null.
     *
     * @return null|Phone
     */
    public function phone_number(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Phone
    {
        return $this->phone_number;
    }
    /**
     * Returns the shipping methods.
     *
     * @return ShippingOption[]
     */
    public function options(): array
    {
        return $this->options;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        $result = array();
        $name = $this->name();
        if ($name) {
            $result['name'] = array('full_name' => $name);
        }
        $address = $this->address();
        if ($address) {
            $result['address'] = $address->to_array();
        }
        $contact_email = $this->email_address();
        if ($contact_email) {
            $result['email_address'] = $contact_email;
        }
        $contact_phone = $this->phone_number();
        if ($contact_phone) {
            $result['phone_number'] = $contact_phone->to_array();
        }
        if ($this->options) {
            $result['options'] = array_map(function (\WooCommerce\PayPalCommerce\ApiClient\Entity\ShippingOption $opt): array {
                return $opt->to_array();
            }, $this->options);
        }
        return $result;
    }
}
