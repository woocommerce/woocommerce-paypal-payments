<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * The storage and expiry every wallet payment record shares. Its subclasses are
 * tested only for what they add on top.
 *
 * Expiry is exercised by seeding the session directly, because time() cannot be
 * redefined in this suite.
 */
class SessionRecordTest extends TestCase {
	use MockeryPHPUnitIntegration;
	use StubsWcSession;

	private TestRecord $sut;

	/**
	 * @var array<string, mixed>
	 */
	private array $store = array();

	public function setUp(): void {
		parent::setUp();

		$this->store = array();
		$this->sut   = new TestRecord();

		$this->stub_wc( $this->session_with( $this->store ) );
	}

	/**
	 * @param mixed $session The session WC() should report.
	 */
	private function stub_wc( $session ): void {
		when( 'WC' )->justReturn( (object) array( 'session' => $session ) );
	}

	/**
	 * @param mixed $value   The stored value.
	 * @param int   $expires Its expiry, relative to now.
	 */
	private function seed( $value, int $expires ): void {
		$this->store[ TestRecord::KEY ] = array(
			'value'   => $value,
			'expires' => time() + $expires,
		);
	}

	/**
	 * GIVEN a value remembered for this payment
	 * WHEN it is read back
	 * THEN the same value is returned, because the record has not expired yet
	 */
	public function test_returns_the_value_it_remembered(): void {
		$this->sut->write( 'kept' );

		$this->assertSame( 'kept', $this->sut->read() );
	}

	/**
	 * GIVEN a value remembered for this payment
	 * WHEN the stored record is inspected
	 * THEN it carries an expiry, so nothing can be stored that never expires
	 */
	public function test_stores_an_expiry_in_the_future(): void {
		$this->sut->write( 'kept' );

		$this->assertGreaterThan( time(), $this->store[ TestRecord::KEY ]['expires'] );
	}

	/**
	 * GIVEN nothing was ever remembered
	 * WHEN the record is read
	 * THEN null is returned
	 */
	public function test_returns_null_when_nothing_was_remembered(): void {
		$this->assertNull( $this->sut->read() );
	}

	/**
	 * GIVEN a record that expires this very second
	 * WHEN it is read
	 * THEN it still applies, because the payment it belongs to may still be open
	 */
	public function test_survives_up_to_its_expiry(): void {
		$this->seed( 'kept', 0 );

		$this->assertSame( 'kept', $this->sut->read() );
	}

	/**
	 * GIVEN a record whose expiry has passed
	 * WHEN it is read
	 * THEN null is returned
	 * AND the stale record is cleared, so it cannot linger past its TTL
	 */
	public function test_clears_and_returns_null_once_expired(): void {
		$this->seed( 'stale', -1 );

		$this->assertNull( $this->sut->read() );
		$this->assertNull( $this->store[ TestRecord::KEY ] );
	}

	/**
	 * GIVEN a record stored without an expiry
	 * WHEN it is read
	 * THEN null is returned, rather than a record that would never expire
	 */
	public function test_ignores_a_record_with_no_expiry(): void {
		$this->store[ TestRecord::KEY ] = array( 'value' => 'forever' );

		$this->assertNull( $this->sut->read() );
	}

	/**
	 * GIVEN a remembered value
	 * WHEN it is forgotten
	 * THEN reading it afterward returns null
	 */
	public function test_forget_clears_the_record(): void {
		$this->sut->write( 'gone' );
		$this->sut->forget();

		$this->assertNull( $this->sut->read() );
	}

	/**
	 * GIVEN WooCommerce has no session available, e.g. outside of a request
	 * WHEN the record is written, forgotten and read
	 * THEN none of them error, and nothing is reported
	 */
	public function test_tolerates_a_missing_session(): void {
		$this->stub_wc( null );

		$this->sut->write( 'nowhere' );
		$this->sut->forget();

		$this->assertNull( $this->sut->read() );
	}
}
