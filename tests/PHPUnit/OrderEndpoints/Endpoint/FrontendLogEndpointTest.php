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
	 * GIVEN a frontend reports a failure with a tag, event and message
	 * WHEN the request is handled
	 * THEN one line is logged as an error, naming the tag, event and message
	 * AND the caller is told the report was logged
	 */
	public function test_logs_a_line_built_from_tag_event_and_message(): void {
		when( 'apply_filters' )->justReturn( true );

		$this->stub_posted_data(
			array(
				'tag'     => 'apple-pay',
				'event'   => 'sheet_failed',
				'message' => 'declined 5000',
			)
		);

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( '[apple-pay] sheet_failed: declined 5000' );

		expect( 'wp_send_json_success' )
			->once()
			->with();

		$this->sut->handle_request();
	}

	/**
	 * GIVEN the posted nonce fails validation
	 * WHEN the request is handled
	 * THEN nothing is logged, since there is no trustworthy report to record
	 * AND the same empty success response is sent as for a written report, since
	 * the response never reveals that the nonce was rejected
	 */
	public function test_sends_same_empty_success_when_nonce_validation_fails(): void {
		$this->request_data->shouldReceive( 'read_request' )
			->with( FrontendLogEndpoint::nonce() )
			->andThrow( new NonceValidationException( 'Could not validate nonce.' ) );

		$this->logger->shouldReceive( 'error' )->never();

		expect( 'wp_send_json_success' )
			->once()
			->with();

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a report posted without a tag or event
	 * WHEN the request is handled
	 * THEN the line falls back to the literal "frontend" tag and "unknown" event
	 */
	public function test_line_falls_back_when_tag_and_event_are_absent(): void {
		when( 'apply_filters' )->justReturn( true );

		$this->stub_posted_data( array() );

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( '[frontend] unknown' );

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a report posted with a tag and event but no message
	 * WHEN the request is handled
	 * THEN the line contains only the tag and event, with no trailing message
	 */
	public function test_line_omits_message_when_none_is_posted(): void {
		when( 'apply_filters' )->justReturn( true );

		$this->stub_posted_data(
			array(
				'tag'   => 'sdk',
				'event' => 'call_failed',
			)
		);

		$this->logger->shouldReceive( 'error' )
			->once()
			->with( '[sdk] call_failed' );

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a posted message long enough to push the assembled line past 1024
	 * characters
	 * WHEN the request is handled
	 * THEN the logged line is truncated to 1024 characters, since anyone who can
	 * load a storefront page holds a usable nonce
	 */
	public function test_line_is_truncated_at_max_length(): void {
		when( 'apply_filters' )->justReturn( true );

		$this->stub_posted_data(
			array(
				'tag'     => 'sdk',
				'event'   => 'call_failed',
				'message' => str_repeat( 'a', 2000 ),
			)
		);

		$this->logger->shouldReceive( 'error' )
			->once()
			->with(
				Mockery::on(
					static function ( string $line ): bool {
						return 1024 === strlen( $line );
					}
				)
			);

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN the woocommerce_paypal_payments_frontend_log_enabled filter returns false
	 * WHEN the request is handled
	 * THEN nothing is logged
	 * AND the caller is still told the report was logged, so the response never
	 * reveals whether reporting is on
	 */
	public function test_skips_logging_when_disabled_by_filter(): void {
		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_frontend_log_enabled', true )
			->andReturn( false );

		$this->stub_posted_data(
			array(
				'tag'   => 'sdk',
				'event' => 'call_failed',
			)
		);

		$this->logger->shouldReceive( 'error' )->never();

		expect( 'wp_send_json_success' )
			->once()
			->with();

		$this->sut->handle_request();
	}
}
