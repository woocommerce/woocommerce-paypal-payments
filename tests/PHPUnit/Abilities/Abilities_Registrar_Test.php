<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests for Abilities_Registrar.
 *
 * The plugin's PHPUnit suite uses Brain Monkey for WordPress-function
 * isolation; a real WP runtime is not available. The class_exists() check
 * for Woo Core's AbilitiesLoader is therefore exercised indirectly:
 * outside an integration environment, AbilitiesLoader is not autoloadable,
 * so init() bails on the loader gate even when the feature filter passes.
 * The integration harness (Phase V) covers the loader-present path against
 * a real WC 10.9 install.
 */
class Abilities_Registrar_Test extends TestCase
{
	const FEATURE_FILTER = 'woocommerce_paypal_payments_abilities_enabled';
	const LOADER_FILTER  = 'woocommerce_ability_definition_classes';

	public function setUp(): void
	{
		parent::setUp();

		Abilities_Registrar::reset_initialized_for_testing();
	}

	public function tearDown(): void
	{
		Abilities_Registrar::reset_initialized_for_testing();

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

		Abilities_Registrar::init();

		// Brain Monkey's expectations register at tearDown via Mockery::close();
		// the explicit assertion below keeps PHPUnit's risky-test detector
		// from flagging this method as assertion-free.
		$this->addToAssertionCount(1);
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

		Abilities_Registrar::init();
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

		Abilities_Registrar::init();
		Abilities_Registrar::init();

		$this->addToAssertionCount(1);
	}

	public function test_append_classes_round_trip_returns_full_ability_class_list(): void
	{
		$classes = Abilities_Registrar::append_classes(array());

		// Assert the registrar contributes exactly the Domain classes its
		// const declares — no more, no fewer. Phase I lands with an empty
		// list; subsequent Domain-class commits will extend this assertion.
		$expected = array();

		$this->assertSame($expected, $classes);
	}

	public function test_append_classes_preserves_caller_supplied_classes(): void
	{
		$preexisting = array('Some\\OtherPlugin\\AbilityDefinition');

		$classes = Abilities_Registrar::append_classes($preexisting);

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

		$this->assertTrue(Abilities_Registrar::can_manage_woocommerce());
	}

	public function test_can_manage_woocommerce_returns_false_when_capability_not_held(): void
	{
		when('current_user_can')
			->justReturn(false);

		$this->assertFalse(Abilities_Registrar::can_manage_woocommerce());
	}

	public function test_category_slug_is_the_shared_woocommerce_bucket(): void
	{
		// Plugin ownership lives in the ability namespace
		// (`woocommerce-paypal-payments/<name>`), not the category. The
		// `woocommerce` slug is the shared cross-extension bucket Woo Core
		// owns. Locking this assertion in catches any future refactor that
		// drifts the registrar away from the shared category.
		$this->assertSame('woocommerce', Abilities_Registrar::CATEGORY_SLUG);
	}
}
