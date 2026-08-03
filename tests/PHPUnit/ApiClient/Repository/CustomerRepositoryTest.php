<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class CustomerRepositoryTest extends TestCase
{
	public function testReturnsRealPayPalCustomerId()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		expect('get_user_meta')
			->once()
			->with(42, 'ppcp_customer_id', true)
			->andReturn('paypal-customer-id');

		$this->assertSame('paypal-customer-id', $repository->paypal_customer_id_for_user(42));
	}

	public function testReturnsEmptyStringWhenNeverVaulted()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		// get_user_meta returns '' for an unset meta value; no local fallback ID.
		expect('get_user_meta')
			->once()
			->with(42, 'ppcp_customer_id', true)
			->andReturn('');

		$this->assertSame('', $repository->paypal_customer_id_for_user(42));
	}

	public function testReturnsEmptyStringForGuestUser()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		expect('get_user_meta')->never();

		$this->assertSame('', $repository->paypal_customer_id_for_user(0));
	}
}
