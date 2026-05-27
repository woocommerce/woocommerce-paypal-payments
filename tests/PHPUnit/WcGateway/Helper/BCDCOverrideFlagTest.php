<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatusResultCache;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Filters\expectApplied;

/**
 * This test suite only covers the BCDC-Override-flag logic of the DCCProductStatus.
 *
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus
 */
class BCDCOverrideFlagTest extends TestCase {

	private const OVERRIDE_FLAG_KEY = 'woocommerce_paypal_payments_override_acdc_status_with_bcdc';

	private PartnersEndpoint $partners_endpoint;
	private FailureRegistry $api_failure_registry;
	private ProductStatusResultCache $result_cache;
	private DccApplies $dcc_applies;

	public function setUp(): void {
		parent::setUp();

		$this->partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$this->api_failure_registry = Mockery::mock( FailureRegistry::class );
		$this->result_cache         = Mockery::mock( ProductStatusResultCache::class );
		$this->dcc_applies          = Mockery::mock( DccApplies::class );

		defined( 'MONTH_IN_SECONDS' ) || define( 'MONTH_IN_SECONDS', 30 * 24 * 60 * 60 );
	}

	private function create_testee( bool $is_connected = true ): DCCProductStatus {
		return new DCCProductStatus(
			$is_connected,
			$this->partners_endpoint,
			$this->api_failure_registry,
			$this->result_cache,
			$this->dcc_applies
		);
	}

	// -------------------------------------------------------------------------
	// check_local_state() — filter active (skip_filters = false, default)
	// -------------------------------------------------------------------------

	/**
	 * @scenario BCDC override is active (filter returns true)
	 *
	 * Given the BCDC migration override flag is set
	 * When check_local_state() is called
	 * Then it must return false — ACDC is NOT active for this merchant
	 */
	public function test_check_local_state_returns_false_when_bcdc_override_is_active(): void {
		expectApplied( self::OVERRIDE_FLAG_KEY )
			->once()
			->with( null )
			->andReturn( true );

		// Cache should not be consulted when the filter short-circuits.
		$this->result_cache->shouldNotReceive( 'get' );

		$result = $this->create_testee()->check_local_state();

		$this->assertFalse( $result );
	}

	/**
	 * @scenario No BCDC override — filter returns null (default pass-through)
	 *
	 * Given no BCDC override is in place
	 * And the result cache is empty
	 * When check_local_state() is called
	 * Then it falls through to the parent cache check and returns null
	 */
	public function test_check_local_state_returns_null_when_no_override_and_cache_is_empty(): void {
		expectApplied( self::OVERRIDE_FLAG_KEY )
			->once()
			->with( null )
			->andReturn( null );

		$this->result_cache->shouldReceive( 'get' )
			->with( DCCProductStatus::KEY )
			->once()
			->andReturn( '' );

		$result = $this->create_testee()->check_local_state();

		$this->assertNull( $result );
	}

	/**
	 * @scenario No BCDC override — filter returns null, cache contains a positive result
	 *
	 * Given no BCDC override is in place
	 * And the result cache holds a previously stored "yes" value
	 * When check_local_state() is called
	 * Then it returns true (ACDC eligible, sourced from cache)
	 */
	public function test_check_local_state_returns_true_when_no_override_and_cache_is_yes(): void {
		expectApplied( self::OVERRIDE_FLAG_KEY )
			->once()
			->andReturn( null );

		$this->result_cache->shouldReceive( 'get' )
			->with( DCCProductStatus::KEY )
			->once()
			->andReturn( 'yes' );

		$result = $this->create_testee()->check_local_state();

		$this->assertTrue( $result );
	}

	/**
	 * @scenario No BCDC override — filter returns null, cache contains a negative result
	 *
	 * Given no BCDC override is in place
	 * And the result cache holds a previously stored "no" value
	 * When check_local_state() is called
	 * Then it returns false (ACDC not eligible, sourced from cache)
	 */
	public function test_check_local_state_returns_false_when_no_override_and_cache_is_no(): void {
		expectApplied( self::OVERRIDE_FLAG_KEY )
			->once()
			->andReturn( null );

		$this->result_cache->shouldReceive( 'get' )
			->with( DCCProductStatus::KEY )
			->once()
			->andReturn( 'no' );

		$result = $this->create_testee()->check_local_state();

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// check_local_state() — filter skipped (skip_filters = true)
	// -------------------------------------------------------------------------

	/**
	 * @scenario Filters are skipped explicitly — cache is empty
	 *
	 * Given skip_filters is true
	 * And the result cache is empty
	 * When check_local_state(true) is called
	 * Then the override filter is never applied
	 * And null is returned (no cached state available)
	 */
	public function test_check_local_state_skips_filter_and_returns_null_when_cache_is_empty(): void {
		expectApplied( self::OVERRIDE_FLAG_KEY )
			->never();

		$this->result_cache->shouldReceive( 'get' )
			->with( DCCProductStatus::KEY )
			->once()
			->andReturn( '' );

		$result = $this->create_testee()->check_local_state( true );

		$this->assertNull( $result );
	}

	/**
	 * @scenario Filters are skipped explicitly — cache contains a positive result
	 *
	 * Given skip_filters is true
	 * And the result cache holds "yes"
	 * When check_local_state(true) is called
	 * Then the override filter is never applied
	 * And true is returned (ACDC eligible, from cache)
	 */
	public function test_check_local_state_skips_filter_and_returns_true_when_cache_is_yes(): void {
		expectApplied( self::OVERRIDE_FLAG_KEY )
			->never();

		$this->result_cache->shouldReceive( 'get' )
			->with( DCCProductStatus::KEY )
			->once()
			->andReturn( 'yes' );

		$result = $this->create_testee()->check_local_state( true );

		$this->assertTrue( $result );
	}
}
