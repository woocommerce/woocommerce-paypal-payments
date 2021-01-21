<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class PayerTest extends TestCase
{

    public function testPayer()
    {
        $birthday = new \DateTime();
        $address = Mockery::mock(Address::class);
        $address
            ->expects('to_array')
            ->andReturn(['address']);
	    $address
		    ->expects('country_code')
		    ->andReturn('UK');
        $phone = Mockery::mock(PhoneWithType::class);
        $phone
            ->expects('to_array')
            ->andReturn(['phone']);
        $taxInfo = Mockery::mock(PayerTaxInfo::class);
        $taxInfo
            ->expects('to_array')
            ->andReturn(['taxInfo']);
        $payerName = Mockery::mock(PayerName::class);
        $payerName
            ->expects('to_array')
            ->andReturn(['payerName']);
        $email = 'email@example.com';
        $payerId = 'payerId';
        $payer = new Payer(
            $payerName,
            $email,
            $payerId,
            $address,
            $birthday,
            $phone,
            $taxInfo
        );

        $this->assertEquals($payerName, $payer->name());
        $this->assertEquals($email, $payer->email_address());
        $this->assertEquals($payerId, $payer->payer_id());
        $this->assertEquals($address, $payer->address());
        $this->assertEquals($birthday, $payer->birthdate());
        $this->assertEquals($phone, $payer->phone());
        $this->assertEquals($taxInfo, $payer->tax_info());

        $array = $payer->to_array();
        $this->assertEquals($birthday->format('Y-m-d'), $array['birth_date']);
        $this->assertEquals(['payerName'], $array['name']);
        $this->assertEquals($email, $array['email_address']);
        $this->assertEquals(['address'], $array['address']);
        $this->assertEquals($payerId, $array['payer_id']);
        $this->assertEquals(['phone'], $array['phone']);
        $this->assertEquals(['taxInfo'], $array['tax_info']);
    }

    public function testPayerNoId()
    {
        $birthday = new \DateTime();
        $address = Mockery::mock(Address::class);
        $address
            ->expects('to_array')
            ->andReturn(['address']);
	    $address
		    ->expects('country_code')
		    ->andReturn('UK');
        $phone = Mockery::mock(PhoneWithType::class);
        $phone
            ->expects('to_array')
            ->andReturn(['phone']);
        $taxInfo = Mockery::mock(PayerTaxInfo::class);
        $taxInfo
            ->expects('to_array')
            ->andReturn(['taxInfo']);
        $payerName = Mockery::mock(PayerName::class);
        $payerName
            ->expects('to_array')
            ->andReturn(['payerName']);
        $email = 'email@example.com';
        $payerId = '';
        $payer = new Payer(
            $payerName,
            $email,
            $payerId,
            $address,
            $birthday,
            $phone,
            $taxInfo
        );

        $this->assertEquals($payerId, $payer->payer_id());

        $array = $payer->to_array();
        $this->assertArrayNotHasKey('payer_id', $array);
    }

    public function testPayerNoPhone()
    {
        $birthday = new \DateTime();
        $address = Mockery::mock(Address::class);
        $address
            ->expects('to_array')
            ->andReturn(['address']);
	    $address
		    ->expects('country_code')
		    ->andReturn('UK');
        $phone = null;
        $taxInfo = Mockery::mock(PayerTaxInfo::class);
        $taxInfo
            ->expects('to_array')
            ->andReturn(['taxInfo']);
        $payerName = Mockery::mock(PayerName::class);
        $payerName
            ->expects('to_array')
            ->andReturn(['payerName']);
        $email = 'email@example.com';
        $payerId = 'payerId';
        $payer = new Payer(
            $payerName,
            $email,
            $payerId,
            $address,
            $birthday,
            $phone,
            $taxInfo
        );

        $this->assertEquals($phone, $payer->phone());

        $array = $payer->to_array();
        $this->assertArrayNotHasKey('phone', $array);
    }

    public function testPayerNoTaxInfo()
    {
        $birthday = new \DateTime();
        $address = Mockery::mock(Address::class);
        $address
            ->expects('to_array')
            ->andReturn(['address']);
	    $address
		    ->expects('country_code')
		    ->andReturn('UK');
        $phone = Mockery::mock(PhoneWithType::class);
        $phone
            ->expects('to_array')
            ->andReturn(['phone']);
        $taxInfo = null;
        $payerName = Mockery::mock(PayerName::class);
        $payerName
            ->expects('to_array')
            ->andReturn(['payerName']);
        $email = 'email@example.com';
        $payerId = 'payerId';
        $payer = new Payer(
            $payerName,
            $email,
            $payerId,
            $address,
            $birthday,
            $phone,
            $taxInfo
        );

        $this->assertEquals($taxInfo, $payer->tax_info());

        $array = $payer->to_array();
        $this->assertArrayNotHasKey('tax_info', $array);
    }

    public function testPayerNoBirthDate()
    {
        $birthday = null;
        $address = Mockery::mock(Address::class);
        $address
            ->expects('to_array')
            ->andReturn(['address']);
	    $address
		    ->expects('country_code')
		    ->andReturn('UK');
        $phone = Mockery::mock(PhoneWithType::class);
        $phone
            ->expects('to_array')
            ->andReturn(['phone']);
        $taxInfo = Mockery::mock(PayerTaxInfo::class);
        $taxInfo
            ->expects('to_array')
            ->andReturn(['taxInfo']);
        $payerName = Mockery::mock(PayerName::class);
        $payerName
            ->expects('to_array')
            ->andReturn(['payerName']);
        $email = 'email@example.com';
        $payerId = 'payerId';
        $payer = new Payer(
            $payerName,
            $email,
            $payerId,
            $address,
            $birthday,
            $phone,
            $taxInfo
        );

        $this->assertEquals($birthday, $payer->birthdate());

        $array = $payer->to_array();
        $this->assertArrayNotHasKey('birth_date', $array);
    }
}
