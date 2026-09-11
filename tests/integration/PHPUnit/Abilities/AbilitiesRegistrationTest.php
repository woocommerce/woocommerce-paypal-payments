<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Abilities;

use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;

/**
 * Integration coverage for the abilities module's registration path against a
 * real WC 10.9+ AbilitiesLoader (verified present on the ddev target: WP 7.0,
 * WC 11.0.1).
 *
 * PCP-6418: unit tests inject the WC-10.9-loader gate to exercise
 * AbilitiesRegistrar without WooCommerce; this suite is the one place that
 * proves the real `Automattic\WooCommerce\Internal\Abilities\AbilitiesLoader`
 * actually picks the four Domain classes up through
 * `woocommerce_ability_definition_classes` and registers them via
 * `wp_register_ability()`.
 *
 * Verified against WooCommerce trunk
 * (plugins/woocommerce/src/Internal/Abilities/AbilitiesLoader.php):
 * `AbilitiesLoader::init()` binds `register_abilities` to BOTH
 * `abilities_api_init` (pre-6.9) and `wp_abilities_api_init` (6.9+) —
 * "Support both old (pre-6.9) and new (6.9+) action names". This test fires
 * both so it keeps working on either WP generation; if WC ever changes either
 * name, only the two class constants below need to change.
 *
 * @group integration
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilitiesModule
 * @covers \WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar
 */
class AbilitiesRegistrationTest extends IntegrationMockedTestCase
{
	const FEATURE_FILTER = 'woocommerce_paypal_payments_abilities_enabled';
	const LOADER_INIT_ACTION_LEGACY = 'abilities_api_init';
	const LOADER_INIT_ACTION_CURRENT = 'wp_abilities_api_init';

	const ABILITY_NAMES = array(
		'woocommerce-paypal-payments/get-connection-status',
		'woocommerce-paypal-payments/get-payment-methods',
		'woocommerce-paypal-payments/get-order-tracking',
		'woocommerce-paypal-payments/get-paypal-order',
	);

	/** @var array<int, int> User ids created by a test, deleted in tearDown. */
	private $created_user_ids = array();

	public function tearDown(): void
	{
		foreach (self::ABILITY_NAMES as $ability_name) {
			if (function_exists('wp_has_ability') && wp_has_ability($ability_name)) {
				wp_unregister_ability($ability_name);
			}
		}

		// Two filters leak across tests in the same process unless cleared here:
		//
		// - self::FEATURE_FILTER (woocommerce_paypal_payments_abilities_enabled):
		//   the enabled-flag tests do `add_filter(self::FEATURE_FILTER,
		//   '__return_true')` and nothing else ever removes it. If that test runs
		//   first, the callback stays attached, so a LATER test's
		//   AbilitiesRegistrar::init() calls apply_filters(FEATURE_FILTER, false)
		//   and gets true back from the leftover callback — even though that
		//   test itself never enabled anything. This is what breaks the
		//   flag-off test when it runs after an enabled test.
		// - woocommerce_ability_definition_classes: each bootstrapModule() call
		//   builds a fresh AbilitiesRegistrar and, when the feature flag is
		//   enabled, attaches it here. That callback likewise stays on the
		//   global $wp_filter for the rest of the process, so a later test
		//   (even one whose OWN registrar correctly does not attach) would still
		//   have the PREVIOUS test's registrar contributing our four classes
		//   when the loader re-fires.
		//
		// Clearing both — after every test, regardless of pass/fail/order — is
		// what makes each test's outcome depend only on its own bootstrap, not
		// on what ran before it.
		remove_all_filters(self::FEATURE_FILTER);
		remove_all_filters('woocommerce_ability_definition_classes');

		// wp_delete_user() lives in wp-admin/includes/user.php, which this
		// harness's wp-load.php-only bootstrap never loads. Pull it in on
		// demand so cleanup doesn't fatal — and do this after the ability/filter
		// cleanup above, so even if user deletion were to fail it cannot skip
		// the state that actually caused test contamination.
		if ($this->created_user_ids && !function_exists('wp_delete_user')) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		foreach ($this->created_user_ids as $user_id) {
			wp_delete_user($user_id);
		}
		$this->created_user_ids = array();

		parent::tearDown();
	}

