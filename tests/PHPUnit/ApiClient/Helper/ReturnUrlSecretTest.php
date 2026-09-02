<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Helper\ReturnUrlSecret
 */
class ReturnUrlSecretTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}

		// tests/PHPUnit/TestCase.php registers get_transient()/delete_transient() as
		// Brain\Monkey stubs (returnArg()) before Brain\Monkey\setUp() runs. Once a
		// function is registered as a stub, Brain\Monkey\Functions\expect() for the
		// same function silently keeps serving the old stub instead of the mock
		// (Brain\Monkey\Expectation\FunctionStubFactory::create() only redefines a
		// function when no stub/expectation already exists for it). Re-running
		// Brain\Monkey's own tearDown()/setUp() here clears that bookkeeping (and the
		// underlying Patchwork redefinitions) so that a per-test expect()/when() for
		// get_transient() or delete_transient() actually takes effect.
		\Brain\Monkey\tearDown();
		\Brain\Monkey\setUp();
	}

	/**
	 * GIVEN a fresh ReturnUrlSecret
	 * WHEN issue_pending() is called
	 * THEN it returns the value that wp_generate_password() produced
	 * AND no transient is written, because the PayPal order id is not known yet
	 */
	public function test_issue_pending_returns_generated_secret_without_persisting(): void {
		// Arrange
		when( 'wp_generate_password' )->justReturn( 'PENDING-SECRET-VALUE' );
		expect( 'set_transient' )->never();

		$testee = new ReturnUrlSecret();

		// When
		$secret = $testee->issue_pending();

		// Then
		$this->assertSame( 'PENDING-SECRET-VALUE', $secret );
	}

	/**
	 * GIVEN a pending secret created by issue_pending()
	 * WHEN bind() is called with the new PayPal order id
	 * THEN set_transient() stores the pending secret under 'ppcp_ru_' . $paypal_order_id
	 *      for DAY_IN_SECONDS
	 * AND a second call to bind() persists nothing more, because the pending secret
	 *     was cleared after the first bind
	 */
	public function test_bind_persists_the_pending_secret_once_and_clears_it(): void {
		// Arrange
		when( 'wp_generate_password' )->justReturn( 'SECRET-TO-BIND' );
		expect( 'set_transient' )
			->once()
			->with( 'ppcp_ru_ORDER-1', 'SECRET-TO-BIND', DAY_IN_SECONDS )
			->andReturn( true );

		$testee = new ReturnUrlSecret();
		$testee->issue_pending();

		// When
		$testee->bind( 'ORDER-1' );
		$testee->bind( 'ORDER-1' );

		// Then: set_transient() must have been called exactly once (asserted above).
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a ReturnUrlSecret that never had issue_pending() called on it
	 * WHEN bind() is called
	 * THEN no transient is written, because there is no pending secret to persist
	 */
	public function test_bind_without_a_pending_secret_persists_nothing(): void {
		// Arrange
		expect( 'set_transient' )->never();

		$testee = new ReturnUrlSecret();

		// When
		$testee->bind( 'ORDER-NO-PENDING' );

		// Then
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a PayPal order id that already exists
	 * WHEN issue_for() is called
	 * THEN a new secret is generated and persisted immediately under the order's key
	 * AND the generated secret is returned to the caller
	 */
	public function test_issue_for_generates_and_persists_immediately(): void {
		// Arrange
		when( 'wp_generate_password' )->justReturn( 'SECRET-FOR-ORDER' );
		expect( 'set_transient' )
			->once()
			->with( 'ppcp_ru_ORDER-2', 'SECRET-FOR-ORDER', DAY_IN_SECONDS )
			->andReturn( true );

		$testee = new ReturnUrlSecret();

		// When
		$secret = $testee->issue_for( 'ORDER-2' );

		// Then
		$this->assertSame( 'SECRET-FOR-ORDER', $secret );
	}

	/**
	 * GIVEN a transient that holds the bound secret for a PayPal order
	 * WHEN verify() is called with the exact same candidate
	 * THEN it returns true
	 */
	public function test_verify_returns_true_on_exact_match(): void {
		// Arrange
		expect( 'get_transient' )->once()->with( 'ppcp_ru_ORDER-3' )->andReturn( 'STORED-SECRET' );

		$testee = new ReturnUrlSecret();

		// When
		$result = $testee->verify( 'ORDER-3', 'STORED-SECRET' );

		// Then
		$this->assertTrue( $result );
	}

	/**
	 * GIVEN either an absent transient, an empty candidate, or a candidate that
	 *       differs from the stored secret
	 * WHEN verify() is called
	 * THEN it returns false
	 *
	 * @dataProvider verify_refusal_provider
	 */
	public function test_verify_returns_false_when_proof_does_not_hold( $stored_value, string $candidate ): void {
		// Arrange
		when( 'get_transient' )->justReturn( $stored_value );

		$testee = new ReturnUrlSecret();

		// When
		$result = $testee->verify( 'ORDER-4', $candidate );

		// Then
		$this->assertFalse( $result );
	}

	public function verify_refusal_provider(): array {
		return array(
			'transient absent'          => array( false, 'ANY-CANDIDATE' ),
			'candidate empty'           => array( 'STORED-SECRET', '' ),
			'candidate differs'         => array( 'STORED-SECRET', 'WRONG-CANDIDATE' ),
		);
	}

	/**
	 * GIVEN a secret bound to a PayPal order
	 * WHEN consume() is called
	 * THEN delete_transient() removes the transient under 'ppcp_ru_' . $paypal_order_id
	 */
	public function test_consume_deletes_the_bound_transient(): void {
		// Arrange
		expect( 'delete_transient' )->once()->with( 'ppcp_ru_ORDER-5' );

		$testee = new ReturnUrlSecret();

		// When
		$testee->consume( 'ORDER-5' );

		// Then
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a PayPal order id with or without a stored transient
	 * WHEN has_secret() is called
	 * THEN it reports whether a transient exists for that order
	 *
	 * @dataProvider has_secret_provider
	 */
	public function test_has_secret_reports_transient_presence( $transient_value, bool $expected ): void {
		// Arrange
		expect( 'get_transient' )->once()->with( 'ppcp_ru_ORDER-6' )->andReturn( $transient_value );

		$testee = new ReturnUrlSecret();

		// When
		$result = $testee->has_secret( 'ORDER-6' );

		// Then
		$this->assertSame( $expected, $result );
	}

	public function has_secret_provider(): array {
		return array(
			'transient exists' => array( 'SOME-SECRET', true ),
			'transient absent' => array( false, false ),
		);
	}

	/**
	 * GIVEN a pending secret created by issue_pending()
	 * WHEN discard_pending() is called with a value that does NOT match the pending secret
	 * THEN the pending secret is kept
	 * AND a following bind() still persists it
	 */
	public function test_discard_pending_keeps_a_non_matching_secret(): void {
		// Arrange
		when( 'wp_generate_password' )->justReturn( 'PENDING-TO-KEEP' );
		expect( 'set_transient' )
			->once()
			->with( 'ppcp_ru_ORDER-X', 'PENDING-TO-KEEP', DAY_IN_SECONDS )
			->andReturn( true );

		$testee = new ReturnUrlSecret();
		$testee->issue_pending();

		// When
		$testee->discard_pending( 'SOMETHING-ELSE' );
		$testee->bind( 'ORDER-X' );

		// Then: set_transient() must have been called exactly once (asserted above).
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a pending secret created by issue_pending()
	 * WHEN discard_pending() is called with that exact secret
	 * THEN the pending secret is cleared
	 * AND a following bind() persists nothing, because there is no pending secret left
	 */
	public function test_discard_pending_clears_only_the_matching_secret(): void {
		// Arrange
		when( 'wp_generate_password' )->justReturn( 'PENDING-TO-DISCARD' );
		expect( 'set_transient' )->never();

		$testee = new ReturnUrlSecret();
		$secret = $testee->issue_pending();

		// When
		$testee->discard_pending( $secret );
		$testee->bind( 'ORDER-X' );

		// Then
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a PayPal order id with a secret already bound to it
	 * WHEN secret_for() is called
	 * THEN it returns the bound secret
	 * AND it writes no new transient, because the existing binding is reused
	 */
	public function test_secret_for_returns_the_bound_secret_without_writing(): void {
		// Arrange
		expect( 'get_transient' )->once()->with( 'ppcp_ru_ORDER-7' )->andReturn( 'ALREADY-BOUND' );
		expect( 'set_transient' )->never();

		$testee = new ReturnUrlSecret();

		// When
		$result = $testee->secret_for( 'ORDER-7' );

		// Then
		$this->assertSame( 'ALREADY-BOUND', $result );
	}

	/**
	 * GIVEN a PayPal order id with no secret bound to it
	 * WHEN secret_for() is called
	 * THEN it generates a new secret and persists it under the order's key
	 * AND it returns the generated secret
	 */
	public function test_secret_for_issues_and_binds_when_none_is_bound(): void {
		// Arrange
		when( 'get_transient' )->justReturn( false );
		when( 'wp_generate_password' )->justReturn( 'FRESH' );
		expect( 'set_transient' )
			->once()
			->with( 'ppcp_ru_ORDER-8', 'FRESH', DAY_IN_SECONDS )
			->andReturn( true );

		$testee = new ReturnUrlSecret();

		// When
		$result = $testee->secret_for( 'ORDER-8' );

		// Then
		$this->assertSame( 'FRESH', $result );
	}
}
