<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests for the GetPaypalOrder ability.
 *
 * The container-bound + remote-PayPal-call paths in execute() are covered
 * by the Phase V integration harness; here we cover the registration
 * shape, the input-identifier validation contract, the
 * paypal_order_id format guard, the wc_order_not_found branch, and the
 * payer-PII redaction projection.
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
		$this->assertSame(array( AbilitiesRegistrar::class, 'can_manage_woocommerce' ), $args['permission_callback']);
		$this->assertSame(AbilitiesRegistrar::CATEGORY_SLUG, $args['category']);

		$properties = $args['input_schema']['properties'];
		$this->assertArrayHasKey('paypal_order_id', $properties);
		$this->assertSame('string', $properties['paypal_order_id']['type']);
		$this->assertArrayHasKey('wc_order_id', $properties);
		$this->assertSame('integer', $properties['wc_order_id']['type']);
		$this->assertSame(1, $properties['wc_order_id']['minimum']);
		$this->assertArrayHasKey('include_payer_pii', $properties);
		$this->assertSame('boolean', $properties['include_payer_pii']['type']);
		$this->assertFalse($properties['include_payer_pii']['default']);

		// Neither identifier field is in the JSON-schema `required` list because
		// the "exactly one of" constraint is enforced in the execute callback.
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

	public function test_execute_returns_invalid_input_when_paypal_order_id_has_disallowed_chars(): void
	{
		// Path-traversal-style payload: alters the v2/checkout/orders URL path
		// when concatenated by OrderEndpoint::order(). The format guard rejects
		// it before reaching the endpoint.
		$result = GetPaypalOrder::execute(array( 'paypal_order_id' => 'ORDERID/../refunds' ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_invalid_input', $result->get_error_code());
	}

	public function test_execute_returns_invalid_input_when_paypal_order_id_is_lowercase(): void
	{
		$result = GetPaypalOrder::execute(array( 'paypal_order_id' => 'lowercase' ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_invalid_input', $result->get_error_code());
	}

	public function test_execute_returns_not_found_when_wc_order_does_not_exist(): void
	{
		when('wc_get_order')->justReturn(false);

		$result = GetPaypalOrder::execute(array( 'wc_order_id' => 999 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_not_found', $result->get_error_code());
	}

	public function test_project_order_strips_payer_block_by_default(): void
	{
		$payload = array(
			'id'             => '8XR43025NW123456A',
			'status'         => 'COMPLETED',
			'intent'         => 'CAPTURE',
			'payer'          => array(
				'email_address' => 'payer@example.test',
				'name'          => array( 'given_name' => 'Test' ),
				'address'       => array( 'country_code' => 'US' ),
				'birth_date'    => '1990-01-01',
			),
			'purchase_units' => array(
				array(
					'reference_id' => 'default',
					'amount'       => array( 'currency_code' => 'USD', 'value' => '10.00' ),
				),
			),
		);

		$result = GetPaypalOrder::project_order($payload, false);

		$this->assertArrayNotHasKey('payer', $result, 'payer block must be stripped by default.');
		$this->assertSame('8XR43025NW123456A', $result['id']);
		$this->assertSame('COMPLETED', $result['status']);
		$this->assertSame('CAPTURE', $result['intent']);
	}

	public function test_project_order_strips_per_purchase_unit_shipping_address_by_default(): void
	{
		$payload = array(
			'id'             => 'ORDERID',
			'status'         => 'CREATED',
			'purchase_units' => array(
				array(
					'reference_id' => 'default',
					'shipping'     => array(
						'name'    => array( 'full_name' => 'Test Customer' ),
						'address' => array( 'country_code' => 'US', 'postal_code' => '94103' ),
					),
					'amount'       => array( 'currency_code' => 'USD', 'value' => '10.00' ),
				),
			),
		);

		$result = GetPaypalOrder::project_order($payload, false);

		$this->assertArrayNotHasKey('shipping', $result['purchase_units'][0], 'shipping must be stripped from each purchase_unit by default.');
		$this->assertArrayHasKey('amount', $result['purchase_units'][0], 'non-PII purchase_unit fields must survive.');
	}

	public function test_project_order_passes_payer_through_when_include_payer_pii_is_true(): void
	{
		$payload = array(
			'id'    => 'ORDERID',
			'payer' => array( 'email_address' => 'payer@example.test' ),
		);

		$result = GetPaypalOrder::project_order($payload, true);

		$this->assertSame($payload, $result, 'project_order must be a passthrough when the opt-in flag is true.');
	}
}
