<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class PayerFactoryTest extends TestCase
{

    public function testFromWcCustomer()
    {
        $expectedPhone = '012345678901';
        $expectedEmail = 'test@example.com';
        $expectedFirstName = 'John';
        $expectedLastName = 'Locke';
        $address = Mockery::mock(Address::class);
        $customer = Mockery::mock(\WC_Customer::class);
        $customer
            ->shouldReceive('get_billing_phone')
            ->andReturn($expectedPhone);
        $customer
            ->shouldReceive('get_billing_email')
            ->andReturn($expectedEmail);
        $customer
            ->shouldReceive('get_billing_last_name')
            ->andReturn($expectedLastName);
        $customer
            ->shouldReceive('get_billing_first_name')
            ->andReturn($expectedFirstName);
        $addressFactory = Mockery::mock(AddressFactory::class);
        $addressFactory
            ->expects('from_wc_customer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->from_customer($customer);

        $this->assertEquals($expectedEmail, $result->email_address());
        $this->assertEquals($expectedLastName, $result->name()->surname());
        $this->assertEquals($expectedFirstName, $result->name()->given_name());
        $this->assertEquals($address, $result->address());
        $this->assertEquals($expectedPhone, $result->phone()->phone()->national_number());
        $this->assertNull($result->birthdate());
        $this->assertEmpty($result->payer_id());
    }

    /**
     * The phone number is only allowed to contain numbers.
     * The WC_Customer get_billing_phone can contain other characters, which need to
     * get stripped.
     */
    public function testFromWcCustomerStringsFromNumberAreRemoved()
    {
        $expectedPhone = '012345678901';
        $expectedEmail = 'test@example.com';
        $expectedFirstName = 'John';
        $expectedLastName = 'Locke';
        $address = Mockery::mock(Address::class);
        $customer = Mockery::mock(\WC_Customer::class);
        $customer
            ->shouldReceive('get_billing_phone')
            ->andReturn($expectedPhone . 'abcdefg');
        $customer
            ->shouldReceive('get_billing_email')
            ->andReturn($expectedEmail);
        $customer
            ->shouldReceive('get_billing_last_name')
            ->andReturn($expectedLastName);
        $customer
            ->shouldReceive('get_billing_first_name')
            ->andReturn($expectedFirstName);
        $addressFactory = Mockery::mock(AddressFactory::class);
        $addressFactory
            ->expects('from_wc_customer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->from_customer($customer);

        $this->assertEquals($expectedPhone, $result->phone()->phone()->national_number());
    }

    public function testFromWcCustomerNoNumber()
    {
        $expectedEmail = 'test@example.com';
        $expectedFirstName = 'John';
        $expectedLastName = 'Locke';
        $address = Mockery::mock(Address::class);
        $customer = Mockery::mock(\WC_Customer::class);
        $customer
            ->shouldReceive('get_billing_phone')
            ->andReturn('');
        $customer
            ->shouldReceive('get_billing_email')
            ->andReturn($expectedEmail);
        $customer
            ->shouldReceive('get_billing_last_name')
            ->andReturn($expectedLastName);
        $customer
            ->shouldReceive('get_billing_first_name')
            ->andReturn($expectedFirstName);
        $addressFactory = Mockery::mock(AddressFactory::class);
        $addressFactory
            ->expects('from_wc_customer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->from_customer($customer);

        $this->assertNull($result->phone());
    }

    /**
     * The phone number is not allowed to be longer than 14 characters.
     * We need to make sure, we strip the number if longer.
     */
    public function testFromWcCustomerTooLongNumberGetsStripped()
    {
        $expectedPhone = '01234567890123';
        $expectedEmail = 'test@example.com';
        $expectedFirstName = 'John';
        $expectedLastName = 'Locke';
        $address = Mockery::mock(Address::class);
        $customer = Mockery::mock(\WC_Customer::class);
        $customer
            ->shouldReceive('get_billing_phone')
            ->andReturn($expectedPhone . '456789');
        $customer
            ->shouldReceive('get_billing_email')
            ->andReturn($expectedEmail);
        $customer
            ->shouldReceive('get_billing_last_name')
            ->andReturn($expectedLastName);
        $customer
            ->shouldReceive('get_billing_first_name')
            ->andReturn($expectedFirstName);
        $addressFactory = Mockery::mock(AddressFactory::class);
        $addressFactory
            ->expects('from_wc_customer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->from_customer($customer);

        $this->assertEquals($expectedPhone, $result->phone()->phone()->national_number());
    }

    /**
     * @dataProvider dataForTestFromPayPalResponse
     */
    public function testFromPayPalResponse($data)
    {
        $addressFactory = Mockery::mock(AddressFactory::class);
        $addressFactory
            ->expects('from_paypal_response')
            ->with($data->address)
            ->andReturn(Mockery::mock(Address::class));
        $testee = new PayerFactory($addressFactory);
        $payer = $testee->from_paypal_response($data);
        $this->assertEquals($data->email_address, $payer->email_address());
        $this->assertEquals($data->payer_id, $payer->payer_id());
        $this->assertEquals($data->name->given_name, $payer->name()->given_name());
        $this->assertEquals($data->name->surname, $payer->name()->surname());
        if (isset($data->phone)) {
            $this->assertEquals($data->phone->phone_type, $payer->phone()->type());
            $this->assertEquals($data->phone->phone_number->national_number, $payer->phone()->phone()->national_number());
        } else {
            $this->assertNull($payer->phone());
        }
        $this->assertInstanceOf(Address::class, $payer->address());
        if (isset($data->tax_info)) {
            $this->assertEquals($data->tax_info->tax_id, $payer->tax_info()->tax_id());
            $this->assertEquals($data->tax_info->tax_id_type, $payer->tax_info()->type());
        } else {
            $this->assertNull($payer->tax_info());
        }
        if (isset($data->birth_date)) {
            $this->assertEquals($data->birth_date, $payer->birthdate()->format('Y-m-d'));
        } else {
            $this->assertNull($payer->birthdate());
        }
    }

    public function dataForTestFromPayPalResponse() : array
    {
        return [
            'default' => [
                (object)[
                    'address' => new \stdClass(),
                    'name' => (object)[
                        'given_name' => 'given_name',
                        'surname' => 'surname',
                    ],
                    'phone' => (object)[
                        'phone_type' => 'HOME',
                        'phone_number' => (object)[
                            'national_number' => '1234567890',
                        ],
                    ],
                    'tax_info' => (object)[
                        'tax_id' => 'tax_id',
                        'tax_id_type' => 'BR_CPF',
                    ],
                    'birth_date' => '1970-01-01',
                    'email_address' => 'email_address',
                    'payer_id' => 'payer_id',
                ],
            ],
            'no_phone' => [
                (object)[
                    'address' => new \stdClass(),
                    'name' => (object)[
                        'given_name' => 'given_name',
                        'surname' => 'surname',
                    ],
                    'tax_info' => (object)[
                        'tax_id' => 'tax_id',
                        'tax_id_type' => 'BR_CPF',
                    ],
                    'birth_date' => '1970-01-01',
                    'email_address' => 'email_address',
                    'payer_id' => 'payer_id',
                ],
            ],
            'no_tax_info' => [
                (object)[
                    'address' => new \stdClass(),
                    'name' => (object)[
                        'given_name' => 'given_name',
                        'surname' => 'surname',
                    ],
                    'phone' => (object)[
                        'phone_type' => 'HOME',
                        'phone_number' => (object)[
                            'national_number' => '1234567890',
                        ],
                    ],
                    'birth_date' => '1970-01-01',
                    'email_address' => 'email_address',
                    'payer_id' => 'payer_id',
                ],
            ],
            'no_birth_date' => [
                (object)[
                    'address' => new \stdClass(),
                    'name' => (object)[
                        'given_name' => 'given_name',
                        'surname' => 'surname',
                    ],
                    'phone' => (object)[
                        'phone_type' => 'HOME',
                        'phone_number' => (object)[
                            'national_number' => '1234567890',
                        ],
                    ],
                    'tax_info' => (object)[
                        'tax_id' => 'tax_id',
                        'tax_id_type' => 'BR_CPF',
                    ],
                    'email_address' => 'email_address',
                    'payer_id' => 'payer_id',
                ],
            ],
        ];
    }
}
