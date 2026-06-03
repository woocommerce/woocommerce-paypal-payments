<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests for the GetOrderTracking ability.
 *
 * The container-bound execute() path that calls
 * OrderTrackingEndpoint::list_tracking_information() is exercised by the
 * Phase V integration harness; here we cover the registration shape, the
 * input-validation contract, and the shipment serialization.
 */
class GetOrderTrackingTest extends TestCase
{
	public function test_get_name_uses_the_extension_namespace(): void
	{
		$this->assertSame(
			'woocommerce-paypal-payments/get-order-tracking',
			GetOrderTracking::get_name()
		);
	}

	public function test_registration_args_declare_required_wc_order_id(): void
	{
		$args = GetOrderTracking::get_registration_args();

		$this->assertSame(array( GetOrderTracking::class, 'execute' ), $args['execute_callback']);
		$this->assertSame(array( AbilitiesRegistrar::class, 'can_manage_woocommerce' ), $args['permission_callback']);
		$this->assertSame(AbilitiesRegistrar::CATEGORY_SLUG, $args['category']);

		$properties = $args['input_schema']['properties'];
		$this->assertArrayHasKey('wc_order_id', $properties);
		$this->assertSame('integer', $properties['wc_order_id']['type']);
		$this->assertSame(1, $properties['wc_order_id']['minimum']);
		$this->assertSame(array( 'wc_order_id' ), $args['input_schema']['required']);
		$this->assertFalse($args['input_schema']['additionalProperties']);

		$this->assertTrue($args['meta']['annotations']['readonly']);
		$this->assertFalse($args['meta']['annotations']['destructive']);
		$this->assertTrue($args['meta']['annotations']['idempotent']);
		$this->assertTrue($args['meta']['show_in_rest']);
		$this->assertTrue($args['meta']['mcp']['public']);
	}

	public function test_execute_returns_error_when_wc_order_id_is_missing(): void
	{
		$result = GetOrderTracking::execute(array());

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_missing_wc_order_id', $result->get_error_code());
	}

	public function test_execute_returns_error_when_wc_order_id_is_zero_or_negative(): void
	{
		$result = GetOrderTracking::execute(array( 'wc_order_id' => 0 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_invalid_input', $result->get_error_code());
	}

	public function test_execute_returns_not_found_when_wc_order_does_not_exist(): void
	{
		// A missing order short-circuits before the container is touched, so the
		// agent gets a structured not_found rather than the empty shipment list
		// the backing service returns for an unknown order id.
		when('wc_get_order')->justReturn(false);

		$result = GetOrderTracking::execute(array( 'wc_order_id' => 999 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_not_found', $result->get_error_code());
	}

	public function test_serialize_shipment_surfaces_each_interface_accessor_at_the_expected_wire_key(): void
	{
		// Build a shipment whose interface accessors return distinct sentinels
		// and whose to_array() echoes them through realistic wire keys. The
		// fixture is intentionally a faithful stand-in for the real Shipment
		// entity, so the assertions below pin the wire contract — any future
		// rename of a key or change of which accessor populates it makes the
		// test fail at the specific accessor->key edge rather than collapsing
		// to a "the mock returned what we set" tautology.
		$shipment = new class implements ShipmentInterface {
			public function capture_id(): string {
				return 'CAPTURE_ID_VALUE';
			}
			public function tracking_number(): string {
				return 'TRACKING_NUMBER_VALUE';
			}
			public function status(): string {
				return 'STATUS_VALUE';
			}
			public function carrier(): string {
				return 'CARRIER_VALUE';
			}
			public function carrier_name_other(): string {
				return 'CARRIER_NAME_OTHER_VALUE';
			}
			public function line_items(): array {
				return array( 42 );
			}
			public function render( array $allowed_statuses ): void {
			}
			public function to_array(): array {
				return array(
					'capture_id'         => $this->capture_id(),
					'tracking_number'    => $this->tracking_number(),
					'status'             => $this->status(),
					'carrier'            => $this->carrier(),
					'carrier_name_other' => $this->carrier_name_other(),
					'items'              => $this->line_items(),
				);
			}
		};

		$result = GetOrderTracking::serialize_shipment($shipment);

		// Pin each accessor -> wire-key edge. The fixture builds its array
		// from the interface methods, so a rename in either direction surfaces
		// here.
		$this->assertSame('CAPTURE_ID_VALUE', $result['capture_id'] ?? null, 'capture_id wire key must come from ::capture_id().');
		$this->assertSame('TRACKING_NUMBER_VALUE', $result['tracking_number'] ?? null, 'tracking_number wire key must come from ::tracking_number().');
		$this->assertSame('STATUS_VALUE', $result['status'] ?? null, 'status wire key must come from ::status().');
		$this->assertSame('CARRIER_VALUE', $result['carrier'] ?? null, 'carrier wire key must come from ::carrier().');
		$this->assertSame('CARRIER_NAME_OTHER_VALUE', $result['carrier_name_other'] ?? null, 'carrier_name_other wire key must come from ::carrier_name_other().');
		$this->assertSame(array( 42 ), $result['items'] ?? null, 'items wire key must come from ::line_items().');
	}

	public function test_serialize_shipment_passes_through_only_the_keys_to_array_emits(): void
	{
		// to_array() is the contract: if a future entity stops emitting a
		// field, the ability must not synthesize one. Use a deliberately
		// minimal to_array() and assert no unexpected keys appear.
		$shipment = new class implements ShipmentInterface {
			public function capture_id(): string {
				return 'CAP-X';
			}
			public function tracking_number(): string {
				return '';
			}
			public function status(): string {
				return '';
			}
			public function carrier(): string {
				return '';
			}
			public function carrier_name_other(): string {
				return '';
			}
			public function line_items(): array {
				return array();
			}
			public function render( array $allowed_statuses ): void {
			}
			public function to_array(): array {
				return array( 'capture_id' => $this->capture_id() );
			}
		};

		$result = GetOrderTracking::serialize_shipment($shipment);

		$this->assertSame(array( 'capture_id' ), array_keys($result));
		$this->assertSame('CAP-X', $result['capture_id']);
	}
}
