<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Abilities\Helper\EnvelopeParser;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PaymentRestEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use WP_REST_Response;

/**
 * Unit tests for GetPaymentMethodsHandler, the DI replacement for
 * Domain\GetPaymentMethods's static execute().
 *
 * PCP-6418: the handler calls its injected PaymentRestEndpoint directly
 * (Shape 2) instead of round-tripping through rest_do_request().
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Handler\GetPaymentMethodsHandler
 */
class GetPaymentMethodsHandlerTest extends TestCase
{
	/** @var LoggerInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		$this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
	}

	private function create_handler(PaymentRestEndpoint $endpoint, ?EnvelopeParser $envelope = null): GetPaymentMethodsHandler
	{
		return new GetPaymentMethodsHandler(
			$endpoint,
			$envelope ?? new EnvelopeParser($this->logger),
			$this->logger
		);
	}

	/**
	 * GIVEN a payment-methods payload from the backing endpoint
	 * WHEN the handler executes
	 * THEN PaymentRestEndpoint::get_details() is called exactly once and the
	 * unwrapped `data` payload is returned.
	 */
	public function test_execute_calls_get_details_once_and_returns_the_unwrapped_payload(): void
	{
		$endpoint = Mockery::mock(PaymentRestEndpoint::class);
		$endpoint->shouldReceive('get_details')
			->once()
			->andReturn(new WP_REST_Response(array(
				'success' => true,
				'data'    => array(
					'ppcp-gateway' => array( 'id' => 'ppcp-gateway', 'enabled' => true ),
				),
			)));

		$result = $this->create_handler($endpoint)->execute();

		$this->assertSame(
			array( 'ppcp-gateway' => array( 'id' => 'ppcp-gateway', 'enabled' => true ) ),
			$result
		);
	}

	/**
	 * GIVEN the backing endpoint returns a success=false envelope
	 * WHEN the handler executes
	 * THEN a redacted WP_Error is returned rather than the raw upstream text.
	 */
	public function test_execute_returns_a_redacted_error_on_envelope_failure(): void
	{
		$endpoint = Mockery::mock(PaymentRestEndpoint::class);
		$endpoint->shouldReceive('get_details')->once()->andReturn(new WP_REST_Response(array(
			'success' => false,
			'message' => 'Upstream failure with information_link https://api.paypal.com/v1/x.',
		)));

		$result = $this->create_handler($endpoint)->execute();

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertStringNotContainsString('information_link', $result->get_error_message());
		$this->assertStringNotContainsString('paypal.com', $result->get_error_message());
	}
}
