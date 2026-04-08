<?php
/**
 * Integration tests for automatic settings UI migration.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Tests\Integration
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

class MigrationManagerTest extends TestCase {

	private MigrationManager $migration_manager;
	private OnboardingProfile $onboarding_profile;

	protected function setUp(): void {
		parent::setUp();

		$this->container          = $this->getContainer();
		$this->migration_manager  = $this->container->get('settings.service.data-migration');
		$this->onboarding_profile = $this->container->get('settings.data.onboarding');
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		delete_option(MigrationManager::OPTION_NAME_MIGRATION_IS_DONE);

		parent::tearDown();
	}

	/**
	 * Test that migration handles API failures gracefully and still completes.
	 *
	 * In test environments without valid API credentials, individual data migrations
	 * may fail, but the migration manager should catch these errors, log them,
	 * and NOT set the migration complete flag, allowing retry on next update.
	 */
	public function test_migration_handles_api_failures_gracefully(): void {
		// Assert pre-conditions
		$this->assertNotSame('1', get_option(MigrationManager::OPTION_NAME_MIGRATION_IS_DONE));

		// Act: Run migration (will fail due to missing API connection in tests)
		$this->migration_manager->migrate();

		// Reload the onboarding profile to get fresh data from DB
		$this->onboarding_profile = $this->container->get('settings.data.onboarding');

		// Assert: Migration did NOT complete due to API failure
		$this->assertNotSame('1', get_option(MigrationManager::OPTION_NAME_MIGRATION_IS_DONE));

		// Assert: Onboarding profile WAS updated (happens before API calls)
		$this->assertTrue($this->onboarding_profile->get_completed());
		$this->assertTrue($this->onboarding_profile->is_gateways_refreshed());
		$this->assertTrue($this->onboarding_profile->is_gateways_synced());
	}

	/**
	 * Test that onboarding profile is properly updated during migration.
	 */
	public function test_onboarding_profile_updated_during_migration(): void {
		// Arrange: Set onboarding as incomplete
		$this->onboarding_profile->set_completed(false);
		$this->onboarding_profile->set_gateways_refreshed(false);
		$this->onboarding_profile->set_gateways_synced(false);
		$this->onboarding_profile->save();

		// Assert pre-conditions
		$this->assertFalse($this->onboarding_profile->get_completed());
		$this->assertFalse($this->onboarding_profile->is_gateways_refreshed());
		$this->assertFalse($this->onboarding_profile->is_gateways_synced());

		// Act: Run migration
		$this->migration_manager->migrate();

		// Reload the onboarding profile
		$this->onboarding_profile = $this->container->get('settings.data.onboarding');

		// Assert: Onboarding profile fully updated (happens before API calls)
		$this->assertTrue($this->onboarding_profile->get_completed());
		$this->assertTrue($this->onboarding_profile->is_gateways_refreshed());
		$this->assertTrue($this->onboarding_profile->is_gateways_synced());
	}
}
