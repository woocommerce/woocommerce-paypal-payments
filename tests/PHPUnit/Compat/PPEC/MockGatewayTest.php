<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @scenario `SubscriptionsHandler::add_mock_ppec_gateway()` registers this gateway
 *           unconditionally (see SubscriptionsHandlerTest), so keeping it off
 *           WooCommerce > Settings > Payments relies entirely on MockGateway presenting
 *           itself as a "shell" gateway. WooCommerce's `PaymentsProviders::is_shell_payment_gateway()`
 *           only recognises a gateway as a shell when both `method_title` and
 *           `method_description` are empty; setting either one, even accidentally, would
 *           silently put the gateway back on the settings screen.
 */
class MockGatewayTest extends TestCase
{
	public function testExposesAnEmptyMethodTitleAndMethodDescriptionToStayOffTheSettingsScreen()
	{
		// GIVEN the mock gateway used to disguise PPEC subscriptions.
		$gateway = new MockGateway('PayPal (Legacy)');

		// WHEN its public state is inspected the way WooCommerce core does to decide
		// whether a gateway is a shell.
		$exposed = get_object_vars($gateway);

		// THEN both method_title and method_description are empty, so the gateway is
		// recognised as a shell and excluded from the Payments settings screen.
		$this->assertSame('', $exposed['method_title'] ?? '');
		$this->assertSame('', $exposed['method_description'] ?? '');
	}

	public function testStillExposesTheGivenTitleForOrderAndSubscriptionScreens()
	{
		// GIVEN a title meant to label the gateway on order and subscription screens.
		$gateway = new MockGateway('PayPal (Legacy)');

		// WHEN its title is read.
		// THEN it matches what was passed in, unlike method_title/method_description.
		$this->assertSame('PayPal (Legacy)', $gateway->title);
	}
}
