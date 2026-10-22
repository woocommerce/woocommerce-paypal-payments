<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Mockery;
use WooCommerce\PayPalCommerce\Abilities\AbilityHandlers;
use WooCommerce\PayPalCommerce\Abilities\AbilityNames;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetPaymentMethodsHandler;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Unit tests for the GetPaymentMethods ability shell.
 *
 * PCP-6418: the shell carries no logic anymore; execute()/envelope handling
 * moved to GetPaymentMethodsHandlerTest. Here we cover only the registered
 * shape and the AbilityHandlers binding seam.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Domain\GetPaymentMethods
 */
class GetPaymentMethodsTest extends TestCase
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
			'woocommerce-paypal-payments/get-payment-methods',
			GetPaymentMethods::get_name()
		);
	}

	/**
	 * GIVEN the AbilityNames constants are the single source of truth for the
	 *       registered slugs
	 * WHEN the shell's static get_name() is read
	 * THEN it returns exactly AbilityNames::GET_PAYMENT_METHODS, so the
	 * registered slug and the AbilityHandlers map key this class is looked up
	 * under can never drift apart.
	 */
	public function test_get_name_matches_the_shared_ability_names_constant(): void
	{
		$this->assertSame(AbilityNames::GET_PAYMENT_METHODS, GetPaymentMethods::get_name());
	}

	/**
	 * GIVEN a handler bound through the AbilityHandlers seam
	 * WHEN the shell's execute_callback is invoked
	 * THEN the call dispatches to that bound handler instance, with the input
	 * forwarded.
	 */
	public function test_registration_args_bind_execute_callback_to_the_di_handler_instance(): void
	{
		$handler = Mockery::mock(GetPaymentMethodsHandler::class);
		$handler->shouldReceive('execute')
			->once()
			->with(array( 'foo' => 'bar' ))
			->andReturn(array( 'gateways' => array() ));
		AbilityHandlers::set(array( GetPaymentMethods::get_name() => $handler ));

		$args = GetPaymentMethods::get_registration_args();

		$this->assertIsCallable(
			$args['execute_callback'],
			'execute_callback must be invocable — the shell has no static execute() of its own anymore.'
		);
		$this->assertSame(
			array( 'gateways' => array() ),
			($args['execute_callback'])(array( 'foo' => 'bar' )),
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

		$args = GetPaymentMethods::get_registration_args();

		$this->assertSame($permission, $args['permission_callback']);
		$this->assertNotSame('__return_true', $args['permission_callback']);
		$this->assertFalse(
			($args['permission_callback'])(),
			'The shell must surface exactly the bound gate, including a denial, never bypass it.'
		);
	}

	public function test_registration_args_are_zero_arg_read_only(): void
	{
		$args = GetPaymentMethods::get_registration_args();

		$this->assertSame(AbilityNames::CATEGORY_SLUG, $args['category']);

		$this->assertSame(array(), $args['input_schema']['properties']);
		$this->assertFalse($args['input_schema']['additionalProperties']);

		$this->assertTrue($args['meta']['annotations']['readonly']);
		$this->assertFalse($args['meta']['annotations']['destructive']);
		$this->assertTrue($args['meta']['annotations']['idempotent']);
		$this->assertTrue($args['meta']['show_in_rest']);
		$this->assertTrue($args['meta']['mcp']['public']);
	}
}
