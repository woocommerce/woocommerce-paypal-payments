<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * A concrete cart endpoint exposing prevent_session_persistence() for direct testing,
 * since the method it verifies is protected and AbstractCartEndpoint itself is abstract.
 */
class TestableCartEndpoint extends AbstractCartEndpoint {

	protected function handle_data(): void {}

	public function prevent_session_persistence(): void {
		parent::prevent_session_persistence();
	}
}

class AbstractCartEndpointTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private TestableCartEndpoint $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new TestableCartEndpoint();
	}

	/**
	 * GIVEN a live WooCommerce session with WC_Session_Handler::save_data registered
	 * on the shutdown hook, the mechanism that would otherwise overwrite the whole
	 * session row with a stale snapshot
	 * WHEN prevent_session_persistence() is called
	 * THEN that shutdown persistence is removed, so nothing this request wrote to the
	 * session overwrites a concurrent request's changes
	 */
	public function test_removes_the_session_save_data_shutdown_hook(): void {
		$session  = Mockery::mock( \WC_Session::class );
		$callback = array( $session, 'save_data' );
		add_action( 'shutdown', $callback, 20 );

		$wc          = Mockery::mock();
		$wc->session = $session;
		when( 'WC' )->justReturn( $wc );

		$this->sut->prevent_session_persistence();

		$this->assertFalse( has_action( 'shutdown', $callback, 20 ) );
	}

	/**
	 * GIVEN WooCommerce has no usable session object for this request - either because
	 * WC()->session was never initialized, or because it holds something other than a
	 * WC_Session (contexts like cron or a webhook do not guarantee session state)
	 * WHEN prevent_session_persistence() is called
	 * THEN nothing is removed and no error is raised
	 *
	 * @dataProvider missing_session_provider
	 */
	public function test_does_nothing_when_no_usable_session_is_present( $session ): void {
		$wc          = Mockery::mock();
		$wc->session = $session;
		when( 'WC' )->justReturn( $wc );

		$unrelated_session = Mockery::mock( \WC_Session::class );
		$callback          = array( $unrelated_session, 'save_data' );
		add_action( 'shutdown', $callback, 20 );

		$this->sut->prevent_session_persistence();

		$this->assertTrue( has_action( 'shutdown', $callback, 20 ) );
	}

	public function missing_session_provider(): array {
		return array(
			'session property was never initialized' => array( null ),
			'session property is not a WC_Session'   => array( new \stdClass() ),
		);
	}

}
