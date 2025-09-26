<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\ActivationDetector;
use WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\PathRepository;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

class BrandedExperienceTest extends TestCase {
	protected $container;
	private GeneralSettings $generalSettings;

	public function setUp(): void {
		parent::setUp();

		$this->container       = $this->getContainer();
		$this->generalSettings = $this->container->get( 'settings.data.general' );
	}

	public function test_should_persist_path_only_once() {
		$this->generalSettings->reset_installation_path( 'plugin_uninstall' );

		$detector = Mockery::mock( ActivationDetector::class );

		$detector->shouldReceive( 'detect_activation_path' )
		         ->once()
		         ->andReturn( 'payment-settings' );

		$repository = new PathRepository(
			$detector,
			$this->generalSettings
		);

		$repository->persist();
		$repository->persist();

		$this->assertEquals( 'payment-settings', $this->generalSettings->get_installation_path() );
	}
}
