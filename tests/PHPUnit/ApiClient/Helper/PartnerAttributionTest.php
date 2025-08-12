<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use PHPUnit\Framework\TestCase;

use WooCommerce\PayPalCommerce\Settings\Enum\InstallationPathEnum;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

/**
 * Test case for PartnerAttribution class.
 */
class PartnerAttributionTest extends TestCase {

	/**
	 * Option name for storing the BN Code.
	 *
	 * @var string
	 */
	private string $bn_code_option_name = 'ppcp_bn_code';

	/**
	 * BN codes mapping.
	 *
	 * @var array
	 */
    private array $bn_codes = array(
        InstallationPathEnum::CORE_PROFILER    => 'WooPPCP_Ecom_PS_CoreProfiler',
        InstallationPathEnum::PAYMENT_SETTINGS => 'WooPPCP_Ecom_PS_CoreProfiler',
    );

	/**
	 * The default BN code.
	 *
	 * @var string
	 */
	private string $default_bn_code = 'Woo_PPCP';

	/**
	 * Set up Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		setUp(); // ✅ REQUIRED for Brain Monkey to work
	}

	/**
	 * Tear down Brain Monkey after each test.
	 */
	protected function tearDown(): void {
		tearDown(); // ✅ REQUIRED to reset mocks after each test
		parent::tearDown();
	}

	/**
	 * Tests initializing BN code when it's not already set.
	 */
	public function test_initialize_bn_code_sets_bn_code_if_not_present(): void {
		$installation_path = 'core-profiler';
		$expected_bn_code  = 'WooPPCP_Ecom_PS_CoreProfiler';

		// Ensure get_option returns false to simulate "not set" state
		when('get_option')->justReturn(false);

		// Expect update_option to be called once with the correct values
		expect('update_option')
			->once()
			->with($this->bn_code_option_name, $expected_bn_code)
			->andReturn(true);

		$partner_attribution = new PartnerAttribution( $this->bn_code_option_name, $this->bn_codes, $this->default_bn_code );
		$partner_attribution->initialize_bn_code( $installation_path );

		$this->assertTrue(true);
	}

	/**
	 * Tests that initialize_bn_code does nothing if the BN code is already set.
	 */
	public function test_initialize_bn_code_does_not_update_if_already_set(): void {
		when('get_option')->justReturn('WooPPCP_Ecom_PS_CoreProfiler');
		expect( 'update_option' )->never();

		$partner_attribution = new PartnerAttribution( $this->bn_code_option_name, $this->bn_codes, $this->default_bn_code );
		$partner_attribution->initialize_bn_code( 'core-profiler' );

		$this->assertTrue(true);
	}

	/**
	 * Tests retrieving the BN Code.
	 */
	public function test_get_bn_code_returns_persisted_value(): void {
		$expected_bn_code = 'WooPPCP_Ecom_PS_CoreProfiler';

		expect( 'get_option' )
			->once()
			->with( $this->bn_code_option_name, $this->default_bn_code )
		    ->andReturn( $expected_bn_code );

		$partner_attribution = new PartnerAttribution( $this->bn_code_option_name, $this->bn_codes, $this->default_bn_code );

		$this->assertSame( $expected_bn_code, $partner_attribution->get_bn_code() );
	}

	/**
	 * Tests that get_bn_code returns an empty string if no value is stored.
	 */
	public function test_get_bn_code_returns_empty_string_when_not_set(): void {
		expect( 'get_option' )
			->once()
			->with( $this->bn_code_option_name, $this->default_bn_code )
			->andReturn( $this->default_bn_code );

		$partner_attribution = new PartnerAttribution( $this->bn_code_option_name, $this->bn_codes, $this->default_bn_code );

		$this->assertSame( $this->default_bn_code, $partner_attribution->get_bn_code() );
	}
}
