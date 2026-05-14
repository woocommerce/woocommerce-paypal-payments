<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

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

	public function test_serialize_shipment_delegates_to_entity_to_array(): void
	{
		$shipment = new class implements ShipmentInterface {
			public function capture_id(): string {
				return 'CAP-1';
			}
			public function tracking_number(): string {
				return 'TRK-123';
			}
			public function status(): string {
				return 'SHIPPED';
			}
			public function carrier(): string {
				return 'UPS';
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
				return array(
					'capture_id'      => 'CAP-1',
					'tracking_number' => 'TRK-123',
					'status'          => 'SHIPPED',
					'carrier'         => 'UPS',
					'items'           => array(),
				);
			}
		};

		$result = GetOrderTracking::serialize_shipment($shipment);

		$this->assertSame(
			array(
				'capture_id'      => 'CAP-1',
				'tracking_number' => 'TRK-123',
				'status'          => 'SHIPPED',
				'carrier'         => 'UPS',
				'items'           => array(),
			),
			$result
		);
	}
}
