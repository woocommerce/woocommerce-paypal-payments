<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use Mockery;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetConnectionStatusHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

/**
 * Unit tests for the single DI-fed binding seam between the static
 * AbilityDefinition shells and the container-resolved handlers.
 *
 * PCP-6418: WooCommerce's AbilitiesLoader calls get_registration_args()
 * statically, so the shells need one module-wide way back to their
 * handlers. AbilityHandlers is that seam — a production API called from
 * AbilitiesModule::run(), NOT a test-only reset helper: set() replaces the
 * map wholesale, which is also how tests isolate themselves.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilityHandlers
 */
class AbilityHandlersTest extends TestCase
{
	const ABILITY = 'woocommerce-paypal-payments/get-connection-status';

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

	/**
	 * @scenario A bound handler is reached through the returned callback.
	 *
	 * Given a handler instance bound to an ability name
	 * When the shell's execute callback is invoked
	 * Then the call lands on that instance's execute(), with the input forwarded.
	 */
	public function test_callback_dispatches_to_the_bound_handler_instance(): void
	{
		// Arrange.
		$input   = array( 'wc_order_id' => 42 );
		$handler = Mockery::mock(GetConnectionStatusHandler::class);
		$handler->shouldReceive('execute')->once()->with($input)->andReturn(array( 'merchant' => array() ));
		AbilityHandlers::set(array( self::ABILITY => $handler ));

		// When.
		$callback = AbilityHandlers::callback(self::ABILITY);

		// Then.
		$this->assertIsCallable($callback);
		$this->assertSame(array( 'merchant' => array() ), $callback($input));
	}

	/**
	 * @scenario The registered callback keeps the arity of the static execute()
	 *           it replaced.
	 *
	 * Given a handler bound to an ability name
	 * When the callback is invoked with no argument, as a zero-arg ability is
	 * Then execute() still runs, receiving null.
	 */
	public function test_callback_is_invocable_with_no_argument(): void
	{
		// Arrange.
		$handler = Mockery::mock(GetConnectionStatusHandler::class);
		$handler->shouldReceive('execute')->once()->with(null)->andReturn(array());
		AbilityHandlers::set(array( self::ABILITY => $handler ));

		// When / Then.
		$callback = AbilityHandlers::callback(self::ABILITY);
		$this->assertSame(array(), $callback());
	}

	/**
	 * @scenario Building the registration args must not drain the container.
	 *
	 * Given a handler bound as a factory closure
	 * When callback() is asked for its execute callback — which the shells do
	 *      from get_registration_args(), inside Woo's loader
	 * Then the factory has still not run.
	 *
	 * PCP-6418 review: resolving here built all four backing endpoints on every
	 * request that merely registered the surface, including cron and WP-CLI,
	 * where api.endpoint.order.cached reaches WC()->session via
	 * SessionHandler::bn_code().
	 */
	public function test_callback_does_not_resolve_the_factory_until_the_returned_callable_is_invoked(): void
	{
		// Arrange.
		$handler = Mockery::mock(GetConnectionStatusHandler::class);
		$handler->shouldReceive('execute')->andReturn(array());
		$calls = 0;

		AbilityHandlers::set(
			array(
				self::ABILITY => static function () use ($handler, &$calls) {
					$calls++;

					return $handler;
				},
			)
		);

		// When.
		$callback = AbilityHandlers::callback(self::ABILITY);

		// Then: registration-time is too early.
		$this->assertSame(0, $calls, 'Building the registration args must not resolve the handler.');

		// When: the ability is actually executed.
		$callback(array());

		// Then.
		$this->assertSame(1, $calls, 'The factory must run when the ability executes.');
	}

