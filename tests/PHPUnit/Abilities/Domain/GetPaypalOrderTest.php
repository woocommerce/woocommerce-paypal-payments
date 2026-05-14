<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\Abilities_Registrar;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

/**
 * Unit tests for the GetPaypalOrder ability.
 *
 * The container-bound + remote-PayPal-call paths in execute() are covered
 * by the Phase V integration harness; here we cover the registration
 * shape and the input-identifier validation contract.
 */
class GetPaypalOrderTest extends TestCase
{
	public function test_get_name_uses_the_extension_namespace(): void
	{
		$this->assertSame(
			'woocommerce-paypal-payments/get-paypal-order',
			GetPaypalOrder::get_name()
		);
	}

	public function test_registration_args_accept_either_identifier(): void
	{
		$args = GetPaypalOrder::get_registration_args();

		$this->assertSame(array( GetPaypalOrder::class, 'execute' ), $args['execute_callback']);
		$this->assertSame(array( Abilities_Registrar::class, 'can_manage_woocommerce' ), $args['permission_callback']);
		$this->assertSame(Abilities_Registrar::CATEGORY_SLUG, $args['category']);

		$properties = $args['input_schema']['properties'];
		$this->assertArrayHasKey('paypal_order_id', $properties);
		$this->assertSame('string', $properties['paypal_order_id']['type']);
		$this->assertArrayHasKey('wc_order_id', $properties);
		$this->assertSame('integer', $properties['wc_order_id']['type']);
		$this->assertSame(1, $properties['wc_order_id']['minimum']);

		// Neither field is in the JSON-schema `required` list because the
		// "exactly one of" constraint is enforced in the execute callback.
		$this->assertArrayNotHasKey('required', $args['input_schema']);

		$this->assertFalse($args['input_schema']['additionalProperties']);

		$this->assertTrue($args['meta']['annotations']['readonly']);
		$this->assertFalse($args['meta']['annotations']['destructive']);
		$this->assertTrue($args['meta']['annotations']['idempotent']);
		$this->assertTrue($args['meta']['show_in_rest']);
		$this->assertTrue($args['meta']['mcp']['public']);
	}

	public function test_execute_returns_error_when_no_identifier_supplied(): void
	{
		$result = GetPaypalOrder::execute(array());

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_missing_identifier', $result->get_error_code());
	}

	public function test_execute_returns_error_when_wc_order_id_is_zero_and_no_paypal_id(): void
	{
		$result = GetPaypalOrder::execute(array( 'wc_order_id' => 0 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_missing_identifier', $result->get_error_code());
	}
}
