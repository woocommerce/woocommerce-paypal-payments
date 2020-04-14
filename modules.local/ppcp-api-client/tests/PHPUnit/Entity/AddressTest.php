<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


use Inpsyde\PayPalCommerce\ApiClient\TestCase;

class AddressTest extends TestCase
{

    public function test() {
        $testee = new Address(
            'countryCode',
            'addressLine1',
            'addressLine2',
            'adminArea1',
            'adminArea2',
            'postalCode'
        );

        $this->assertEquals('countryCode', $testee->countryCode());
        $this->assertEquals('addressLine1', $testee->addressLine1());
        $this->assertEquals('addressLine2', $testee->addressLine2());
        $this->assertEquals('adminArea1', $testee->adminArea1());
        $this->assertEquals('adminArea2', $testee->adminArea2());
        $this->assertEquals('postalCode', $testee->postalCode());
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

        $actual = $testee->toArray();
        $this->assertEquals($expected, $actual);
    }
}