<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Helper;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

/**
 * Unit tests for EnvelopeParser, the injectable replacement for
 * AbstractPpcpAbility::envelope_error_or_null()/unwrap_envelope().
 *
 * PCP-6418: the envelope-redaction contract (message/details redacted and
 * logged server-side by default, verbatim on opt-out, never an empty
 * WP_Error message) is a review-pinned guarantee that must survive the move
 * from static self::logger() to a constructor-injected PSR-3 logger.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Helper\EnvelopeParser
 */
class EnvelopeParserTest extends TestCase
{
	/** @var LoggerInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		$this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
	}

	private function create_parser(?LoggerInterface $logger = null): EnvelopeParser
	{
		return new EnvelopeParser($logger ?? $this->logger);
	}

	/**
	 * GIVEN a decoded envelope reporting success
	 * WHEN error_or_null() inspects it
	 * THEN no error is produced.
	 */
	public function test_error_or_null_returns_null_on_success(): void
	{
		$payload = array( 'success' => true, 'data' => array() );

		$this->assertNull($this->create_parser()->error_or_null($payload));
	}

	/**
	 * GIVEN a payload that is not the plugin's `{ success, data }` envelope
	 * WHEN error_or_null() inspects it
	 * THEN it is not this helper's concern and no error is produced.
	 */
	public function test_error_or_null_returns_null_when_success_key_is_absent(): void
	{
		$payload = array( 'merchant' => array() );

		$this->assertNull($this->create_parser()->error_or_null($payload));
	}

	/**
	 * GIVEN a failed envelope carrying raw upstream text and structured details
	 * WHEN error_or_null() runs with the default redaction
	 * THEN the agent-facing error contains no upstream text and no details.
	 */
	public function test_error_or_null_redacts_message_and_drops_details_by_default(): void
	{
		$payload = array(
			'success' => false,
			'message' => 'Some upstream failure with PayPal information_link https://api.paypal.com/v1/notifications/123.',
			'details' => array(
				'internal_route' => '/v2/checkout/orders/SECRET_ROUTE',
				'api_version'    => '2.5.1',
			),
		);

		$result = $this->create_parser()->error_or_null($payload);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_endpoint_error', $result->get_error_code());

		$this->assertStringNotContainsString('information_link', $result->get_error_message());
		$this->assertStringNotContainsString('paypal.com', $result->get_error_message());
		$this->assertStringContainsString('see server log', $result->get_error_message());

		$data = $result->get_error_data();
		if (is_array($data)) {
			$this->assertArrayNotHasKey('details', $data, 'details key must be dropped when redact_message=true.');
		} else {
			$this->assertSame('', $data, 'WP_Error data must be the empty default when redact_message=true.');
		}
	}

	/**
	 * GIVEN a failed envelope
	 * WHEN error_or_null() runs with redaction explicitly disabled
	 * THEN the raw message and details pass through verbatim — the opt-out is
	 * part of the helper's contract, not just the default path.
	 */
	public function test_error_or_null_preserves_message_and_details_when_redact_off(): void
	{
		$payload = array(
			'success' => false,
			'message' => 'Verbatim upstream message.',
			'details' => array( 'route' => '/v2/checkout/orders/X' ),
		);

		$result = $this->create_parser()->error_or_null($payload, false);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('Verbatim upstream message.', $result->get_error_message());
		$this->assertSame(array( 'details' => array( 'route' => '/v2/checkout/orders/X' ) ), $result->get_error_data());
	}

	/**
	 * GIVEN a failed envelope with no `message` key
	 * WHEN error_or_null() runs with redaction disabled
	 * THEN a non-empty fallback message is still supplied.
	 */
	public function test_error_or_null_falls_back_to_generic_message_when_message_missing_on_redact_off_branch(): void
	{
		$payload = array( 'success' => false );

		$result = $this->create_parser()->error_or_null($payload, false);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertNotSame('', $result->get_error_message(), 'Redact-off branch must supply a fallback message when the envelope omits one.');
	}

	/**
	 * GIVEN a failed envelope with no `message` key
	 * WHEN error_or_null() runs with the default redaction
	 * THEN a non-empty, redacted fallback message is supplied — the default
	 * path must never surface an empty WP_Error message either.
	 */
	public function test_error_or_null_supplies_non_empty_message_when_message_missing_on_default_redact_branch(): void
	{
		$payload = array( 'success' => false );

		$result = $this->create_parser()->error_or_null($payload);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertNotSame('', $result->get_error_message(), 'Default redact branch must always surface a non-empty WP_Error message.');
		$this->assertStringContainsString('see server log', $result->get_error_message());
	}

	/**
	 * GIVEN a failed envelope with a raw message and details
	 * WHEN error_or_null() runs with the default redaction
	 * THEN the raw text is written to the injected PSR-3 logger, never to
	 * error_log() — this is what makes the logger a constructor dependency
	 * instead of a static seam.
	 */
	public function test_error_or_null_logs_raw_message_and_details_to_the_injected_logger(): void
	{
		$logger = Mockery::mock(LoggerInterface::class);
		$logger->shouldReceive('warning')
			->once()
			->with(Mockery::pattern('/Raw upstream failure with information_link/'));
		$logger->shouldReceive('warning')
			->once()
			->with(Mockery::pattern('/SECRET_ROUTE/'));

		$payload = array(
			'success' => false,
			'message' => 'Raw upstream failure with information_link https://api.paypal.com/x.',
			'details' => array( 'internal_route' => '/v2/checkout/orders/SECRET_ROUTE' ),
		);

		$this->create_parser($logger)->error_or_null($payload);

		$this->addToAssertionCount(1);
	}

	/**
	 * GIVEN a successful envelope carrying a `data` key
	 * WHEN unwrap() runs
	 * THEN only the inner `data` is returned.
	 */
	public function test_unwrap_returns_the_inner_data_on_success(): void
	{
		$payload = array( 'success' => true, 'data' => array( 'foo' => 'bar' ) );

		$this->assertSame(array( 'foo' => 'bar' ), $this->create_parser()->unwrap($payload));
	}

	/**
	 * GIVEN a failed envelope
	 * WHEN unwrap() runs
	 * THEN it returns the same WP_Error error_or_null() would produce, rather
	 * than an inner `data` value.
	 */
	public function test_unwrap_returns_a_wp_error_on_failure(): void
	{
		$payload = array( 'success' => false, 'message' => 'boom' );

		$result = $this->create_parser()->unwrap($payload);

		$this->assertInstanceOf(WP_Error::class, $result);
	}

	/**
	 * GIVEN a payload that is not an array (e.g. already a scalar or object)
	 * WHEN unwrap() runs
	 * THEN the payload passes through unchanged — there is no envelope to parse.
	 */
	public function test_unwrap_passes_through_non_array_payloads_unchanged(): void
	{
		$payload = 'not-an-envelope';

		$this->assertSame($payload, $this->create_parser()->unwrap($payload));
	}
}
