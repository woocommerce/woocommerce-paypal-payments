<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use Mockery;
use ReflectionClass;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests for AbilitiesRegistrar as a DI service.
 *
 * PCP-6418: the registrar used to be a static coordinator whose
 * idempotency guard was a private static bool, forcing a public
 * reset_initialized_for_testing() method purely so tests could isolate
 * themselves. It is now an instance service; the guard is instance state
 * and each test simply builds its own registrar.
 *
 * The WC 10.9 loader gate is injectable so the "loader present" path can
 * be exercised without a WooCommerce runtime; the real gate is covered by
 * tests/integration/PHPUnit/Abilities/AbilitiesRegistrationTest.php.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar
 */
class AbilitiesRegistrarTest extends TestCase
{
	const FEATURE_FILTER = 'woocommerce_paypal_payments_abilities_enabled';
	const LOADER_FILTER  = 'woocommerce_ability_definition_classes';

	/**
	 * @scenario AbilitiesRegistrar::reset_initialized_for_testing() no longer exists.
	 *
	 * Given the registrar is a DI service
	 * When its API is inspected
	 * Then it exposes no test-only static reset helper and holds no static state.
	 */
	public function test_registrar_exposes_no_static_test_reset_helper(): void
	{
		// Then.
		$this->assertFalse(
			method_exists(AbilitiesRegistrar::class, 'reset_initialized_for_testing'),
			'A test-only public method on a production class was the symptom of the static design.'
		);

		$statics = (new ReflectionClass(AbilitiesRegistrar::class))->getStaticProperties();
		$this->assertSame(
			array(),
			$statics,
			'The initialization guard must be instance state, not a static property.'
		);
	}

	/**
	 * @scenario AbilitiesRegistrar is an instantiable service whose initialization
	 *           guard is instance state.
	 *
	 * Given two registrar instances and both gates open
	 * When each initializes twice
	 * Then each wires its filter exactly once, and neither is suppressed by the
	 *      other's guard.
	 */
	public function test_init_guard_is_instance_state_so_two_registrars_are_independent(): void
	{
		// Arrange.
		when('apply_filters')->justReturn(true);
		expect('add_filter')
			->twice()
			->with(
				self::LOADER_FILTER,
				Mockery::on(
					static function ($callback): bool {
						// The loader callback must be bound to the registrar
						// instance, not to the class name.
						return is_array($callback)
							&& is_object($callback[0])
							&& $callback[0] instanceof AbilitiesRegistrar
							&& 'append_classes' === $callback[1];
					}
				)
			);

		$first  = $this->registrar_with_loader_present();
		$second = $this->registrar_with_loader_present();

		// When.
		$first->init();
		$first->init();
		$second->init();
		$second->init();

		// Then: two instances -> two registrations, each guarded once.
		$this->assertTrue($this->read_initialized_guard($first));
		$this->assertTrue($this->read_initialized_guard($second));
	}

	/**
	 * @scenario With the filter left at its false default, no ability is registered
	 *           and the woocommerce_ability_definition_classes callback is never added.
	 *
	 * Given the feature flag is disabled
	 * When the registrar initializes
	 * Then it bails before wiring the loader filter and never latches its guard.
	 */
	public function test_init_does_not_register_the_loader_filter_when_feature_flag_is_disabled(): void
	{
		// Arrange.
		expect('apply_filters')
			->once()
			->with(self::FEATURE_FILTER, false)
			->andReturn(false);
		expect('add_filter')->never();

		$registrar = $this->registrar_with_loader_present();

		// When.
		$registrar->init();

		// Then.
		$this->assertFalse(
			$this->read_initialized_guard($registrar),
			'A disabled flag must leave the guard unlatched so an operator can flip it at runtime.'
		);
	}

	/**
	 * @scenario The loader gate keeps the module silent on WooCommerce < 10.9.
	 *
	 * Given the feature flag passes but AbilitiesLoader is absent
	 * When the registrar initializes
	 * Then it no-ops without wiring the filter.
	 */
	public function test_init_bails_when_the_woo_abilities_loader_is_absent(): void
	{
		// Arrange.
		expect('apply_filters')
			->once()
			->with(self::FEATURE_FILTER, false)
			->andReturn(true);
		expect('add_filter')->never();

		$registrar = new AbilitiesRegistrar(
			static function (): bool {
				return false;
			}
		);

		// When.
		$registrar->init();

		// Then.
		$this->assertFalse($this->read_initialized_guard($registrar));
	}

	/**
	 * @scenario All four abilities register under their unchanged slugs (class-list half).
	 *
	 * Given a caller-supplied list of ability definition classes
	 * When the registrar appends its own
	 * Then exactly the four Domain classes are contributed and the caller's survive.
	 */
	public function test_append_classes_contributes_the_four_definition_classes_onto_the_caller_list(): void
	{
		// Arrange.
		$preexisting = array( 'Some\\OtherPlugin\\AbilityDefinition' );
		$registrar   = $this->registrar_with_loader_present();

		// When.
		$classes = $registrar->append_classes($preexisting);

		// Then.
		$expected = array(
			Domain\GetConnectionStatus::class,
			Domain\GetPaymentMethods::class,
			Domain\GetOrderTracking::class,
			Domain\GetPaypalOrder::class,
		);

		$this->assertSame(
			$preexisting,
			array_slice($classes, 0, count($preexisting)),
			'append_classes() must merge onto the caller-supplied list, never replace it.'
		);
		$this->assertEqualsCanonicalizing(
			array_merge($preexisting, $expected),
			$classes
		);
	}

	/**
	 * @scenario Every ability is gated behind manage_woocommerce.
	 *
	 * Given a user with or without the capability
	 * When the shared permission gate runs
	 * Then it mirrors current_user_can('manage_woocommerce').
	 *
	 * @dataProvider capabilityDataProvider
	 */
	public function test_permission_gate_allows_only_manage_woocommerce(bool $granted): void
	{
		// Arrange.
		when('current_user_can')
			->alias(static function (string $capability) use ($granted): bool {
				return $granted && 'manage_woocommerce' === $capability;
			});

		// When / Then.
		$this->assertSame($granted, $this->registrar_with_loader_present()->can_manage_woocommerce());
	}

	public function capabilityDataProvider(): array
	{
		return array(
			'capability held'     => array( true ),
			'capability not held' => array( false ),
		);
	}

	/**
	 * A registrar whose injected WC 10.9 loader gate reports the loader as
	 * present, so tests can reach the registration path without WooCommerce.
	 */
	private function registrar_with_loader_present(): AbilitiesRegistrar
	{
		return new AbilitiesRegistrar(
			static function (): bool {
				return true;
			}
		);
	}

	/**
	 * Read the instance initialization guard without widening the production API.
	 */
	private function read_initialized_guard(AbilitiesRegistrar $registrar): bool
	{
		$property = (new ReflectionClass(AbilitiesRegistrar::class))->getProperty('initialized');
		$property->setAccessible(true);

		return (bool) $property->getValue($registrar);
	}
}
