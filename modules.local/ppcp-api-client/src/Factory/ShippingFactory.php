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

    public function fromWcCustomer(\WC_Customer $customer): Shipping
    {
        // Replicates the Behavior of \WC_Order::get_formatted_shipping_full_name()
        $fullName = sprintf(
            // translators: %1$s is the first name and %2$s is the second name. wc translation.
            _x('%1$s %2$s', 'full name', 'woocommerce'),
            $customer->get_shipping_first_name(),
            $customer->get_shipping_last_name()
        );
        $address = $this->addressFactory->fromWcCustomer($customer);
        return new Shipping(
            $fullName,
            $address
        );
    }

    public function fromWcOrder(\WC_Order $order): Shipping
    {
        $fullName = $order->get_formatted_shipping_full_name();
        $address = $this->addressFactory->fromWcOrder($order);
        return new Shipping(
            $fullName,
            $address
        );
    }

    public function fromPayPalResponse(\stdClass $data): Shipping
    {
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
