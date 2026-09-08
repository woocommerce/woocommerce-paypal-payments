<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Abilities\Helper\EnvelopeParser;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use WP_REST_Response;

/**
 * Unit tests for GetConnectionStatusHandler, the DI replacement for
 * Domain\GetConnectionStatus's static execute()/project_merchant_payload().
 *
 * PCP-6418: the handler calls its injected CommonRestEndpoint directly
 * (Shape 2) instead of round-tripping through rest_do_request(), and the
 * clientId/clientSecret redaction pinned in review must survive unchanged.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Handler\GetConnectionStatusHandler
 */
class GetConnectionStatusHandlerTest extends TestCase
{
	/** @var LoggerInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		$this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
	}

	private function create_handler(CommonRestEndpoint $endpoint, ?EnvelopeParser $envelope = null): GetConnectionStatusHandler
	{
		return new GetConnectionStatusHandler(
			$endpoint,
			$envelope ?? new EnvelopeParser($this->logger),
			$this->logger
		);
	}

	/**
	 * GIVEN a merchant payload with API credentials attached
	 * WHEN the handler executes
	 * THEN CommonRestEndpoint::get_merchant_details() is called exactly once
	 * and clientId/clientSecret are stripped while id/email/isConnected survive.
	 */
	public function test_execute_calls_endpoint_once_and_strips_client_credentials(): void
	{
		$endpoint = Mockery::mock(CommonRestEndpoint::class);
		$endpoint->shouldReceive('get_merchant_details')
			->once()
			->andReturn(new WP_REST_Response(array(
				'success'  => true,
				'data'     => array(),
				'merchant' => array(
					'isConnected'  => true,
					'isSandbox'    => false,
					'id'           => 'M3RCH4NT_ID',
					'email'        => 'merchant@example.test',
					'clientId'     => 'PUBLIC_LOOKING_BUT_SECRET_ID',
					'clientSecret' => 'CR3D3NT14L',
				),
			)));

		$result = $this->create_handler($endpoint)->execute();

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('clientId', $result['merchant']);
		$this->assertArrayNotHasKey('clientSecret', $result['merchant']);
		$this->assertSame('M3RCH4NT_ID', $result['merchant']['id']);
		$this->assertSame('merchant@example.test', $result['merchant']['email']);
		$this->assertTrue($result['merchant']['isConnected']);
	}

	/**
	 * GIVEN a merchant payload carrying a top-level `features` key
	 * WHEN the handler executes
	 * THEN the features are passed through unchanged.
	 */
	public function test_execute_passes_features_through_when_present(): void
	{
		$endpoint = Mockery::mock(CommonRestEndpoint::class);
		$endpoint->shouldReceive('get_merchant_details')->once()->andReturn(new WP_REST_Response(array(
			'success'  => true,
			'data'     => array(),
			'merchant' => array( 'isConnected' => true ),
			'features' => array( 'fastlane', 'pay_later' ),
		)));

		$result = $this->create_handler($endpoint)->execute();

		$this->assertSame(array( 'fastlane', 'pay_later' ), $result['features']);
	}

	/**
	 * GIVEN a merchant payload with no `features` key
	 * WHEN the handler executes
	 * THEN the result carries no `features` key either — none is synthesized.
	 */
	public function test_execute_omits_features_when_absent(): void
	{
		$endpoint = Mockery::mock(CommonRestEndpoint::class);
		$endpoint->shouldReceive('get_merchant_details')->once()->andReturn(new WP_REST_Response(array(
			'success'  => true,
			'data'     => array(),
			'merchant' => array( 'isConnected' => false ),
		)));

		$result = $this->create_handler($endpoint)->execute();

		$this->assertArrayNotHasKey('features', $result);
	}

	/**
	 * GIVEN a success envelope that omits the `merchant` subobject entirely
	 * WHEN the handler executes
	 * THEN it degrades to an empty merchant array instead of failing.
	 */
	public function test_execute_tolerates_a_missing_merchant_subobject(): void
	{
		$endpoint = Mockery::mock(CommonRestEndpoint::class);
		$endpoint->shouldReceive('get_merchant_details')->once()->andReturn(new WP_REST_Response(array(
			'success' => true,
			'data'    => array(),
		)));

		$result = $this->create_handler($endpoint)->execute();

		$this->assertSame(array(), $result['merchant']);
	}

	/**
	 * GIVEN the backing endpoint returns a success=false envelope
	 * WHEN the handler executes
	 * THEN a redacted WP_Error is returned to the caller while the raw
	 * upstream text is written to the injected PSR-3 logger.
	 */
	public function test_execute_returns_redacted_error_and_logs_the_raw_text_on_envelope_failure(): void
	{
		$endpoint = Mockery::mock(CommonRestEndpoint::class);
		$endpoint->shouldReceive('get_merchant_details')->once()->andReturn(new WP_REST_Response(array(
			'success' => false,
			'message' => 'Upstream failure with information_link https://api.paypal.com/v1/x.',
		)));

		$envelope_logger = Mockery::mock(LoggerInterface::class);
		$envelope_logger->shouldReceive('warning')
			->once()
			->with(Mockery::pattern('/information_link https:\/\/api\.paypal\.com/'));

		$result = $this->create_handler($endpoint, new EnvelopeParser($envelope_logger))->execute();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertStringNotContainsString('information_link', $result->get_error_message());
		$this->assertStringNotContainsString('paypal.com', $result->get_error_message());
	}
}
