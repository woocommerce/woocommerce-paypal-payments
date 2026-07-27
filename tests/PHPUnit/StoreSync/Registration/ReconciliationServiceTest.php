<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Registration;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Setting\AgenticSettingsDataModel;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Registration\ReconciliationService
 */
class ReconciliationServiceTest extends TestCase {

	/**
	 * @var AgenticSettingsDataModel|Mockery\MockInterface
	 */
	private $settings;

	/**
	 * @var RegistrationService|Mockery\MockInterface
	 */
	private $registration;

	/**
	 * @var LoggerInterface|Mockery\MockInterface
	 */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->settings     = Mockery::mock( AgenticSettingsDataModel::class );
		$this->registration = Mockery::mock( RegistrationService::class );

		$this->logger = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
	}

	private function create_testee(): ReconciliationService {
		return new ReconciliationService( $this->settings, $this->registration, $this->logger );
	}

	/**
	 * GIVEN a store that should initialize agentic features but is not yet registered
	 * WHEN reconciling the registration state
	 * AND the registration call succeeds
	 * THEN the store is registered
	 * AND the toggle is left active, since registration succeeded
	 */
	public function test_reconcile_registers_store_when_desired_but_not_registered(): void {
		$this->settings->shouldReceive( 'should_initialize_features' )->andReturn( true );
		$this->registration->shouldReceive( 'is_registered' )->andReturn( false );

		$this->registration->shouldReceive( 'register' )
			->once()
			->andReturn( new RegistrationResult( true, 'Registered', null ) );

		$this->settings->shouldNotReceive( 'set_active' );
		$this->settings->shouldNotReceive( 'save' );

		$this->create_testee()->reconcile();

		$this->addToAssertionCount( 3 );
	}

	/**
	 * GIVEN a store that should initialize agentic features but is not yet registered
	 * WHEN reconciling the registration state
	 * AND the registration call fails
	 * THEN the toggle is switched off, so a failure is never retried automatically
	 * AND the failure reason is logged for troubleshooting
	 * AND the settings are saved
	 */
	public function test_reconcile_disables_toggle_when_registration_fails(): void {
		$this->settings->shouldReceive( 'should_initialize_features' )->andReturn( true );
		$this->registration->shouldReceive( 'is_registered' )->andReturn( false );

		$this->registration->shouldReceive( 'register' )
			->once()
			->andReturn( new WP_Error( 'registration_failed', 'store not found' ) );

		$this->settings->shouldReceive( 'set_active' )->once()->with( false );
		$this->settings->shouldReceive( 'save' )->once();

		$this->create_testee()->reconcile();

		$this->addToAssertionCount( 3 );
	}

	/**
	 * GIVEN a store that is registered but should no longer have agentic features active
	 * WHEN reconciling the registration state
	 * THEN the store is deregistered
	 */
	public function test_reconcile_deregisters_store_when_no_longer_desired(): void {
		$this->settings->shouldReceive( 'should_initialize_features' )->andReturn( false );
		$this->registration->shouldReceive( 'is_registered' )->andReturn( true );

		$this->registration->shouldReceive( 'deregister' )->once();
		$this->registration->shouldNotReceive( 'register' );

		$this->create_testee()->reconcile();

		$this->addToAssertionCount( 2 );
	}

	/**
	 * GIVEN the desired and actual registration state already match
	 * WHEN reconciling the registration state
	 * THEN neither registration nor deregistration is attempted
	 *
	 * @dataProvider matching_state_provider
	 */
	public function test_reconcile_does_nothing_when_state_already_matches( bool $desired, bool $actual ): void {
		$this->settings->shouldReceive( 'should_initialize_features' )->andReturn( $desired );
		$this->registration->shouldReceive( 'is_registered' )->andReturn( $actual );

		$this->registration->shouldNotReceive( 'register' );
		$this->registration->shouldNotReceive( 'deregister' );

		$this->create_testee()->reconcile();

		$this->addToAssertionCount( 2 );
	}

	public function matching_state_provider(): array {
		return array(
			'already registered and still desired'     => array( true, true ),
			'already deregistered and no longer desired' => array( false, false ),
		);
	}

	/**
	 * GIVEN auto-registration has been disabled via the PPCP_AGENTIC_AUTO_REGISTER constant
	 * AND a store that should initialize agentic features but is not yet registered
	 * WHEN reconciling the registration state
	 * THEN registration is skipped
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_reconcile_skips_registration_when_auto_register_is_disabled(): void {
		define( 'PPCP_AGENTIC_AUTO_REGISTER', false );

		$this->settings->shouldReceive( 'should_initialize_features' )->andReturn( true );
		$this->registration->shouldReceive( 'is_registered' )->andReturn( false );

		$this->registration->shouldNotReceive( 'register' );
		$this->registration->shouldNotReceive( 'deregister' );

		$this->create_testee()->reconcile();

		$this->addToAssertionCount( 2 );
	}
}
