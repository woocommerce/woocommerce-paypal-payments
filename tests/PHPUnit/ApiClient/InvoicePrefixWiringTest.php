<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use Mockery;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use function Brain\Monkey\Functions\when;

/**
 * Covers the real container wiring for the invoice-prefix/customer-prefix split:
 * an empty merchant-configured invoice prefix must reach 'api.prefix' verbatim,
 * while the vault customer ID resolved through 'api.repository.customer' must
 * never degrade to a bare, unprefixed user ID.
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
	 * THEN it resolves to an empty string rather than substituting the historical 'WC-' default
	 */
	public function test_invoice_prefix_resolves_empty_when_merchant_clears_it(): void
	{
		$this->invoicePrefix = '';

		$result = $this->appContainer->get('api.prefix');

		self::assertSame('', $result);
	}

	/**
	 * GIVEN an empty invoice prefix and a user who has never been vaulted with PayPal
	 * WHEN the container-resolved customer repository fabricates a customer ID for that user
	 * THEN it still applies the 'WC-' fallback rather than exposing the bare user ID, because
	 *      the vault customer_id is merchant-scoped and must never degrade to a raw numeric ID
	 */
	public function test_customer_id_keeps_fallback_prefix_when_invoice_prefix_is_empty(): void
	{
		$this->invoicePrefix = '';

		when('get_user_meta')->justReturn('');

		$repository = $this->appContainer->get('api.repository.customer');

		$customer_id = $repository->customer_id_for_user(5);

		self::assertSame('WC-5', $customer_id);
		self::assertNotSame('5', $customer_id);
	}

	/**
	 * GIVEN a merchant-configured, non-empty invoice prefix
	 * WHEN both the invoice-ID prefix and the fabricated customer ID are resolved from the container
	 * THEN both use the configured prefix exactly, proving 'api.customer-prefix' does not shadow a
	 *      real configured value with the 'WC-' fallback
	 */
	public function test_configured_prefix_reaches_both_invoice_and_customer_ids(): void
	{
		$this->invoicePrefix = 'Store_1';

		when('get_user_meta')->justReturn('');

		$repository = $this->appContainer->get('api.repository.customer');

		self::assertSame('Store_1', $this->appContainer->get('api.prefix'));
		self::assertSame('Store_15', $repository->customer_id_for_user(5));
	}
}