<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\FrontendLogEndpoint
 */
class FrontendLogEndpointTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @var RequestData&Mockery\MockInterface
	 */
	private $request_data;

	/**
	 * @var LoggerInterface&Mockery\MockInterface
	 */
	private $logger;

	private FrontendLogEndpoint $sut;

	public function setUp(): void {
		parent::setUp();

		$this->request_data = Mockery::mock( RequestData::class );
		$this->logger       = Mockery::mock( LoggerInterface::class );

		$this->sut = new FrontendLogEndpoint( $this->request_data, $this->logger );
	}

	private function stub_posted_data( array $data ): void {
		$this->request_data->shouldReceive( 'read_request' )
			->with( FrontendLogEndpoint::nonce() )
			->andReturn( $data );
	}

	/**
	 * GIVEN a frontend reports a failure with a tag, event and context
	 * WHEN the request is handled
	 * THEN one line is logged as an error, naming the tag and event and
	 * listing every context value
	 * AND the caller is told the report was logged
	 */
	public function test_logs_a_line_built_from_tag_event_and_context(): void {
		$this->stub_posted_data(
			array(
				'tag'     => 'apple-pay',
				'event'   => 'sheet_failed',
				'context' => array(
					'reason' => 'declined',
					'code'   => '5000',
				),
			)
		);

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( '[apple-pay] sheet_failed reason=declined code=5000' );

		expect( 'wp_send_json_success' )
			->once()
			->with( array( 'logged' => true ) );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN the posted nonce fails validation
	 * WHEN the request is handled
	 * THEN an error response is sent with the failure message and a 400 status
	 * AND nothing is logged, since there is no trustworthy report to record
	 */
	public function test_sends_error_response_when_nonce_validation_fails(): void {
		$this->request_data->shouldReceive( 'read_request' )
			->with( FrontendLogEndpoint::nonce() )
			->andThrow( new NonceValidationException( 'Could not validate nonce.' ) );

		$this->logger->shouldReceive( 'error' )->never();

		expect( 'wp_send_json_error' )
			->once()
			->with( array( 'message' => 'Could not validate nonce.' ), 400 );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a report posted without a tag or event
	 * WHEN the request is handled
	 * THEN the line falls back to the literal "frontend" tag and "unknown" event
	 */
	public function test_message_falls_back_when_tag_and_event_are_absent(): void {
		$this->stub_posted_data( array() );

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( '[frontend] unknown' );

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a context value longer than the 500 character cap
	 * WHEN the request is handled
	 * THEN the logged value is cut off at 500 characters rather than logged in full
	 */
	public function test_context_value_is_truncated_to_the_maximum_length(): void {
		$long_value = str_repeat( 'a', 600 );

		$this->stub_posted_data(
			array(
				'tag'     => 'sdk',
				'event'   => 'call_failed',
				'context' => array( 'body' => $long_value ),
			)
		);

		$expected_message = '[sdk] call_failed body=' . str_repeat( 'a', 500 );

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( $expected_message );

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a context map containing a non-scalar value and an empty string
	 * WHEN the request is handled
	 * THEN both are dropped from the logged line instead of causing a failure
	 */
	public function test_unusable_context_values_are_dropped(): void {
		$this->stub_posted_data(
			array(
				'tag'     => 'sdk',
				'event'   => 'call_failed',
				'context' => array(
					'nested' => array( 'not' => 'scalar' ),
					'empty'  => '',
					'reason' => 'timeout',
				),
			)
		);

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( '[sdk] call_failed reason=timeout' );

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a report posted with a context field that is not an array
	 * WHEN the request is handled
	 * THEN it is treated as if no context was posted rather than causing a failure
	 */
	public function test_non_array_context_is_tolerated(): void {
		$this->stub_posted_data(
			array(
				'tag'     => 'sdk',
				'event'   => 'call_failed',
				'context' => 'not-an-array',
			)
		);

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( '[sdk] call_failed' );

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();
	}
}
