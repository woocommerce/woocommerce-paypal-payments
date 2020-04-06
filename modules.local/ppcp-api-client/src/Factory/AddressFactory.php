<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;


use Inpsyde\PayPalCommerce\ApiClient\Entity\Address;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class AddressFactory
{

    public function __construct()
    {
    }

    public function fromPayPalRequest(\stdClass $data) : Address {

        if (! isset($data->country_code)) {
            new RuntimeException(__('No country given for address.', 'woocommerce-paypal-commerce-gateway'));
        }
        return new Address(
            $data->country_code,
            (isset($data->address_line_1))?$data->address_line_1:'',
            (isset($data->address_line_2))?$data->address_line_2:'',
            (isset($data->admin_area_1))?$data->admin_area_1:'',
            (isset($data->admin_area_2))?$data->admin_area_2:'',
            (isset($data->postal_code))?$data->postal_code:''
        );
    }
}