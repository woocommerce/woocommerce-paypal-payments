<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\E2e\Validation;

use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WooCommerce\PayPalCommerce\Button\Validation\CheckoutFormValidator;
use WooCommerce\PayPalCommerce\Tests\E2e\TestCase;

class ValidationTest extends TestCase
{
	protected $container;

	/**
	 * @var CheckoutFormValidator
	 */
	protected $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->container = $this->getContainer();

		$this->sut = $this->container->get( 'button.validation.wc-checkout-validator' );
		assert($this->sut instanceof CheckoutFormValidator);
	}

    public function testValid()
    {
		$this->sut->validate([
			'billing_first_name'=>'John',
			'billing_last_name'=>'Doe',
			'billing_company'=>'',
			'billing_country'=>'DE',
			'billing_address_1'=>'1 Main St',
			'billing_address_2'=>'city1',
			'billing_postcode'=>'11111',
			'billing_city'=>'city1',
			'billing_state'=>'DE-BW',
			'billing_phone'=>'12345678',
			'billing_email'=>'a@gmail.com',
			'terms-field'=>'1',
			'terms'=>'on',
		]);
    }

    public function testInvalid()
    {
		$this->expectException(ValidationException::class);
		$this->expectExceptionMessageMatches('/.+First name.+Postcode/i');

		$this->sut->validate([
			'billing_first_name'=>'',
			'billing_postcode'=>'ABCDE',

			'billing_last_name'=>'Doe',
			'billing_company'=>'',
			'billing_country'=>'DE',
			'billing_address_1'=>'1 Main St',
			'billing_address_2'=>'city1',
			'billing_city'=>'city1',
			'billing_state'=>'DE-BW',
			'billing_phone'=>'12345678',
			'billing_email'=>'a@gmail.com',
			'terms-field'=>'1',
			'terms'=>'on',
		]);
    }
}
