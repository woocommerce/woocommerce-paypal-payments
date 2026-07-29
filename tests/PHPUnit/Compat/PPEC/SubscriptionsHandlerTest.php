<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\RenewalHandler;
use function Brain\Monkey\Functions\when;

/**
 * @scenario Regression test for PCP-6709. `add_mock_ppec_gateway()` used to inject the
 *           "PayPal (Legacy)" mock gateway into WooCommerce's full gateway list
 *           unconditionally, which leaked it into WooCommerce > Settings > Payments for
 *           any merchant with legacy PPEC subscriptions. `should_mock_ppec_gateway()`
 *           scopes that injection to the legitimate contexts (a renewal in progress, My
 *           Account > Subscriptions, editing an order/subscription tied to PPEC, ...) so it
 *           no longer shows up on the general payment settings screen.
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

		// Reliably false in the unit test environment: neither the WC_Subscriptions nor
		// the WooCommerce-core OrderUtil class is loaded here, so both class_exists()
		// checks in should_mock_ppec_gateway() take their "not available" branch without
		// needing to be mocked.
		when('is_admin')->justReturn(false);
		when('is_wc_endpoint_url')->justReturn(false);
	}

	public function tearDown(): void
	{
		unset($_GET['id'], $_GET['post'], $_GET['post_type'], $_POST['post_ID'], $_POST['post_type']);

		parent::tearDown();
	}

	/**
	 * Stubs doing_action() to report only the given actions as currently in progress, so
	 * each test states explicitly which of them (if any) is active instead of relying on
	 * a single argument-agnostic default that would silently satisfy every call.
	 *
	 * Uses a single alias() callback rather than one expect()->with($action) per action:
	 * two separate with()-constrained expectations on the same function name don't
	 * dispatch on the actual call argument in this test environment (both calls resolve
	 * to the first-registered expectation's return value), so a single argument-aware
	 * callback is used instead.
	 *
	 * @param string[] $activeActions Action names that should report as currently doing.
	 */
	private function stubDoingAction(array $activeActions = []): void
	{
		when('doing_action')->alias(
			static fn (string $action): bool => in_array($action, $activeActions, true)
		);
	}

	public function testDoesNotAddGatewayWhenNoLegitimateContextMatches()
	{
		// GIVEN a plain page load with no renewal, subscription, or order-edit context
		// active (e.g. viewing WooCommerce > Settings > Payments).
		$this->stubDoingAction();

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway must not be added.
		$this->assertArrayNotHasKey(PPECHelper::PPEC_GATEWAY_ID, $gateways);
	}

	public function testAddsGatewayWhileProcessingARenewal()
	{
		// GIVEN a subscription renewal is being processed.
		$this->stubDoingAction(['woocommerce_scheduled_subscription_payment']);

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is added so the renewal can be attributed to PPEC.
		$this->assertSame($this->mock_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}

	public function testAddsGatewayOnMyAccountSubscriptionsEndpoint()
	{
		// GIVEN the buyer is viewing My Account > Subscriptions.
		$this->stubDoingAction();
		when('is_wc_endpoint_url')->justReturn(true);

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is added so PPEC subscriptions display correctly there.
		$this->assertSame($this->mock_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}

	public function testDoesNotOverwriteAnExistingGatewayEntry()
	{
		// GIVEN something else already registered a gateway under the PPEC id, and a
		// legitimate context is also active.
		$this->stubDoingAction(['woocommerce_scheduled_subscription_payment']);
		$existing_gateway = new \stdClass();

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([PPECHelper::PPEC_GATEWAY_ID => $existing_gateway]);

		// THEN the existing entry is left untouched.
		$this->assertSame($existing_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}

	public function testAddsGatewayWhenAdminEditsAnOrderTiedToPpec()
	{
		// GIVEN an admin is editing an order paid for via the legacy PPEC gateway.
		$this->stubDoingAction();
		when('is_admin')->justReturn(true);
		$_GET['id'] = '123';
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_payment_method')->andReturn(PPECHelper::PPEC_GATEWAY_ID);
		when('wc_get_order')->justReturn($order);

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is added so the order displays its payment method correctly.
		$this->assertSame($this->mock_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}

	public function testDoesNotAddGatewayWhenAdminEditsAnOrderWithADifferentPaymentMethod()
	{
		// GIVEN an admin is editing an order paid for via a different gateway.
		$this->stubDoingAction();
		when('is_admin')->justReturn(true);
		$_GET['id'] = '123';
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_payment_method')->andReturn('ppcp-gateway');
		when('wc_get_order')->justReturn($order);

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is not added.
		$this->assertArrayNotHasKey(PPECHelper::PPEC_GATEWAY_ID, $gateways);
	}

	public function testAddsGatewayWhileSavingSubscriptionMetadataInAdmin()
	{
		// GIVEN an admin is saving an order/subscription's metadata.
		when('is_admin')->justReturn(true);
		$this->stubDoingAction(['woocommerce_process_shop_order_meta']);

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is added.
		$this->assertSame($this->mock_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}

	public function testAddsGatewayOnTheLegacyWcSubscriptionsListScreen()
	{
		// GIVEN an admin is viewing the legacy WC > Subscriptions list screen.
		$this->stubDoingAction();
		when('is_admin')->justReturn(true);
		$_GET['post_type'] = 'shop_subscription';

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is added.
		$this->assertSame($this->mock_gateway, $gateways[PPECHelper::PPEC_GATEWAY_ID]);
	}

	public function testDoesNotAddGatewayInAdminWhenNothingElseMatches()
	{
		// GIVEN a plain admin screen with no order id, no relevant action, and no
		// subscriptions-list post_type (e.g. an unrelated settings page).
		$this->stubDoingAction();
		when('is_admin')->justReturn(true);

		// WHEN the gateway list is filtered.
		$gateways = $this->sut->add_mock_ppec_gateway([]);

		// THEN the mock gateway is not added.
		$this->assertArrayNotHasKey(PPECHelper::PPEC_GATEWAY_ID, $gateways);
	}
}
