<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\Abilities_Registrar;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Unit tests for the GetLastWebhookEvent ability.
 *
 * Container-bound execute() paths are covered by the Phase V integration
 * harness; here we cover the registration shape and the projection method
 * that turns the storage payload into the agent-facing shape.
 */
class GetLastWebhookEventTest extends TestCase
{
	public function test_get_name_uses_the_extension_namespace(): void
	{
		$this->assertSame(
			'woocommerce-paypal-payments/get-last-webhook-event',
			GetLastWebhookEvent::get_name()
		);
	}

	public function test_registration_args_are_zero_arg_read_only(): void
	{
		$args = GetLastWebhookEvent::get_registration_args();

		$this->assertSame(array( GetLastWebhookEvent::class, 'execute' ), $args['execute_callback']);
		$this->assertSame(array( Abilities_Registrar::class, 'can_manage_woocommerce' ), $args['permission_callback']);
		$this->assertSame(Abilities_Registrar::CATEGORY_SLUG, $args['category']);

		$this->assertSame(array(), $args['input_schema']['properties']);
		$this->assertFalse($args['input_schema']['additionalProperties']);

		$this->assertTrue($args['meta']['annotations']['readonly']);
		$this->assertFalse($args['meta']['annotations']['destructive']);
		$this->assertTrue($args['meta']['annotations']['idempotent']);
		$this->assertTrue($args['meta']['show_in_rest']);
		$this->assertTrue($args['meta']['mcp']['public']);
	}

	public function test_project_appends_iso_timestamp_to_storage_payload(): void
	{
		$payload = array(
			'id'            => 'WH-EVENT-1234',
			'received_time' => 1747094400, // 2025-05-13T00:00:00Z
		);

		$result = GetLastWebhookEvent::project($payload);

		$this->assertTrue($result['received']);
		$this->assertSame('WH-EVENT-1234', $result['id']);
		$this->assertSame(1747094400, $result['received_time']);
		$this->assertSame('2025-05-13T00:00:00+00:00', $result['received_iso']);
	}

	public function test_project_returns_null_iso_when_received_time_missing(): void
	{
		$result = GetLastWebhookEvent::project(array( 'id' => 'WH-EVENT-NO-TIME' ));

		$this->assertTrue($result['received']);
		$this->assertSame(0, $result['received_time']);
		$this->assertNull($result['received_iso']);
	}
}