	/**
	 * @scenario A resolved handler is reused for the rest of the request.
	 *
	 * Given a handler bound as a factory closure
	 * When its callback is invoked repeatedly, and a second callback is built
	 * Then the factory runs exactly once.
	 */
	public function test_the_resolved_handler_is_memoized_across_invocations(): void
	{
		// Arrange.
		$handler = Mockery::mock(GetConnectionStatusHandler::class);
		$handler->shouldReceive('execute')->times(3)->andReturn(array());
		$calls = 0;

		AbilityHandlers::set(
			array(
				self::ABILITY => static function () use ($handler, &$calls) {
					$calls++;

					return $handler;
				},
			)
		);

		// When.
		$first = AbilityHandlers::callback(self::ABILITY);
		$first(array());
		$first(array());
		AbilityHandlers::callback(self::ABILITY)(array());

		// Then.
		$this->assertSame(1, $calls, 'The factory must be resolved once and memoized.');
	}

	/**
	 * @scenario A container failure degrades instead of breaking registration.
	 *
	 * Given a factory that throws, as the container does when a backing service
	 *       cannot be built
	 * When the ability is executed
	 * Then a WP_Error is returned and nothing propagates.
	 *
	 * PCP-6418 review: the removed resolve_service() caught Throwable and
	 * returned this WP_Error. Without it, a container exception thrown while
	 * building the registration args escapes into `wp_abilities_api_init` and
	 * takes WooCommerce Core's own ability registration down with it.
	 */
	public function test_callback_returns_a_service_unavailable_wp_error_when_the_factory_throws(): void
	{
		// Arrange.
		AbilityHandlers::set(
			array(
				self::ABILITY => static function (): void {
					throw new \RuntimeException('settings.rest.common could not be built');
				},
			)
		);

		// When: registration itself must survive.
		$callback = AbilityHandlers::callback(self::ABILITY);
		$this->assertIsCallable($callback);

		$result = $callback(array());

		// Then.
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_service_unavailable', $result->get_error_code());
	}

	/**
	 * @scenario Registration never fatals when a handler was not bound.
	 *
	 * Given no handler bound for an ability
	 * When its callback is built and invoked
	 * Then a WP_Error is returned rather than a fatal on an invalid callable.
	 */
	public function test_callback_for_an_unbound_ability_returns_a_wp_error_instead_of_fataling(): void
	{
		// When.
		$callback = AbilityHandlers::callback(self::ABILITY);

		// Then.
		$this->assertIsCallable($callback);

		$result = $callback(array());
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_not_initialized', $result->get_error_code());
	}

	/**
	 * @scenario A bound object that cannot execute never yields an invalid callable.
	 *
	 * Given an object bound to an ability name that has no execute() method
	 * When the shell asks for its execute callback
	 * Then callback() still returns something callable, and invoking it yields
	 *      a WP_Error instead of fataling on an invalid callable during
	 *      registration.
	 */
	public function test_callback_for_a_non_executable_bound_object_returns_a_wp_error_instead_of_an_invalid_callable(): void
	{
		// Arrange.
		AbilityHandlers::set(array( self::ABILITY => new \stdClass() ));

		// When.
		$callback = AbilityHandlers::callback(self::ABILITY);

		// Then.
		$this->assertIsCallable($callback);

		$result = $callback(array());
		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_not_initialized', $result->get_error_code());
	}

	/**
	 * @scenario The permission gate is bound through the same seam.
	 *
	 * Given a permission callable bound alongside the handlers
	 * When a shell asks for the permission callback
	 * Then it receives that callable.
	 */
	public function test_permission_callback_returns_the_bound_callable(): void
	{
		// Arrange.
		$permission = static function (): bool {
			return true;
		};
		AbilityHandlers::set(array(), $permission);

		// When.
		$callback = AbilityHandlers::permission_callback();

		// Then.
		$this->assertTrue($callback());
	}

	/**
	 * @scenario An unbound permission gate denies.
	 *
	 * Given no permission callable was bound
	 * When the permission callback is invoked
	 * Then it denies — never __return_true.
	 */
	public function test_permission_callback_denies_when_nothing_was_bound(): void
	{
		// When.
		$callback = AbilityHandlers::permission_callback();

		// Then.
		$this->assertIsCallable($callback);
		$this->assertFalse($callback(), 'An unwired permission gate must fail closed.');
	}
}
