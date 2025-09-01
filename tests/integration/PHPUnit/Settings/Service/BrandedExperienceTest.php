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
		$this->generalSettings->set_installation_path( '' );

		$detector = Mockery::mock( ActivationDetector::class );

		$repository = new PathRepository(
			$detector,
			$this->generalSettings
		);

		$detector->shouldReceive( 'detect_activation_path' )
		         ->once()
		         ->andReturn( 'foo' );

		$detector->shouldReceive( 'detect_activation_path' )
		         ->once()
		         ->andReturn( 'bar' );

		$repository->persist();
		$repository->persist();

		$this->assertEquals( 'foo', $this->generalSettings->get_installation_path() );
	}
}
