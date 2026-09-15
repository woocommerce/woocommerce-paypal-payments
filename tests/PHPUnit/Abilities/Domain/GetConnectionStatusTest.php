<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Mockery;
use WooCommerce\PayPalCommerce\Abilities\AbilityHandlers;
use WooCommerce\PayPalCommerce\Abilities\AbilityNames;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetConnectionStatusHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

/**
 * Unit tests for the GetConnectionStatus ability shell.
 *
 * PCP-6418: the shell carries no logic anymore — WC's loader calls
 * get_name()/get_registration_args() statically, so it can only ever expose
 * the registered shape and bind execute_callback/permission_callback through
 * the AbilityHandlers seam. All projection/redaction behaviour now lives in
 * GetConnectionStatusHandlerTest.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Domain\GetConnectionStatus
 */
class GetConnectionStatusTest extends TestCase
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
			'woocommerce-paypal-payments/get-connection-status',
			GetConnectionStatus::get_name(),
			'Ability name MUST use the plugin slug as the namespace prefix, never the reserved `woocommerce/` namespace.'
		);
	}

	/**
	 * GIVEN the AbilityNames constants are the single source of truth for the
	 *       registered slugs
	 * WHEN the shell's static get_name() is read
	 * THEN it returns exactly AbilityNames::GET_CONNECTION_STATUS, so the
	 * registered slug and the AbilityHandlers map key this class is looked up
	 * under can never drift apart.
	 */
	public function test_get_name_matches_the_shared_ability_names_constant(): void
	{
		$this->assertSame(AbilityNames::GET_CONNECTION_STATUS, GetConnectionStatus::get_name());
	}

	/**
	 * GIVEN a handler bound through the AbilityHandlers seam
	 * WHEN the shell's execute_callback is invoked
	 * THEN the call dispatches to that bound handler instance, with the input
	 * forwarded — the shell has no static execute() of its own anymore.
	 */
	public function test_registration_args_bind_execute_callback_to_the_di_handler_instance(): void
	{
		$handler = Mockery::mock(GetConnectionStatusHandler::class);
		$handler->shouldReceive('execute')
			->once()
			->with(array( 'foo' => 'bar' ))
			->andReturn(array( 'merchant' => array() ));
		AbilityHandlers::set(array( GetConnectionStatus::get_name() => $handler ));

		$args = GetConnectionStatus::get_registration_args();

		$this->assertIsCallable(
			$args['execute_callback'],
			'execute_callback must be invocable — the shell has no static execute() of its own anymore.'
		);
		$this->assertSame(
			array( 'merchant' => array() ),
			($args['execute_callback'])(array( 'foo' => 'bar' )),
			'Invoking execute_callback must dispatch to the bound handler instance and forward its input.'
		);
	}

	/**
	 * GIVEN nothing has been bound yet (e.g. registration ran before AbilitiesModule::run())
	 * WHEN the shell's execute_callback is invoked
	 * THEN it returns a WP_Error instead of fataling on an invalid callable —
	 * registration must never crash the site before wiring completes.
	 */
	public function test_execute_callback_is_still_callable_and_returns_an_error_when_nothing_is_bound(): void
	{
		$args = GetConnectionStatus::get_registration_args();

		$this->assertIsCallable($args['execute_callback']);

		$result = ($args['execute_callback'])();
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_not_initialized', $result->get_error_code());
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

		$args = GetConnectionStatus::get_registration_args();

		$this->assertSame($permission, $args['permission_callback']);
		$this->assertNotSame('__return_true', $args['permission_callback']);
		$this->assertFalse(
			($args['permission_callback'])(),
			'The shell must surface exactly the bound gate, including a denial, never bypass it.'
		);
	}

	public function test_registration_args_describe_a_zero_arg_read(): void
	{
		$args = GetConnectionStatus::get_registration_args();

		$this->assertSame(
			AbilityNames::CATEGORY_SLUG,
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
}
