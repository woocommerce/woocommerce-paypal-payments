<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class CustomerRepositoryTest extends TestCase
{
	/**
	 * GIVEN a user with both the most recently assigned PayPal customer ID and the legacy vaulted ID stored
	 * WHEN resolving the PayPal customer ID for that user
	 * THEN the most recently assigned ID takes precedence over the legacy one
	 */
	public function testMostRecentlyAssignedIdTakesPrecedenceOverLegacyId()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		expect('get_user_meta')
			->once()
			->with(42, '_ppcp_target_customer_id', true)
			->andReturn('most-recent-customer-id');

		expect('get_user_meta')
			->never()
			->with(42, 'ppcp_customer_id', true);

		$this->assertSame('most-recent-customer-id', $repository->paypal_customer_id_for_user(42));
	}

	/**
	 * GIVEN a user whose most recently assigned PayPal customer ID was written back by a billing
	 *       agreement conversion, with no legacy vaulted ID present
	 * WHEN resolving the PayPal customer ID for that user
	 * THEN the previously assigned ID is found, preventing a duplicate PayPal customer from being
	 *      created on a subsequent renewal
	 */
	public function testFindsIdWrittenBackByBillingAgreementConversion()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		expect('get_user_meta')
			->once()
			->with(42, '_ppcp_target_customer_id', true)
			->andReturn('converted-customer-id');

		$this->assertSame('converted-customer-id', $repository->paypal_customer_id_for_user(42));
	}

	/**
	 * GIVEN a user with only the legacy vaulted PayPal customer ID stored
	 * WHEN resolving the PayPal customer ID for that user
	 * THEN the legacy ID is returned as a fallback
	 */
	public function testFallsBackToLegacyIdWhenMostRecentlyAssignedIdIsMissing()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		expect('get_user_meta')
			->once()
			->with(42, '_ppcp_target_customer_id', true)
			->andReturn('');

		expect('get_user_meta')
			->once()
			->with(42, 'ppcp_customer_id', true)
			->andReturn('legacy-customer-id');

		$this->assertSame('legacy-customer-id', $repository->paypal_customer_id_for_user(42));
	}

	public function testReturnsEmptyStringWhenNeverVaulted()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		// get_user_meta returns '' for both keys when the user was never vaulted.
		expect('get_user_meta')
			->once()
			->with(42, '_ppcp_target_customer_id', true)
			->andReturn('');

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

	/**
	 * GIVEN a user with a previously stored ppcp_customer_id
	 * WHEN resolving the vault customer ID for that user
	 * THEN the stored ID is returned verbatim, regardless of the repository's configured prefix
	 */
	public function test_stored_customer_id_takes_precedence_over_generated_one()
	{
		$repository = new CustomerRepository('ppcp_prefix_');

		expect('get_user_meta')
			->once()
			->with(5, 'ppcp_guest_customer_id', true)
			->andReturn('');

		expect('get_user_meta')
			->once()
			->with(5, 'ppcp_customer_id', true)
			->andReturn('PAYPAL-123');

		$this->assertSame('PAYPAL-123', $repository->customer_id_for_user(5));
	}
}
