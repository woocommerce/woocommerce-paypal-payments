<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;


use Inpsyde\PayPalCommerce\ApiClient\Entity\Shipping;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class ShippingFactory
{

    private $addressFactory;
    public function __construct(AddressFactory $addressFactory)
    {
        $this->addressFactory = $addressFactory;
    }

    public function fromPayPalResponse(\stdClass $data) : Shipping {

        if (! isset($data->name->full_name)) {
            throw new RuntimeException(
                __("No name was given for shipping.", "woocommerce-paypal-commerce-gateway")
            );
        }
        if (! isset($data->address)) {
            throw new RuntimeException(
                __("No address was given for shipping.", "woocommerce-paypal-commerce-gateway")
            );
        }
        $address = $this->addressFactory->fromPayPalRequest($data->address);
        return new Shipping(
            $data->name->full_name,
            $address
        );
    }
}