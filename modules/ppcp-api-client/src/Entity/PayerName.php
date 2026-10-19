<?php

/**
 * The PayerName object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PayerName
 */
class PayerName
{
    /**
     * The given name.
     *
     * @var string
     */
    private $given_name;
    /**
     * The surname.
     *
     * @var string
     */
    private $surname;
    /**
     * PayerName constructor.
     *
     * @param string $given_name The given name.
     * @param string $surname The surname.
     */
    public function __construct(string $given_name, string $surname)
    {
        $this->given_name = $given_name;
        $this->surname = $surname;
    }
    /**
     * Returns the given name.
     *
     * @return string
     */
    public function given_name(): string
    {
        return $this->given_name;
    }
    /**
     * Returns the surname.
     *
     * @return string
     */
    public function surname(): string
    {
        return $this->surname;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('given_name' => $this->given_name(), 'surname' => $this->surname());
    }
}
