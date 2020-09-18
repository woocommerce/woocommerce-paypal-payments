<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\TestCase;

class AddressTest extends TestCase
{

    public function test()
    {
        $testee = new Address(
            'countryCode',
            'addressLine1',
            'addressLine2',
            'adminArea1',
            'adminArea2',
            'postalCode'
        );

        $this->assertEquals('countryCode', $testee->country_code());
        $this->assertEquals('addressLine1', $testee->address_line_1());
        $this->assertEquals('addressLine2', $testee->address_line_2());
        $this->assertEquals('adminArea1', $testee->admin_area_1());
        $this->assertEquals('adminArea2', $testee->admin_area_2());
        $this->assertEquals('postalCode', $testee->postal_code());
    }

    public function testToArray()
    {
        $testee = new Address(
            'countryCode',
            'addressLine1',
            'addressLine2',
            'adminArea1',
            'adminArea2',
            'postalCode'
        );

        $expected = [
            'country_code' => 'countryCode',
            'address_line_1' => 'addressLine1',
            'address_line_2' => 'addressLine2',
            'admin_area_1' => 'adminArea1',
            'admin_area_2' => 'adminArea2',
            'postal_code' => 'postalCode',
        ];

        $actual = $testee->to_array();
        $this->assertEquals($expected, $actual);
    }
}
