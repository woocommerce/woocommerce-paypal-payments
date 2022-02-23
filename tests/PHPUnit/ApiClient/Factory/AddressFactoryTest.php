<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

class AddressFactoryTest extends TestCase
{

    public function testFromWcCustomer()
    {
        $testee = new AddressFactory();
        $customer = Mockery::mock(\WC_Customer::class);
        $customer
            ->expects('get_shipping_country')
            ->andReturn('shipping_country');
        $customer
            ->expects('get_shipping_address_1')
            ->andReturn('shipping_address_1');
        $customer
            ->expects('get_shipping_address_2')
            ->andReturn('shipping_address_2');
        $customer
            ->expects('get_shipping_state')
            ->andReturn('shipping_state');
        $customer
            ->expects('get_shipping_city')
            ->andReturn('shipping_city');
        $customer
            ->expects('get_shipping_postcode')
            ->andReturn('shipping_postcode');

        $result = $testee->from_wc_customer($customer);
        $this->assertEquals('shipping_country', $result->country_code());
        $this->assertEquals('shipping_address_1', $result->address_line_1());
        $this->assertEquals('shipping_address_2', $result->address_line_2());
        $this->assertEquals('shipping_state', $result->admin_area_1());
        $this->assertEquals('shipping_city', $result->admin_area_2());
        $this->assertEquals('shipping_postcode', $result->postal_code());
    }

    public function testFromWcCustomersBillingAddress()
    {
        $testee = new AddressFactory();
        $customer = Mockery::mock(\WC_Customer::class);
        $customer
            ->expects('get_billing_country')
            ->andReturn('billing_country');
        $customer
            ->expects('get_billing_address_1')
            ->andReturn('billing_address_1');
        $customer
            ->expects('get_billing_address_2')
            ->andReturn('billing_address_2');
        $customer
            ->expects('get_billing_state')
            ->andReturn('billing_state');
        $customer
            ->expects('get_billing_city')
            ->andReturn('billing_city');
        $customer
            ->expects('get_billing_postcode')
            ->andReturn('billing_postcode');

        $result = $testee->from_wc_customer($customer, 'billing');
        $this->assertEquals('billing_country', $result->country_code());
        $this->assertEquals('billing_address_1', $result->address_line_1());
        $this->assertEquals('billing_address_2', $result->address_line_2());
        $this->assertEquals('billing_state', $result->admin_area_1());
        $this->assertEquals('billing_city', $result->admin_area_2());
        $this->assertEquals('billing_postcode', $result->postal_code());
    }

    public function testFromWcOrder()
    {
        $testee = new AddressFactory();
        $order = Mockery::mock(\WC_Order::class);
        $order
            ->expects('get_shipping_country')
            ->andReturn('shipping_country');
        $order
            ->expects('get_shipping_address_1')
            ->andReturn('shipping_address_1');
        $order
            ->expects('get_shipping_address_2')
            ->andReturn('shipping_address_2');
        $order
            ->expects('get_shipping_state')
            ->andReturn('shipping_state');
        $order
            ->expects('get_shipping_city')
            ->andReturn('shipping_city');
        $order
            ->expects('get_shipping_postcode')
            ->andReturn('shipping_postcode');

        $result = $testee->from_wc_order($order);
        $this->assertEquals('shipping_country', $result->country_code());
        $this->assertEquals('shipping_address_1', $result->address_line_1());
        $this->assertEquals('shipping_address_2', $result->address_line_2());
        $this->assertEquals('shipping_state', $result->admin_area_1());
        $this->assertEquals('shipping_city', $result->admin_area_2());
        $this->assertEquals('shipping_postcode', $result->postal_code());
    }

    /**
     * @dataProvider dataFromPayPalRequest
     */
    public function testFromPayPalRequest($data)
    {
        $testee = new AddressFactory();

        $result = $testee->from_paypal_response($data);
        $expectedAddressLine1 = (isset($data->address_line_1)) ? $data->address_line_1 : '';
        $expectedAddressLine2 = (isset($data->address_line_2)) ? $data->address_line_2 : '';
        $expectedAdminArea1 = (isset($data->admin_area_1)) ? $data->admin_area_1 : '';
        $expectedAdminArea2 = (isset($data->admin_area_2)) ? $data->admin_area_2 : '';
        $expectedPostalCode = (isset($data->postal_code)) ? $data->postal_code : '';
        $this->assertEquals($data->country_code, $result->country_code());
        $this->assertEquals($expectedAddressLine1, $result->address_line_1());
        $this->assertEquals($expectedAddressLine2, $result->address_line_2());
        $this->assertEquals($expectedAdminArea1, $result->admin_area_1());
        $this->assertEquals($expectedAdminArea2, $result->admin_area_2());
        $this->assertEquals($expectedPostalCode, $result->postal_code());
    }

    public function testFromPayPalRequestThrowsError()
    {
        $testee = new AddressFactory();

        $data = (object) [
            'address_line_1' => 'shipping_address_1',
            'address_line_2' => 'shipping_address_2',
            'admin_area_1' => 'shipping_admin_area_1',
            'admin_area_2' => 'shipping_admin_area_2',
            'postal_code' => 'shipping_postcode',
        ];
        $this->expectException(RuntimeException::class);
        $testee->from_paypal_response($data);
    }

    public function dataFromPayPalRequest() : array
    {
        return [
            'default' => [
                (object) [
                    'country_code' => 'shipping_country',
                    'address_line_1' => 'shipping_address_1',
                    'address_line_2' => 'shipping_address_2',
                    'admin_area_1' => 'shipping_admin_area_1',
                    'admin_area_2' => 'shipping_admin_area_2',
                    'postal_code' => 'shipping_postcode',
                ],
            ],
            'no_admin_area_2' => [
                (object) [
                    'country_code' => 'shipping_country',
                    'address_line_1' => 'shipping_address_1',
                    'address_line_2' => 'shipping_address_2',
                    'admin_area_1' => 'shipping_admin_area_1',
                    'postal_code' => 'shipping_postcode',
                ],
            ],
            'no_postal_code' => [
                (object) [
                    'country_code' => 'shipping_country',
                    'address_line_1' => 'shipping_address_1',
                    'address_line_2' => 'shipping_address_2',
                    'admin_area_1' => 'shipping_admin_area_1',
                    'admin_area_2' => 'shipping_admin_area_2',
                ],
            ],
            'no_admin_area_1' => [
                (object) [
                    'country_code' => 'shipping_country',
                    'address_line_1' => 'shipping_address_1',
                    'address_line_2' => 'shipping_address_2',
                    'admin_area_2' => 'shipping_admin_area_2',
                    'postal_code' => 'shipping_postcode',
                ],
            ],
            'no_address_line_1' => [
                (object) [
                    'country_code' => 'shipping_country',
                    'address_line_2' => 'shipping_address_2',
                    'admin_area_1' => 'shipping_admin_area_1',
                    'admin_area_2' => 'shipping_admin_area_2',
                    'postal_code' => 'shipping_postcode',
                ],
            ],
            'no_address_line_2' => [
                (object) [
                    'country_code' => 'shipping_country',
                    'address_line_1' => 'shipping_address_1',
                    'admin_area_1' => 'shipping_admin_area_1',
                    'admin_area_2' => 'shipping_admin_area_2',
                    'postal_code' => 'shipping_postcode',
                ],
            ],
        ];
    }
}
