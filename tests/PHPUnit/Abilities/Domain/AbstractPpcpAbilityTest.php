<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

/**
 * Unit tests for shared envelope-handling logic in AbstractPpcpAbility.
 *
 * The helpers under test are `protected` so Domain subclasses can inherit
 * them; the {@see _AbilityTestSeam} subclass below re-exposes them as
 * static methods for direct assertion.
 */
class AbstractPpcpAbilityTest extends TestCase
{
	public function test_envelope_error_or_null_returns_null_on_success(): void
	{
		$payload = array( 'success' => true, 'data' => array() );
		$this->assertNull(_AbilityTestSeam::call_envelope_error_or_null($payload));
	}

	public function test_envelope_error_or_null_returns_null_when_success_key_is_absent(): void
	{
		// Non-envelope payloads (no `success` key) are not the helper's concern.
		$payload = array( 'merchant' => array() );
		$this->assertNull(_AbilityTestSeam::call_envelope_error_or_null($payload));
	}

	public function test_envelope_error_or_null_redacts_message_and_drops_details_by_default(): void
	{
		$payload = array(
			'success' => false,
			'message' => 'Some upstream failure with PayPal information_link https://api.paypal.com/v1/notifications/123.',
			'details' => array(
				'internal_route' => '/v2/checkout/orders/SECRET_ROUTE',
				'api_version'    => '2.5.1',
			),
		);

		$result = _AbilityTestSeam::call_envelope_error_or_null($payload);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_endpoint_error', $result->get_error_code());

		// Message redaction: the raw upstream text MUST NOT reach the agent.
		$this->assertStringNotContainsString('information_link', $result->get_error_message());
		$this->assertStringNotContainsString('paypal.com', $result->get_error_message());
		$this->assertStringContainsString('see server log', $result->get_error_message());

		// Details redaction: when `$redact_message` is true (default), the
		// structured `details` key must NOT be forwarded — future endpoints
		// could populate it with PayPal API error bodies that share the same
		// leak vectors as the message field.
		// WP_Error::get_error_data() returns '' (the WP_Error default) when no
		// data was attached; we explicitly assert there's no `details` key
		// reaching the agent.
		$data = $result->get_error_data();
		if (is_array($data)) {
			$this->assertArrayNotHasKey('details', $data, 'details key must be dropped when redact_message=true.');
		} else {
			$this->assertSame('', $data, 'WP_Error data must be the empty default when redact_message=true.');
		}
	}

	public function test_envelope_error_or_null_preserves_message_and_details_when_redact_off(): void
	{
		// The opt-out path is currently unused by any Shape-2 ability, but
		// the behaviour is part of the helper's contract — assert it.
		$payload = array(
			'success' => false,
			'message' => 'Verbatim upstream message.',
			'details' => array( 'route' => '/v2/checkout/orders/X' ),
		);

		$result = _AbilityTestSeam::call_envelope_error_or_null($payload, false);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('Verbatim upstream message.', $result->get_error_message());
		$this->assertSame(array( 'details' => array( 'route' => '/v2/checkout/orders/X' ) ), $result->get_error_data());
	}

	public function test_envelope_error_or_null_falls_back_to_generic_message_when_message_missing(): void
	{
		$payload = array( 'success' => false );

		$result = _AbilityTestSeam::call_envelope_error_or_null($payload, false);

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertNotSame('', $result->get_error_message(), 'Helper must supply a fallback message when the envelope omits one.');
	}
}

/**
 * Test seam that re-exposes the protected helpers as public statics.
 *
 * @internal Test-only; never autoloaded by production code.
 */
class _AbilityTestSeam extends AbstractPpcpAbility
{
	public static function call_envelope_error_or_null( array $payload, bool $redact_message = true ): ?\WP_Error
	{
		return self::envelope_error_or_null($payload, $redact_message);
	}
}
