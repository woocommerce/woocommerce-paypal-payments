<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice;

use WooCommerce\PayPalCommerce\TestCase;

class PaymentSourceTest extends TestCase
{
	private function makeSource(): PaymentSource
	{
		return new PaymentSource(
			'Max',
			'Mustermann',
			'max@example.test',
			'1990-01-01',
			'1701234567',
			'49',
			'Hauptstraße 1',
			'Berlin',
			'10115',
			'DE',
			'de-DE',
			'Acme GmbH',
			'https://example.test/logo.png',
			array('My customer service instruction.')
		);
	}

	public function testAccessorsReturnConstructorValues(): void
	{
		$sut = $this->makeSource();

		$this->assertSame('Max', $sut->given_name());
		$this->assertSame('Mustermann', $sut->surname());
		$this->assertSame('max@example.test', $sut->email());
		$this->assertSame('1990-01-01', $sut->birth_date());
		$this->assertSame('1701234567', $sut->national_number());
		$this->assertSame('49', $sut->phone_country_code());
		$this->assertSame('Hauptstraße 1', $sut->address_line_1());
		$this->assertSame('Berlin', $sut->admin_area_2());
		$this->assertSame('10115', $sut->postal_code());
		$this->assertSame('DE', $sut->country_code());
		$this->assertSame('de-DE', $sut->locale());
		$this->assertSame('Acme GmbH', $sut->brand_name());
		$this->assertSame('https://example.test/logo.png', $sut->logo_url());
		$this->assertSame(array('My customer service instruction.'), $sut->customer_service_instructions());
	}

	public function testToArrayProducesPayPalPaymentSourceStructure(): void
	{
		$sut = $this->makeSource();

		$this->assertSame(
			array(
				'name'               => array(
					'given_name' => 'Max',
					'surname'    => 'Mustermann',
				),
				'email'              => 'max@example.test',
				'birth_date'         => '1990-01-01',
				'phone'              => array(
					'national_number' => '1701234567',
					'country_code'    => '49',
				),
				'billing_address'    => array(
					'address_line_1' => 'Hauptstraße 1',
					'admin_area_2'   => 'Berlin',
					'postal_code'    => '10115',
					'country_code'   => 'DE',
				),
				'experience_context' => array(
					'locale'                        => 'de-DE',
					'brand_name'                    => 'Acme GmbH',
					'logo_url'                      => 'https://example.test/logo.png',
					'customer_service_instructions' => array('My customer service instruction.'),
				),
			),
			$sut->to_array()
		);
	}
}
