<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\RenewalHandler;

/**
 * @scenario A prior fix scoped `add_mock_ppec_gateway()` to only run in certain contexts
 *           (a renewal in progress, My Account > Subscriptions, editing a PPEC order, ...)
 *           to keep the mock "PayPal (Legacy)" gateway off the Payments settings screen.
 *           That guard was a regression: `woocommerce_payment_gateways` fires exactly once,
 *           when `WC_Payment_Gateways` is constructed early in the request, long before
 *           Action Scheduler fires `woocommerce_scheduled_subscription_payment`. Gating the
 *           registration on that action meant the mock gateway was never present when a
 *           renewal actually ran, and PPEC renewals failed. Registration must stay
 *           unconditional; the settings-screen problem is solved separately by MockGateway
 *           presenting itself as a shell gateway (see MockGatewayTest).
 */
class SubscriptionsHandlerTest extends TestCase
{
	/**
	 * @var MockGateway|Mockery\MockInterface
	 */
	private $mock_gateway;

	private SubscriptionsHandler $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->mock_gateway = Mockery::mock(MockGateway::class);

		$this->sut = new SubscriptionsHandler(
			Mockery::mock(RenewalHandler::class),
			$this->mock_gateway,
			Mockery::mock(BillingAgreementTokenConverter::class),
			Mockery::mock(LoggerInterface::class)
		);
	}

	public function testRegistersTheGatewayUnconditionallyWithNoContextSetUp()
	{
		// GIVEN no ambient context whatsoever: no renewal in progress, no admin screen,
		// no endpoint - the same conditions under which a deleted context guard once
		// prevented registration and broke renewals.

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is registered anyway, because Action Scheduler triggers
		// renewals long after this filter has already run once.
		$this->assertSame($this->mock_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}

	public function testDoesNotOverwriteAnExistingGatewayEntry()
	{
		// GIVEN something else already registered a gateway under the PPEC id.
		$existing_gateway = new \stdClass();

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([PPECHelper::PPEC_GATEWAY_ID => $existing_gateway]);

		// THEN the existing entry is left untouched.
		$this->assertSame($existing_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}
}
