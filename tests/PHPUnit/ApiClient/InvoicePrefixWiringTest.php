<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Helper\InvoicePrefixGetter;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use function Brain\Monkey\Functions\when;

/**
 * Covers the real container wiring of the invoice prefix: 'api.prefix' resolves to an
 * InvoicePrefixGetter that yields the merchant-configured prefix verbatim (including an
 * empty one, with no default substituted), and the same prefix feeds the customer IDs
 * fabricated by 'api.repository.customer'.
 *
 * The behaviour under test lives in container definitions rather than a single
 * class, so the only class-level @covers target is the repository the wiring
 * feeds.
 *
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository
 */
class InvoicePrefixWiringTest extends ModularTestCase
{
	private $appContainer;

	private $settingsProvider;

	private $invoicePrefix = '';

	public function setUp(): void
	{
		parent::setUp();

		$this->settingsProvider = Mockery::mock(SettingsProvider::class)->shouldIgnoreMissing('');
		$this->settingsProvider->shouldReceive('invoice_prefix')
			->andReturnUsing(function () {
				return $this->invoicePrefix;
			});

		$this->appContainer = $this->bootstrapModule([
			'settings.settings-provider' => function () {
				return $this->settingsProvider;
			},
		]);
	}

	/**
	 * GIVEN a merchant who cleared the Invoice Prefix field, persisting an empty string
	 * WHEN the 'api.prefix' container service is resolved
	 * THEN it resolves to a prefix getter yielding an empty string rather than the historical 'WC-' default
	 */
	public function test_invoice_prefix_resolves_empty_when_merchant_clears_it(): void
	{
		$this->invoicePrefix = '';

		$result = $this->appContainer->get('api.prefix');

		self::assertInstanceOf(InvoicePrefixGetter::class, $result);
		self::assertSame('', $result->get());
	}

	/**
	 * GIVEN an empty invoice prefix and a user who has never been vaulted with PayPal
	 * WHEN the container-resolved customer repository fabricates a customer ID for that user
	 * THEN the customer ID is the bare user ID
	 */
	public function test_customer_id_is_bare_user_id_when_invoice_prefix_is_empty(): void
	{
		$this->invoicePrefix = '';

		when('get_user_meta')->justReturn('');

		$repository = $this->appContainer->get('api.repository.customer');

		self::assertSame('5', $repository->customer_id_for_user(5));
	}

	/**
	 * GIVEN a merchant-configured, non-empty invoice prefix
	 * WHEN both the invoice-ID prefix and the fabricated customer ID are resolved from the container
	 * THEN both use the configured prefix exactly
	 */
	public function test_configured_prefix_reaches_both_invoice_and_customer_ids(): void
	{
		$this->invoicePrefix = 'Store_1';

		when('get_user_meta')->justReturn('');

		$repository = $this->appContainer->get('api.repository.customer');

		self::assertSame('Store_1', $this->appContainer->get('api.prefix')->get());
		self::assertSame('Store_15', $repository->customer_id_for_user(5));
	}
}