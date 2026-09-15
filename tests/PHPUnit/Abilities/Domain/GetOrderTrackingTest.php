<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Mockery;
use WooCommerce\PayPalCommerce\Abilities\AbilityHandlers;
use WooCommerce\PayPalCommerce\Abilities\AbilityNames;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetOrderTrackingHandler;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Unit tests for the GetOrderTracking ability shell.
 *
 * PCP-6418: the shell carries no logic anymore; execute()/input-validation/
 * shipment-serialization moved to GetOrderTrackingHandlerTest. Here we cover
 * only the registered shape and the AbilityHandlers binding seam.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Domain\GetOrderTracking
 */
class GetOrderTrackingTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		AbilityHandlers::set(array());
	}

	public function tearDown(): void
	{
		AbilityHandlers::set(array());

		parent::tearDown();
	}

	public function test_get_name_uses_the_extension_namespace(): void
	{
		$this->assertSame(
			'woocommerce-paypal-payments/get-order-tracking',
			GetOrderTracking::get_name()
		);
	}

	/**
	 * GIVEN the AbilityNames constants are the single source of truth for the
	 *       registered slugs
	 * WHEN the shell's static get_name() is read
	 * THEN it returns exactly AbilityNames::GET_ORDER_TRACKING, so the
	 * registered slug and the AbilityHandlers map key this class is looked up
	 * under can never drift apart.
	 */
	public function test_get_name_matches_the_shared_ability_names_constant(): void
	{
		$this->assertSame(AbilityNames::GET_ORDER_TRACKING, GetOrderTracking::get_name());
	}

	/**
	 * GIVEN a handler bound through the AbilityHandlers seam
	 * WHEN the shell's execute_callback is invoked
	 * THEN the call dispatches to that bound handler instance, with the input
	 * forwarded.
	 */
	public function test_registration_args_bind_execute_callback_to_the_di_handler_instance(): void
	{
		$handler = Mockery::mock(GetOrderTrackingHandler::class);
		$handler->shouldReceive('execute')
			->once()
			->with(array( 'wc_order_id' => 42 ))
			->andReturn(array( 'shipments' => array() ));
		AbilityHandlers::set(array( GetOrderTracking::get_name() => $handler ));

		$args = GetOrderTracking::get_registration_args();

		$this->assertIsCallable(
			$args['execute_callback'],
			'execute_callback must be invocable — the shell has no static execute() of its own anymore.'
		);
		$this->assertSame(
			array( 'shipments' => array() ),
			($args['execute_callback'])(array( 'wc_order_id' => 42 )),
			'Invoking execute_callback must dispatch to the bound handler instance and forward its input.'
		);
	}

	/**
	 * GIVEN a permission callable bound through the AbilityHandlers seam
	 * WHEN the shell's registration args are read
	 * THEN permission_callback is exactly that bound callable — never a
	 * hardcoded `array(AbilitiesRegistrar::class, 'can_manage_woocommerce')`
	 * or an always-true shortcut — and it denies when the bound gate denies.
	 */
	public function test_registration_args_bind_permission_callback_to_the_bound_gate(): void
	{
		$permission = static function (): bool {
			return false;
		};
		AbilityHandlers::set(array(), $permission);

		$args = GetOrderTracking::get_registration_args();

		$this->assertSame($permission, $args['permission_callback']);
		$this->assertNotSame('__return_true', $args['permission_callback']);
		$this->assertFalse(
			($args['permission_callback'])(),
			'The shell must surface exactly the bound gate, including a denial, never bypass it.'
		);
	}

	public function test_registration_args_declare_required_wc_order_id(): void
	{
		$args = GetOrderTracking::get_registration_args();

		$this->assertSame(AbilityNames::CATEGORY_SLUG, $args['category']);

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
}
