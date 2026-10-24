<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\TokenUnavailableException;
use WooCommerce\PayPalCommerce\TestCase;
use Brain\Monkey\Filters;
use function Brain\Monkey\Functions\expect;

class ClientTokenEndpointTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @var LoggerInterface&Mockery\MockInterface
	 */
	private $logger;

	/**
	 * @var SdkClientToken&Mockery\MockInterface
	 */
	private $sdk_client_token;

	private ClientTokenEndpoint $sut;

	public function setUp(): void {
		parent::setUp();

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->logger           = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
		$this->sdk_client_token = Mockery::mock( SdkClientToken::class );

		$this->sut = new ClientTokenEndpoint(
			$this->logger,
			$this->sdk_client_token
		);
	}

	public function tearDown(): void {
		unset( $_SERVER['REQUEST_METHOD'] );

		parent::tearDown();
	}

	/**
	 * GIVEN a page served from a full-page cache, which carries no usable nonce
	 * WHEN the page requests the client token
	 * THEN the shopper is answered with the token
	 * AND no nonce is verified, so the age of the cached page does not matter
	 */
	public function test_answers_without_verifying_a_nonce(): void {
		expect( 'wp_verify_nonce' )->never();
		expect( 'check_ajax_referer' )->never();

		$this->sdk_client_token->shouldReceive( 'sdk_client_token_data' )
			->andReturn(
				array(
					'token'      => 'a-client-token',
					'expires_in' => 3540,
				)
			);

		expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'client_token' => 'a-client-token',
					'refresh_in'   => 3480,
					'retry_in'     => 10,
				)
			);
		expect( 'wp_send_json_error' )->never();

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a request that is not a POST, such as a crawler, a prefetch or an image tag
	 * WHEN the request is handled
	 * THEN it is answered with 405
	 * AND no client token is requested
	 *
	 * @dataProvider non_post_method_provider
	 */
	public function test_non_post_request_answers_405_without_requesting_a_token( ?string $method ): void {
		if ( null === $method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $method;
		}

		$this->sdk_client_token->shouldNotReceive( 'sdk_client_token_data' );

		expect( 'wp_send_json_error' )
			->once()
			->with( array( 'message' => 'Method not allowed.' ), 405 );
		expect( 'wp_send_json_success' )->never();

		$this->sut->handle_request();
	}

	public function non_post_method_provider(): array {
		return array(
			'GET'               => array( 'GET' ),
			'HEAD'              => array( 'HEAD' ),
			'PUT'               => array( 'PUT' ),
			'no request method' => array( null ),
		);
	}

	/**
	 * GIVEN a store that is not connected, or token requests paused after a PayPal failure
	 * WHEN the client token is requested
	 * THEN the shopper is answered with 503 and the seconds to wait before asking again
	 * AND the wait is never below 10 seconds
	 * AND nothing is written to the error log, because anonymous requests reach this in volume
	 *
	 * @dataProvider token_unavailable_provider
	 */
	public function test_unavailable_token_answers_503_with_the_wait_and_no_error_log( int $retry_after, int $expected_retry_in ): void {
		$this->sdk_client_token->shouldReceive( 'sdk_client_token_data' )
			->andThrow( new TokenUnavailableException( 'Paused.', $retry_after ) );

		$this->logger->shouldNotReceive( 'error' );

		expect( 'wp_send_json_error' )
			->once()
			->with(
				array(
					'message'  => 'Client token temporarily unavailable.',
					'retry_in' => $expected_retry_in,
				),
				503
			);
		expect( 'wp_send_json_success' )->never();

		$this->sut->handle_request();
	}

	public function token_unavailable_provider(): array {
		return array(
			'a pause of 120 seconds is passed on'     => array( 120, 120 ),
			'the not-connected wait is passed on'     => array( 300, 300 ),
			'a pause of 3 seconds becomes 10 seconds' => array( 3, 10 ),
			'a pause that just ended becomes 10'      => array( 0, 10 ),
		);
	}

	/**
	 * GIVEN a request for the client token
	 * WHEN the SDK client token generator produces a token
	 * THEN the shopper is answered with that token
	 * AND the seconds until the browser refreshes it
	 * AND the seconds until the browser retries after a failed refresh
	 */
	public function test_valid_request_answers_with_the_generated_client_token(): void {
		$this->sdk_client_token->shouldReceive( 'sdk_client_token_data' )
			->andReturn(
				array(
					'token'      => 'a-client-token',
					'expires_in' => 3540,
				)
			);

		expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'client_token' => 'a-client-token',
					'refresh_in'   => 3480,
					'retry_in'     => 10,
				)
			);
		expect( 'wp_send_json_error' )->never();

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a request for the client token and a token with a given lifetime
	 * WHEN the request is handled
	 * THEN the browser refreshes the token 60 seconds before it expires
	 * AND waits at least 10 seconds when the lifetime is 60 seconds or less
	 *
	 * @dataProvider token_lifetime_provider
	 */
	public function test_refresh_happens_shortly_before_the_token_expires( int $expires_in, int $expected_refresh_in ): void {
		$this->sdk_client_token->shouldReceive( 'sdk_client_token_data' )
			->andReturn(
				array(
					'token'      => 'a-client-token',
					'expires_in' => $expires_in,
				)
			);

		expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'client_token' => 'a-client-token',
					'refresh_in'   => $expected_refresh_in,
					'retry_in'     => 10,
				)
			);

		$this->sut->handle_request();
	}

	public function token_lifetime_provider(): array {
		return array(
			'lifetime minus 60 seconds'          => array( 3600, 3540 ),
			'lifetime just above the minimum'    => array( 71, 11 ),
			'lifetime giving exactly 10 seconds' => array( 70, 10 ),
			'lifetime of exactly 60 seconds'     => array( 60, 10 ),
			'lifetime below 60 seconds'          => array( 30, 10 ),
		);
	}

	/**
	 * GIVEN a request for the client token and a token that stays valid for 3540 seconds
	 * WHEN a filter changes the seconds until the browser refreshes the token
	 * THEN the shopper is answered with the filtered value, if it is shorter
	 * AND never with a value longer than the computed one
	 * AND never with a numeric value below 10 seconds
	 * AND a non-numeric value is ignored, so the computed value stays
	 *
	 * @param mixed $filtered_value Value the filter returns.
	 * @dataProvider refresh_in_filter_provider
	 */
	public function test_filtered_refresh_delay_is_never_longer_than_computed_or_below_minimum( $filtered_value, int $expected_refresh_in ): void {
		$this->sdk_client_token->shouldReceive( 'sdk_client_token_data' )
			->andReturn(
				array(
					'token'      => 'a-client-token',
					'expires_in' => 3540,
				)
			);

		Filters\expectApplied( 'woocommerce_paypal_payments_sdk_v6_client_token_refresh_in' )
			->once()
			->with( 3480 )
			->andReturn( $filtered_value );

		expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'client_token' => 'a-client-token',
					'refresh_in'   => $expected_refresh_in,
					'retry_in'     => 10,
				)
			);

		$this->sut->handle_request();
	}

	public function refresh_in_filter_provider(): array {
		return array(
			'a shorter value is used'                       => array( 120, 120 ),
			'a shorter numeric string is used'              => array( '120', 120 ),
			'exactly 10 seconds is used'                    => array( 10, 10 ),
			'a longer value keeps the computed value'       => array( 7200, 3480 ),
			'a value below 10 seconds becomes 10'           => array( 5, 10 ),
			'zero becomes 10'                               => array( 0, 10 ),
			'a negative value becomes 10'                   => array( -30, 10 ),
			'a non-numeric string keeps the computed value' => array( 'soon', 3480 ),
			'null keeps the computed value'                 => array( null, 3480 ),
			'an array keeps the computed value'             => array( array( 120 ), 3480 ),
			'false keeps the computed value'                => array( false, 3480 ),
		);
	}

	/**
	 * GIVEN a request for the client token
	 * WHEN generating the client token fails
	 * THEN the shopper is answered with a generic 500 error, never the exception detail
	 * AND the exception detail is written to the error log instead
	 *
	 * @dataProvider token_generation_failure_provider
	 */
	public function test_token_generation_failure_answers_500_with_a_generic_message( string $exception_class, string $expected_logged_detail ): void {
		$exception = $this->build_failure( $exception_class, $expected_logged_detail );

		$this->sdk_client_token->shouldReceive( 'sdk_client_token_data' )->andThrow( $exception );

		$this->logger->shouldReceive( 'error' )
			->once()
			->with(
				Mockery::on(
					static function ( $message ) use ( $expected_logged_detail ): bool {
						return is_string( $message ) && false !== strpos( $message, $expected_logged_detail );
					}
				)
			);

		expect( 'wp_send_json_error' )
			->once()
			->with(
				array( 'message' => 'Failed to generate client token.' ),
				500
			);
		expect( 'wp_send_json_success' )->never();

		$this->sut->handle_request();
	}

	public function token_generation_failure_provider(): array {
		return array(
			'a PayPal API error is hidden from the shopper'
				=> array( PayPalApiException::class, 'Insufficient scope for client token.' ),
			'a runtime error is hidden from the shopper'
				=> array( RuntimeException::class, 'Connection to PayPal timed out.' ),
		);
	}

	/**
	 * Builds the exception the SDK client token generator throws, carrying the
	 * given detail. Kept out of the data provider: PayPalApiException's
	 * constructor calls the WordPress __() stub, which only exists once setUp()
	 * has run.
	 */
	private function build_failure( string $exception_class, string $detail ): \Throwable {
		if ( PayPalApiException::class === $exception_class ) {
			$response          = new \stdClass();
			$response->message = $detail;

			return new PayPalApiException( $response, 503 );
		}

		return new RuntimeException( $detail );
	}
}
