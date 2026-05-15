<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use ReflectionClass;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests for AbilitiesRegistrar.
 *
 * The plugin's PHPUnit suite uses Brain Monkey for WordPress-function
 * isolation; a real WP runtime is not available. The class_exists() check
 * for Woo Core's AbilitiesLoader is therefore exercised indirectly:
 * outside an integration environment, AbilitiesLoader is not autoloadable,
 * so init() bails on the loader gate even when the feature filter passes.
 * The integration harness (Phase V) covers the loader-present path against
 * a real WC 10.9 install.
 */
class AbilitiesRegistrarTest extends TestCase
{
	const FEATURE_FILTER = 'woocommerce_paypal_payments_abilities_enabled';
	const LOADER_FILTER  = 'woocommerce_ability_definition_classes';

	public function setUp(): void
	{
		parent::setUp();

		AbilitiesRegistrar::reset_initialized_for_testing();
	}

	public function tearDown(): void
	{
		AbilitiesRegistrar::reset_initialized_for_testing();

		parent::tearDown();
	}

	public function test_init_bails_when_feature_flag_is_disabled(): void
	{
		// Feature filter returns its second-arg default (false). add_filter
		// must not be invoked because init() short-circuits before the
		// loader gate.
		expect('apply_filters')
			->once()
			->with(self::FEATURE_FILTER, false)
			->andReturn(false);
		expect('add_filter')
			->never();

		AbilitiesRegistrar::init();

		// Concrete state assertion that the bail path did NOT latch the
		// $initialized guard. Prefer this over addToAssertionCount(1) so
		// that if Brain Monkey's deferred verification ever silently breaks
		// we still catch the regression rather than report a false-positive
		// pass with one phantom assertion.
		$this->assertFalse(self::read_initialized_guard());
	}

	public function test_init_bails_when_feature_flag_passes_but_loader_absent(): void
	{
		// AbilitiesLoader is not available in the unit-test environment, so
		// init() must reach the loader gate and return without wiring the
		// filter.
		expect('apply_filters')
			->once()
			->with(self::FEATURE_FILTER, false)
			->andReturn(true);
		expect('add_filter')
			->never();

		$this->assertFalse(
			class_exists('\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader'),
			'Sanity: AbilitiesLoader must be absent in the unit-test environment so this case is meaningful.'
		);

		AbilitiesRegistrar::init();
	}

	public function test_init_re_evaluates_gates_when_initialized_guard_not_set(): void
	{
		// The $initialized guard is only set after BOTH gates pass. With
		// the feature flag disabled, init() bails before setting the guard,
		// so a second call must re-evaluate the filter — that is the
		// intended behaviour and ensures an operator who flips the flag on
		// at runtime can re-trigger registration without a static-state
		// reset. The "wired-once" idempotency case (both gates pass, then
		// init() is called again) requires the WC 10.9 AbilitiesLoader and
		// is exercised by the integration harness in Phase V.
		expect('apply_filters')
			->twice()
			->with(self::FEATURE_FILTER, false)
			->andReturn(false);
		expect('add_filter')
			->never();

		AbilitiesRegistrar::init();
		AbilitiesRegistrar::init();

		// Same belt-and-suspenders rationale as the
		// feature-flag-disabled test: assert the guard wasn't latched
		// rather than relying on the Brain Monkey expect() count alone.
		$this->assertFalse(self::read_initialized_guard());
	}

	public function test_append_classes_round_trip_returns_full_ability_class_list(): void
	{
		$classes = AbilitiesRegistrar::append_classes(array());

		// Assert the registrar contributes exactly the Domain classes its
		// const declares — no more, no fewer. Order-insensitive
		// (assertEqualsCanonicalizing) so adding a Phase II/III ability
		// only requires a single update to $expected here, never a sort
		// dance against ABILITY_CLASSES' declaration order.
		$expected = array(
			Domain\GetConnectionStatus::class,
			Domain\GetPaymentMethods::class,
			Domain\GetLastWebhookEvent::class,
			Domain\GetOrderTracking::class,
			Domain\GetPaypalOrder::class,
		);

		$this->assertCount(count($expected), $classes);
		$this->assertEqualsCanonicalizing($expected, $classes);
	}

	public function test_append_classes_preserves_caller_supplied_classes(): void
	{
		$preexisting = array('Some\\OtherPlugin\\AbilityDefinition');

		$classes = AbilitiesRegistrar::append_classes($preexisting);

		$this->assertSame(
			$preexisting,
			array_slice($classes, 0, count($preexisting)),
			'append_classes() must merge onto the caller-supplied list, never replace it.'
		);
	}

	public function test_can_manage_woocommerce_returns_true_when_capability_held(): void
	{
		when('current_user_can')
			->alias(static function (string $capability): bool {
				return 'manage_woocommerce' === $capability;
			});

		$this->assertTrue(AbilitiesRegistrar::can_manage_woocommerce());
	}

	public function test_can_manage_woocommerce_returns_false_when_capability_not_held(): void
	{
		when('current_user_can')
			->justReturn(false);

		$this->assertFalse(AbilitiesRegistrar::can_manage_woocommerce());
	}

	public function test_category_slug_is_the_shared_woocommerce_bucket(): void
	{
		// Plugin ownership lives in the ability namespace
		// (`woocommerce-paypal-payments/<name>`), not the category. The
		// `woocommerce` slug is the shared cross-extension bucket Woo Core
		// owns. Locking this assertion in catches any future refactor that
		// drifts the registrar away from the shared category.
		$this->assertSame('woocommerce', AbilitiesRegistrar::CATEGORY_SLUG);
	}

	public function test_category_slug_mirror_on_abstract_ability_stays_in_sync(): void
	{
		// AbstractPpcpAbility intentionally redeclares CATEGORY_SLUG so
		// Domain classes can `self::CATEGORY_SLUG` without a cross-namespace
		// static reference. The duplication is ergonomic, not a typo —
		// this assertion fails loudly if either constant drifts.
		$this->assertSame(
			AbilitiesRegistrar::CATEGORY_SLUG,
			Domain\AbstractPpcpAbility::CATEGORY_SLUG,
			'CATEGORY_SLUG on AbstractPpcpAbility must mirror AbilitiesRegistrar::CATEGORY_SLUG.'
		);
	}

	/**
	 * Read the AbilitiesRegistrar::$initialized private static via
	 * reflection. Used by the gate-bail tests to assert the guard
	 * latched / didn't latch as expected, without needing to expose
	 * additional public methods on the registrar.
	 */
	private static function read_initialized_guard(): bool
	{
		return (bool) (new ReflectionClass(AbilitiesRegistrar::class))
			->getStaticPropertyValue('initialized');
	}
}
