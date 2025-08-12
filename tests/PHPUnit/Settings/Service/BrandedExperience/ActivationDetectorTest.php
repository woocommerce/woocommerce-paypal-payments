<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Service\BrandedExperience;

use WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\ActivationDetector;
use WooCommerce\PayPalCommerce\Settings\Enum\InstallationPathEnum;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class ActivationDetectorTest extends TestCase {

	public function test_returns_direct_if_not_installed_via_woocommerce_paths() {
		when( 'get_option' )->justReturn( [] );
		$detector = new ActivationDetector();

		$this->assertEquals( InstallationPathEnum::DIRECT, $detector->detect_activation_path() );
	}

	public function test_returns_core_profiler_if_installed_via_woocommerce_path() {
		expect( 'get_option' )
			->with( 'woocommerce_paypal_branded' )
			->andReturn('payments_settings' );

		$detector = new ActivationDetector();

		$this->assertEquals( InstallationPathEnum::CORE_PROFILER, $detector->detect_activation_path() );
	}
}
