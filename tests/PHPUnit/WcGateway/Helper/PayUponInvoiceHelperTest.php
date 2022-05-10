<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use DateTime;
use WooCommerce\PayPalCommerce\TestCase;

class PayUponInvoiceHelperTest extends TestCase
{
	/**
	 * @dataProvider datesProvider
	 */
	public function testValidateBirthDate($input, $output)
	{
		$this->assertSame((new PayUponInvoiceHelper())->validate_birth_date($input), $output);
	}

	public function datesProvider(): array{
		$format = 'Y-m-d';

		return [
			['', false],
			[(new DateTime())->format($format), false],
			[(new DateTime('-17 years'))->format($format), false],
			['31-02-1942', false],
			['01-01-1942', false],
			['1942-01-01', true],
		];
	}

}
