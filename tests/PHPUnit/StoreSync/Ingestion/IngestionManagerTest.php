<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Config\AgenticWebhookConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Ingestion\IngestionManager
 */
class IngestionManagerTest extends TestCase {

	private const NOW      = 1700000000;
	private const INTERVAL = 900;

	/**
	 * @var IngestionManager
	 */
	private $testee;

	public function setUp(): void {
		parent::setUp();

		when( 'time' )->justReturn( self::NOW );

		$configuration = Mockery::mock( IngestionConfiguration::class );
		$configuration->allows( 'get_sync_interval_in_seconds' )->andReturn( self::INTERVAL );

		$this->testee = new IngestionManager(
			$configuration,
			Mockery::mock( IngestionBatchProvider::class ),
			Mockery::mock( AgenticWebhookConfiguration::class ),
			Mockery::mock( MerchantMetadataProvider::class ),
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing(),
			Mockery::mock( ProductManager::class ),
			Mockery::mock( ProductFilter::class )
		);
	}

	/**
	 * GIVEN no sync action is scheduled
	 * WHEN the ingestion manager starts
	 * THEN a recurring sync is created whose first run is one sync interval in the future
	 * AND the action is unique
	 */
	public function test_init_schedules_recurring_sync_one_interval_ahead_as_unique_action(): void {
		when( 'as_next_scheduled_action' )->justReturn( false );

		expect( 'as_schedule_recurring_action' )
			->once()
			->with(
				self::NOW + self::INTERVAL,
				self::INTERVAL,
				'ppcp_agentic_sync_batch',
				array(),
				'ppcp_agentic_sync',
				true
			);

		$this->testee->init();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a sync action is already scheduled
	 * WHEN the ingestion manager starts
	 * THEN no further recurring sync is created
	 */
	public function test_init_does_not_schedule_again_when_sync_is_already_scheduled(): void {
		when( 'as_next_scheduled_action' )->justReturn( self::NOW + 60 );

		expect( 'as_schedule_recurring_action' )->never();

		$this->testee->init();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a sync action is scheduled
	 * WHEN the recurring schedule is cleared
	 * THEN all sync actions of the sync group are removed
	 */
	public function test_clear_recurring_schedule_removes_all_sync_actions(): void {
		when( 'as_next_scheduled_action' )->justReturn( self::NOW + 60 );

		expect( 'as_unschedule_all_actions' )
			->once()
			->with( 'ppcp_agentic_sync_batch', array(), 'ppcp_agentic_sync' );

		$this->testee->clear_recurring_schedule();

		$this->addToAssertionCount( 1 );
	}
}
