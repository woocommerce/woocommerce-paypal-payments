<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

/**
 * Unit tests for the GetConnectionStatus reference ability.
 *
 * Covers the registration shape and the projection method's
 * secret-redaction contract. The full delegate→REST→envelope path is
 * exercised by the Phase V integration harness against a real WC 10.9
 * install.
 */
class GetConnectionStatusTest extends TestCase
{
	public function test_get_name_uses_the_extension_namespace(): void
	{
		$this->assertSame(
			'woocommerce-paypal-payments/get-connection-status',
			GetConnectionStatus::get_name(),
			'Ability name MUST use the plugin slug as the namespace prefix, never the reserved `woocommerce/` namespace.'
		);
	}

	public function test_registration_args_describe_a_zero_arg_read(): void
	{
		$args = GetConnectionStatus::get_registration_args();

		$this->assertSame(
			array( GetConnectionStatus::class, 'execute' ),
			$args['execute_callback'],
			'execute_callback must point at the Domain class itself.'
		);
		$this->assertSame(
			array( AbilitiesRegistrar::class, 'can_manage_woocommerce' ),
			$args['permission_callback'],
			'permission_callback must point at the shared registrar helper, never at __return_true.'
		);
		$this->assertSame(
			AbilitiesRegistrar::CATEGORY_SLUG,
			$args['category'],
			'Category must be the shared `woocommerce` slug owned by Woo Core.'
		);

		$this->assertSame(
			array(),
			$args['input_schema']['properties'],
			'Reference ability is zero-arg — input_schema declares no properties.'
		);
		$this->assertFalse(
			$args['input_schema']['additionalProperties'],
			'additionalProperties must be false to reject stray inputs deterministically.'
		);
	}

	public function test_registration_args_assert_all_three_annotations(): void
	{
		$args = GetConnectionStatus::get_registration_args();
		$annotations = $args['meta']['annotations'];

		$this->assertTrue($annotations['readonly'], 'get-connection-status is read-only.');
		$this->assertFalse($annotations['destructive'], 'get-connection-status has no side effects.');
		$this->assertTrue($annotations['idempotent'], 'Repeated calls return the same payload (modulo backend changes).');
	}

	public function test_registration_args_opt_into_both_projections(): void
	{
		$args = GetConnectionStatus::get_registration_args();

		$this->assertTrue(
			$args['meta']['show_in_rest'],
			'show_in_rest must be true so the REST bridge picks the ability up.'
		);
		$this->assertTrue(
			$args['meta']['mcp']['public'],
			'mcp.public must be true — the whole point is agent visibility.'
		);
	}

	public function test_project_merchant_payload_strips_clientId_and_clientSecret(): void
	{
		$payload = array(
			'success'  => true,
			'data'     => array(),
			'merchant' => array(
				'isConnected'       => true,
				'isSandbox'         => false,
				'id'                => 'M3RCH4NT_ID',
				'email'             => 'merchant@example.test',
				'sellerType'        => 'BUSINESS',
				'clientId'          => 'PUBLIC_LOOKING_BUT_SECRET_ID',
				'clientSecret'      => 'CR3D3NT14L',
				'isSendOnlyCountry' => false,
			),
		);

		$result = GetConnectionStatus::project_merchant_payload($payload);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('clientId', $result['merchant'], 'API client id leaks the OAuth identity — must be stripped.');
		$this->assertArrayNotHasKey('clientSecret', $result['merchant'], 'API client secret is a credential — must be stripped.');

		$this->assertSame('M3RCH4NT_ID', $result['merchant']['id']);
		$this->assertSame('merchant@example.test', $result['merchant']['email']);
		$this->assertTrue($result['merchant']['isConnected']);
		$this->assertFalse($result['merchant']['isSandbox']);
	}

	public function test_project_merchant_payload_passes_features_through_when_present(): void
	{
		$payload = array(
			'success'  => true,
			'data'     => array(),
			'merchant' => array( 'isConnected' => true ),
			'features' => array( 'fastlane', 'pay_later' ),
		);

		$result = GetConnectionStatus::project_merchant_payload($payload);

		$this->assertIsArray($result);
		$this->assertSame(array( 'fastlane', 'pay_later' ), $result['features']);
	}

	public function test_project_merchant_payload_omits_features_when_absent(): void
	{
		$payload = array(
			'success'  => true,
			'data'     => array(),
			'merchant' => array( 'isConnected' => false ),
		);

		$result = GetConnectionStatus::project_merchant_payload($payload);

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('features', $result);
	}

	public function test_project_merchant_payload_returns_wp_error_on_envelope_failure(): void
	{
		$payload = array(
			'success' => false,
			'message' => 'Some upstream failure with PayPal information_link https://api.paypal.com/v1/notifications/123.',
		);

		$result = GetConnectionStatus::project_merchant_payload($payload);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_endpoint_error', $result->get_error_code());

		// The raw upstream message is REDACTED before reaching the agent —
		// the original is written to error_log, the agent sees a generic
		// pointer to the server log instead. Lock the redaction in by
		// asserting the leak vector text does NOT appear in the message.
		$this->assertStringNotContainsString('information_link', $result->get_error_message());
		$this->assertStringNotContainsString('paypal.com', $result->get_error_message());
		$this->assertStringContainsString('see server log', $result->get_error_message());
	}

	public function test_project_merchant_payload_handles_missing_merchant_subobject(): void
	{
		// Defensive: the endpoint should always return a merchant array, but
		// the projection must not blow up if the shape ever drifts.
		$payload = array(
			'success' => true,
			'data'    => array(),
		);

		$result = GetConnectionStatus::project_merchant_payload($payload);

		$this->assertIsArray($result);
		$this->assertSame(array(), $result['merchant']);
	}
}
