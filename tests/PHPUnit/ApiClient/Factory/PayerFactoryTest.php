<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Address;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
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
            ->expects('fromWcCustomer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->fromCustomer($customer);

        $this->assertEquals($expectedEmail, $result->emailAddress());
        $this->assertEquals($expectedLastName, $result->name()->surname());
        $this->assertEquals($expectedFirstName, $result->name()->givenName());
        $this->assertEquals($address, $result->address());
        $this->assertEquals($expectedPhone, $result->phone()->phone()->nationalNumber());
        $this->assertNull($result->birthDate());
        $this->assertEmpty($result->payerId());
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
            ->expects('fromWcCustomer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->fromCustomer($customer);

        $this->assertEquals($expectedPhone, $result->phone()->phone()->nationalNumber());
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
            ->expects('fromWcCustomer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->fromCustomer($customer);

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
            ->expects('fromWcCustomer')
            ->with($customer, 'billing')
            ->andReturn($address);
        $testee = new PayerFactory($addressFactory);
        $result = $testee->fromCustomer($customer);

        $this->assertEquals($expectedPhone, $result->phone()->phone()->nationalNumber());
    }

    /**
     * @dataProvider dataForTestFromPayPalResponse
     */
    public function testFromPayPalResponse($data)
    {
        $addressFactory = Mockery::mock(AddressFactory::class);
        $addressFactory
            ->expects('fromPayPalRequest')
            ->with($data->address)
            ->andReturn(Mockery::mock(Address::class));
        $testee = new PayerFactory($addressFactory);
        $payer = $testee->fromPayPalResponse($data);
        $this->assertEquals($data->email_address, $payer->emailAddress());
        $this->assertEquals($data->payer_id, $payer->payerId());
        $this->assertEquals($data->name->given_name, $payer->name()->givenName());
        $this->assertEquals($data->name->surname, $payer->name()->surname());
        if (isset($data->phone)) {
            $this->assertEquals($data->phone->phone_type, $payer->phone()->type());
            $this->assertEquals($data->phone->phone_number->national_number, $payer->phone()->phone()->nationalNumber());
        } else {
            $this->assertNull($payer->phone());
        }
        $this->assertInstanceOf(Address::class, $payer->address());
        if (isset($data->tax_info)) {
            $this->assertEquals($data->tax_info->tax_id, $payer->taxInfo()->taxId());
            $this->assertEquals($data->tax_info->tax_id_type, $payer->taxInfo()->type());
        } else {
            $this->assertNull($payer->taxInfo());
        }
        if (isset($data->birth_date)) {
            $this->assertEquals($data->birth_date, $payer->birthDate()->format('Y-m-d'));
        } else {
            $this->assertNull($payer->birthDate());
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
