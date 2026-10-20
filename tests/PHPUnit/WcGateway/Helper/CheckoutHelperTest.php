<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use DateTime;
use WooCommerce\PayPalCommerce\TestCase;

class CheckoutHelperTest extends TestCase
{
	public function tearDown(): void
	{
		$_POST = [];

		parent::tearDown();
	}

	/**
	 * @dataProvider datesProvider
	 */
	public function testValidateBirthDate($input, $output)
	{
		$this->assertSame((new CheckoutHelper())->validate_birth_date($input), $output);
	}

	public function datesProvider(): array{
		$format = 'Y-m-d';

		return [
			['', false],
			[(new DateTime())->format($format), false],
			[(new DateTime('-17 years'))->format($format), false],
			['1942-02-31', false],
			['01-01-1942', false],
			['1942-01-01', true],
			['0001-01-01', false],
		];
	}

	/**
	 * GIVEN both the PUI phone field and the WooCommerce billing phone field are submitted
	 * WHEN submitted_phone is called with PUI's field name first, then WooCommerce's
	 * THEN the first field's value is returned
	 */
	public function testSubmittedPhoneReturnsFirstFieldWhenBothPresent()
	{
		$_POST['ppcp_pui_billing_phone'] = '+1 555 0100';
		$_POST['billing_phone'] = '+1 555 0200';

		$result = (new CheckoutHelper())->submitted_phone('ppcp_pui_billing_phone', 'billing_phone');

		$this->assertSame('+1 555 0100', $result);
	}

	/**
	 * GIVEN only the WooCommerce billing phone field is submitted
	 * WHEN submitted_phone is called with PUI's field name first, then WooCommerce's
	 * THEN the second field's value is returned
	 */
	public function testSubmittedPhoneFallsBackToSecondFieldWhenFirstIsAbsent()
	{
		$_POST['billing_phone'] = '+1 555 0200';

		$result = (new CheckoutHelper())->submitted_phone('ppcp_pui_billing_phone', 'billing_phone');

		$this->assertSame('+1 555 0200', $result);
	}

	/**
	 * GIVEN the PUI phone field is submitted as an empty string and the billing phone field has a value
	 * WHEN submitted_phone is called with PUI's field name first, then WooCommerce's
	 * THEN the second field's value is returned
	 */
	public function testSubmittedPhoneFallsBackToSecondFieldWhenFirstIsEmpty()
	{
		$_POST['ppcp_pui_billing_phone'] = '';
		$_POST['billing_phone'] = '+1 555 0200';

		$result = (new CheckoutHelper())->submitted_phone('ppcp_pui_billing_phone', 'billing_phone');

		$this->assertSame('+1 555 0200', $result);
	}

	/**
	 * GIVEN none of the requested fields are submitted
	 * WHEN submitted_phone is called with any field names
	 * THEN an empty string is returned
	 */
	public function testSubmittedPhoneReturnsEmptyStringWhenNoFieldIsPresent()
	{
		$result = (new CheckoutHelper())->submitted_phone('ppcp_pui_billing_phone', 'billing_phone');

		$this->assertSame('', $result);
	}

	/**
	 * GIVEN the only submitted field value is not a string (e.g. an array)
	 * WHEN submitted_phone is called
	 * THEN the non-string value is skipped and an empty string is returned
	 */
	public function testSubmittedPhoneSkipsNonStringValue()
	{
		$_POST['ppcp_pui_billing_phone'] = ['unexpected', 'array'];

		$result = (new CheckoutHelper())->submitted_phone('ppcp_pui_billing_phone', 'billing_phone');

		$this->assertSame('', $result);
	}

	/**
	 * GIVEN a single field is submitted
	 * WHEN submitted_phone is called with only that one field name
	 * THEN the field's value is returned
	 */
	public function testSubmittedPhoneWorksWithSingleFieldArgument()
	{
		$_POST['billing_phone'] = '+1 555 0300';

		$result = (new CheckoutHelper())->submitted_phone('billing_phone');

		$this->assertSame('+1 555 0300', $result);
	}

}
