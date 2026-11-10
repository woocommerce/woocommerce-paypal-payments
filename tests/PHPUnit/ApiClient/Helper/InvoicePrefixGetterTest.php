<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectApplied;

class InvoicePrefixGetterTest extends TestCase
{
	private function create_testee(string $stored_prefix): InvoicePrefixGetter
	{
		$settings = Mockery::mock(SettingsProvider::class);
		$settings->shouldReceive('invoice_prefix')->andReturn($stored_prefix);

		return new InvoicePrefixGetter($settings);
	}

	/**
	 * GIVEN a merchant-configured invoice prefix and no filter overrides
	 * WHEN the prefix is retrieved
	 * THEN the stored prefix is returned
	 */
	public function test_returns_stored_prefix(): void
	{
		$this->assertSame('Store_1', $this->create_testee('Store_1')->get());
	}

	/**
	 * GIVEN a merchant who cleared the invoice prefix
	 * WHEN the prefix is retrieved
	 * THEN an empty string is returned, without any default substituted
	 */
	public function test_returns_empty_string_when_stored_prefix_is_empty(): void
	{
		$this->assertSame('', $this->create_testee('')->get());
	}

	/**
	 * GIVEN a third party filtering the invoice prefix
	 * WHEN the prefix is retrieved
	 * THEN the filtered value is returned
	 */
	public function test_returns_filtered_prefix(): void
	{
		expectApplied('woocommerce_paypal_payments_invoice_prefix')
			->once()
			->with('Store_1')
			->andReturn('Filtered_');

		$this->assertSame('Filtered_', $this->create_testee('Store_1')->get());
	}

	/**
	 * GIVEN a filter callback that returns something other than a string
	 * WHEN the prefix is retrieved
	 * THEN the unfiltered stored prefix is returned
	 *
	 * @dataProvider non_string_filter_result_provider
	 * @param mixed $filter_result
	 */
	public function test_ignores_non_string_filter_result($filter_result): void
	{
		expectApplied('woocommerce_paypal_payments_invoice_prefix')
			->once()
			->andReturn($filter_result);

		$this->assertSame('Store_1', $this->create_testee('Store_1')->get());
	}

	public function non_string_filter_result_provider(): array
	{
		return [
			'null'    => [null],
			'array'   => [['x']],
			'integer' => [5],
			'boolean' => [false],
		];
	}

	/**
	 * GIVEN a configured invoice prefix
	 * WHEN the getter is cast to a string
	 * THEN the result equals the prefix returned by get()
	 */
	public function test_string_cast_equals_get(): void
	{
		$testee = $this->create_testee('Store_1');

		$this->assertSame($testee->get(), (string) $testee);
	}
}
