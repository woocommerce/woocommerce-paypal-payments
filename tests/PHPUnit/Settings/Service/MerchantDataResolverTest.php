<?php
declare( strict_types = 1 );

namespace PHPUnit\Settings\Service;

use Mockery;
use RuntimeException;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Service\MerchantDataResolver;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PartnersEndpointFactory;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Service\MerchantDataResolver
 */
class MerchantDataResolverTest extends TestCase {

	private $general_settings;
	private $partners_endpoint;
	private $partners_endpoint_factory;
	private $logger;

	public function setUp(): void {
		parent::setUp();

		defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );

		when( 'as_next_scheduled_action' )->justReturn( false );

		$this->general_settings  = Mockery::mock( GeneralSettings::class );
		$this->partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$this->partners_endpoint_factory = Mockery::mock( PartnersEndpointFactory::class );
		$this->partners_endpoint_factory->shouldReceive( 'create' )->andReturn( $this->partners_endpoint );
		$this->logger            = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
	}

	private function create_resolver(): MerchantDataResolver {
		return new MerchantDataResolver( $this->general_settings, $this->partners_endpoint_factory, $this->logger );
	}

	private function merchant_data( string $merchant_country ): MerchantConnectionDTO {
		return new MerchantConnectionDTO( false, 'cid', 'secret', 'MID', 'merchant@example.com', $merchant_country );
	}

	/**
	 * GIVEN a merchant that is not connected to PayPal
	 * WHEN checking whether the country still needs to be resolved
	 * THEN no resolution is required
	 */
	public function test_needs_resolution_is_false_when_merchant_is_not_connected(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( false );
		$this->general_settings->shouldNotReceive( 'get_merchant_data' );

		$resolver = $this->create_resolver();

		$this->assertFalse( $resolver->needs_resolution() );
	}

	/**
	 * GIVEN a connected merchant whose country is already known
	 * WHEN checking whether the country still needs to be resolved
	 * THEN no resolution is required
	 */
	public function test_needs_resolution_is_false_when_country_already_known(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( 'DE' ) );

		$resolver = $this->create_resolver();

		$this->assertFalse( $resolver->needs_resolution() );
	}

	/**
	 * GIVEN a connected merchant whose country is still empty
	 * WHEN checking whether the country still needs to be resolved
	 * THEN resolution is required
	 */
	public function test_needs_resolution_is_true_when_country_is_empty(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$resolver = $this->create_resolver();

		$this->assertTrue( $resolver->needs_resolution() );
	}

	/**
	 * GIVEN a merchant that is not connected to PayPal
	 * WHEN ensure_country_resolved() runs
	 * THEN the resolver skips the API call, persists nothing, and schedules no retry
	 */
	public function test_ensure_country_resolved_does_nothing_when_merchant_is_not_connected(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( false );

		$this->partners_endpoint->shouldNotReceive( 'seller_status' );
		$this->general_settings->shouldNotReceive( 'save' );
		expect( 'as_schedule_single_action' )->never();

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->ensure_country_resolved() );
	}

	/**
	 * GIVEN a connected merchant with an unresolved country
	 * WHEN the seller-status API returns a non-empty country
	 * THEN the merchant data is updated and persisted, and no retry is scheduled
	 */
	public function test_ensure_country_resolved_persists_country_on_success(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$this->partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus( array(), array(), 'DE' )
		);

		$this->general_settings->shouldReceive( 'set_merchant_data' )->once()->with(
			Mockery::on(
				static fn( $dto ) => $dto instanceof MerchantConnectionDTO && 'DE' === $dto->merchant_country
			)
		);
		$this->general_settings->shouldReceive( 'save' )->once();
		expect( 'as_schedule_single_action' )->never();

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->ensure_country_resolved() );
	}

	/**
	 * GIVEN a connected merchant with an unresolved country
	 * WHEN the seller-status API returns an empty country
	 * THEN nothing is persisted and a first retry is scheduled
	 */
	public function test_ensure_country_resolved_schedules_retry_when_country_is_empty(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$this->partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus( array(), array(), '' )
		);

		$this->general_settings->shouldNotReceive( 'save' );
		expect( 'as_schedule_single_action' )->once()->with(
			Mockery::type( 'int' ),
			MerchantDataResolver::RETRY_HOOK,
			array( 'attempt' => 1 )
		);

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->ensure_country_resolved() );
	}

	/**
	 * GIVEN a connected merchant with an unresolved country
	 * WHEN the seller-status API call throws
	 * THEN the failure is logged, nothing is persisted, and a first retry is scheduled
	 */
	public function test_ensure_country_resolved_schedules_retry_when_api_call_throws(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$this->partners_endpoint->shouldReceive( 'seller_status' )
			->once()
			->andThrow( new RuntimeException( '403' ) );

		$this->general_settings->shouldNotReceive( 'save' );
		expect( 'as_schedule_single_action' )->once()->with(
			Mockery::type( 'int' ),
			MerchantDataResolver::RETRY_HOOK,
			array( 'attempt' => 1 )
		);

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->ensure_country_resolved() );
	}

	/**
	 * GIVEN a merchant that is not connected to PayPal
	 * WHEN handle_retry() runs
	 * THEN the resolver skips the API call entirely
	 */
	public function test_handle_retry_does_nothing_when_merchant_is_not_connected(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( false );

		$this->partners_endpoint->shouldNotReceive( 'seller_status' );

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->handle_retry( 2 ) );
	}

	/**
	 * GIVEN a connected merchant with an unresolved country
	 * WHEN a scheduled retry succeeds in resolving the country
	 * THEN the country is persisted and no further retry is scheduled
	 */
	public function test_handle_retry_persists_country_on_success(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$this->partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus( array(), array(), 'DE' )
		);

		$this->general_settings->shouldReceive( 'set_merchant_data' )->once()->with(
			Mockery::on(
				static fn( $dto ) => $dto instanceof MerchantConnectionDTO && 'DE' === $dto->merchant_country
			)
		);
		$this->general_settings->shouldReceive( 'save' )->once();
		expect( 'as_schedule_single_action' )->never();

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->handle_retry( 2 ) );
	}

	/**
	 * GIVEN a scheduled retry at attempt 2 that fails to resolve the country
	 * WHEN handle_retry() runs
	 * THEN a further retry is scheduled for attempt 3
	 */
	public function test_handle_retry_schedules_next_attempt_on_failure(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$this->partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus( array(), array(), '' )
		);

		expect( 'as_schedule_single_action' )->once()->with(
			Mockery::type( 'int' ),
			MerchantDataResolver::RETRY_HOOK,
			array( 'attempt' => 3 )
		);

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->handle_retry( 2 ) );
	}

	/**
	 * GIVEN a scheduled retry at the maximum allowed attempt that still fails
	 * WHEN handle_retry() runs
	 * THEN no further retry is scheduled, so the merchant is not retried forever
	 */
	public function test_handle_retry_does_not_reschedule_beyond_max_attempts(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$this->partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus( array(), array(), '' )
		);

		expect( 'as_schedule_single_action' )->never();

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->handle_retry( 3 ) );
	}

	/**
	 * GIVEN a retry for the same attempt is already pending in ActionScheduler
	 * WHEN resolution fails again
	 * THEN no duplicate retry is scheduled
	 */
	public function test_ensure_country_resolved_does_not_duplicate_pending_retry(): void {
		$this->general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$this->general_settings->shouldReceive( 'get_merchant_data' )
			->andReturn( $this->merchant_data( '' ) );

		$this->partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus( array(), array(), '' )
		);

		when( 'as_next_scheduled_action' )->justReturn( true );
		expect( 'as_schedule_single_action' )->never();

		$resolver = $this->create_resolver();
		$this->assertNull( $resolver->ensure_country_resolved() );
	}
}