	private function create_user_with_role(string $role): int
	{
		$user_id = wp_insert_user(array(
			'user_login' => 'ppcp-abilities-' . $role . '-' . uniqid(),
			'user_pass'  => wp_generate_password(),
			'user_email' => 'ppcp-abilities-' . $role . '-' . uniqid() . '@example.test',
			'role'       => $role,
		));

		$this->assertIsInt($user_id, sprintf('Failed to create a %s fixture user for the permission-gate test.', $role));
		$this->created_user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Re-fires both hook names WC's AbilitiesLoader listens on, so the
	 * registration path is exercised regardless of which WP generation the
	 * loader itself is bound to.
	 *
	 * WordPress already fired these actions once during its own bootstrap
	 * (registering core's own abilities, e.g. `core/get-site-info`), and every
	 * other callback hooked to them re-runs too — not just ours. Core's own
	 * callback does not guard against double registration, so re-firing
	 * without suppression trips `_doing_it_wrong()` on an unrelated ability,
	 * which the integration suite's `convertNoticesToExceptions` turns into a
	 * fatal test failure that has nothing to do with this module. Suppressing
	 * `doing_it_wrong_trigger_error` only silences that notice for the
	 * duration of the re-fire; it does not change what actually gets
	 * registered, so it cannot mask a genuine registration failure of our own
	 * four abilities — those are asserted independently by slug afterwards.
	 * The filter is always removed, even if a hooked callback throws, so a
	 * failure here cannot leak the suppression into later tests.
	 */
	private function trigger_the_abilities_loader(): void
	{
		add_filter('doing_it_wrong_trigger_error', '__return_false');

		try {
			do_action(self::LOADER_INIT_ACTION_LEGACY);
			do_action(self::LOADER_INIT_ACTION_CURRENT);
		} finally {
			remove_filter('doing_it_wrong_trigger_error', '__return_false');
		}
	}

	/**
	 * Scenario: the abilities feature flag is enabled before the module boots.
	 *
	 * Given the woocommerce_paypal_payments_abilities_enabled filter is forced true
	 * When the abilities module runs and the real AbilitiesLoader is re-triggered
	 * Then wp_has_ability() is true for all four unchanged ability slugs.
	 *
	 * @test
	 */
	public function it_registers_all_four_abilities_when_the_feature_flag_is_enabled(): void
	{
		add_filter(self::FEATURE_FILTER, '__return_true');

		$this->bootstrapModule();
		$this->trigger_the_abilities_loader();

		foreach (self::ABILITY_NAMES as $ability_name) {
			$this->assertTrue(
				wp_has_ability($ability_name),
				sprintf('%s must be registered once the feature flag is enabled.', $ability_name)
			);
		}
	}

	/**
	 * Scenario: the abilities feature flag is left at its false default.
	 *
	 * Given no code has enabled woocommerce_paypal_payments_abilities_enabled
	 * When the abilities module runs on a stock install
	 * Then none of the four abilities register.
	 *
	 * @test
	 */
	public function it_registers_none_of_the_abilities_when_the_feature_flag_is_at_its_false_default(): void
	{
		// Prove the precondition instead of assuming it: if a previous test in
		// this process leaked its `add_filter(self::FEATURE_FILTER,
		// '__return_true')`, this must fail loudly here rather than produce a
		// confusing "true is false" only on the registration assertions below.
		$this->assertFalse(
			apply_filters(self::FEATURE_FILTER, false),
			sprintf('Precondition failed: %s must still default to false at the start of this test.', self::FEATURE_FILTER)
		);

		$this->bootstrapModule();
		$this->trigger_the_abilities_loader();

		foreach (self::ABILITY_NAMES as $ability_name) {
			$this->assertFalse(
				wp_has_ability($ability_name),
				sprintf('%s must stay unregistered on a stock install (flag defaults to false).', $ability_name)
			);
		}
	}

	/**
	 * Scenario: a backing service the container cannot build must not take
	 * ability registration down with it.
	 *
	 * Given the get-connection-status handler factory throws while resolving
	 * When the real AbilitiesLoader registers all four abilities and the
	 *      affected one is then executed
	 * Then all four abilities still register — a throw escaping registration
	 *      would break WooCommerce Core's own ability registration on the same
	 *      `wp_abilities_api_init`/`abilities_api_init` hook — and executing the
	 *      affected ability returns a WP_Error with code
	 *      woocommerce_paypal_payments_service_unavailable instead of fataling.
	 *
	 * @test
	 */
	public function it_returns_a_wp_error_instead_of_fataling_when_a_backing_service_cannot_be_resolved(): void
	{
		add_filter(self::FEATURE_FILTER, '__return_true');

		$this->bootstrapModule(array(
			'abilities.handler.get-connection-status' => function () {
				throw new \RuntimeException('settings.rest.common could not be built');
			},
		));
		$this->trigger_the_abilities_loader();

		foreach (self::ABILITY_NAMES as $ability_name) {
			$this->assertTrue(
				wp_has_ability($ability_name),
				sprintf('%s must still register; a broken backing service must not take registration down with it.', $ability_name)
			);
		}

		$admin_id = $this->create_user_with_role('administrator');
		wp_set_current_user($admin_id);

		$ability = wp_get_ability('woocommerce-paypal-payments/get-connection-status');
		$this->assertNotNull($ability, 'get-connection-status must be registered before it can be executed.');

		$result = $ability->execute();

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'A container failure while resolving the handler must degrade to a WP_Error, not a fatal.'
		);
		$this->assertSame('woocommerce_paypal_payments_service_unavailable', $result->get_error_code());
	}

	/**
	 * Scenario: every registered ability enforces the manage_woocommerce gate.
	 *
	 * Given all four abilities are registered
	 * When their permission callback runs for a subscriber vs. a shop manager
	 * Then a subscriber is denied and a shop manager/administrator is allowed.
	 *
	 * @test
	 */
	public function it_denies_a_subscriber_and_allows_a_shop_manager_for_every_ability_permission_callback(): void
	{
		add_filter(self::FEATURE_FILTER, '__return_true');

		$this->bootstrapModule();
		$this->trigger_the_abilities_loader();

		$subscriber_id = $this->create_user_with_role('subscriber');
		$shop_manager_id = $this->create_user_with_role('shop_manager');

		foreach (self::ABILITY_NAMES as $ability_name) {
			$ability = wp_get_ability($ability_name);
			$this->assertNotNull($ability, sprintf('%s must be registered before its permission gate can be checked.', $ability_name));

			wp_set_current_user($subscriber_id);
			$subscriber_result = $ability->check_permissions();
			// check_permissions() returns bool|WP_Error — WP_Error signals a
			// broken/missing permission callback, not a denial. Assert that
			// case away explicitly so it can never be mistaken for the
			// subscriber correctly being denied.
			$this->assertFalse(
				is_wp_error($subscriber_result),
				sprintf('%s permission callback must not error for a subscriber.', $ability_name)
			);
			$this->assertFalse(
				$subscriber_result,
				sprintf('%s must deny a subscriber.', $ability_name)
			);

			wp_set_current_user($shop_manager_id);
			$shop_manager_result = $ability->check_permissions();
			$this->assertFalse(
				is_wp_error($shop_manager_result),
				sprintf('%s permission callback must not error for a shop_manager.', $ability_name)
			);
			$this->assertTrue(
				$shop_manager_result,
				sprintf('%s must allow a shop_manager/administrator.', $ability_name)
			);
		}
	}
}
