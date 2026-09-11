<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use Exception;
use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests for GetOrderTrackingHandler, the DI replacement for
 * Domain\GetOrderTracking's static execute()/serialize_shipment().
 *
 * PCP-6418: the handler is constructed with the container-resolved
 * OrderTrackingEndpoint instead of reaching it through resolve_service(), but
 * the not_found short-circuit, the null-to-empty-shipments coercion, and the
 * shipment wire-key contract must survive unchanged.
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Handler\GetOrderTrackingHandler
 */
class GetOrderTrackingHandlerTest extends TestCase
{
	/** @var LoggerInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		$this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
	}

	private function create_handler(?OrderTrackingEndpoint $endpoint = null): GetOrderTrackingHandler
	{
		return new GetOrderTrackingHandler(
			$endpoint ?? Mockery::mock(OrderTrackingEndpoint::class),
			$this->logger
		);
	}

	/**
	 * GIVEN no wc_order_id in the input
	 * WHEN the handler executes
	 * THEN it reports the field as required.
	 */
	public function test_execute_returns_error_when_wc_order_id_is_missing(): void
	{
		$result = $this->create_handler()->execute(array());

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_missing_wc_order_id', $result->get_error_code());
	}

	/**
	 * GIVEN a wc_order_id of 0
	 * WHEN the handler executes
	 * THEN it reports an invalid, non-positive input.
	 */
	public function test_execute_returns_error_when_wc_order_id_is_zero(): void
	{
		$result = $this->create_handler()->execute(array( 'wc_order_id' => 0 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_invalid_input', $result->get_error_code());
	}

	/**
	 * GIVEN a wc_order_id that does not resolve to a WooCommerce order
	 * WHEN the handler executes
	 * THEN it reports not_found and the tracking endpoint is never called.
	 */
	public function test_execute_returns_not_found_and_never_calls_the_endpoint_when_order_does_not_exist(): void
	{
		when('wc_get_order')->justReturn(false);

		$endpoint = Mockery::mock(OrderTrackingEndpoint::class);
		$endpoint->shouldNotReceive('list_tracking_information');

		$result = $this->create_handler($endpoint)->execute(array( 'wc_order_id' => 999 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_not_found', $result->get_error_code());
	}

	/**
	 * GIVEN the tracking endpoint returns null (the "no trackers yet" case)
	 * WHEN the handler executes
	 * THEN the result reports an empty shipment list rather than null.
	 */
	public function test_execute_coerces_a_null_result_to_an_empty_shipment_list(): void
	{
		when('wc_get_order')->justReturn(Mockery::mock(WC_Order::class));

		$endpoint = Mockery::mock(OrderTrackingEndpoint::class);
		$endpoint->shouldReceive('list_tracking_information')->once()->with(42)->andReturn(null);

		$result = $this->create_handler($endpoint)->execute(array( 'wc_order_id' => 42 ));

		$this->assertSame(array( 'wc_order_id' => 42, 'shipments' => array() ), $result);
	}

	/**
	 * GIVEN a shipment whose interface accessors return distinct sentinels
	 * WHEN the handler serializes it into the response
	 * THEN each wire key is populated from its matching accessor, so a future
	 * rename of either surfaces at the specific accessor -> key edge instead
	 * of collapsing into a mock-echo tautology.
	 */
	public function test_execute_serializes_each_shipment_accessor_to_its_expected_wire_key(): void
	{
		when('wc_get_order')->justReturn(Mockery::mock(WC_Order::class));

		$shipment = new class implements ShipmentInterface {
			public function capture_id(): string
			{
				return 'CAPTURE_ID_VALUE';
			}
			public function tracking_number(): string
			{
				return 'TRACKING_NUMBER_VALUE';
			}
			public function status(): string
			{
				return 'STATUS_VALUE';
			}
			public function carrier(): string
			{
				return 'CARRIER_VALUE';
			}
			public function carrier_name_other(): string
			{
				return 'CARRIER_NAME_OTHER_VALUE';
			}
			public function line_items(): array
			{
				return array( 42 );
			}
			public function render(array $allowed_statuses): void
			{
			}
			public function to_array(): array
			{
				return array(
					'capture_id'         => $this->capture_id(),
					'tracking_number'    => $this->tracking_number(),
					'status'             => $this->status(),
					'carrier'            => $this->carrier(),
					'carrier_name_other' => $this->carrier_name_other(),
					'items'              => $this->line_items(),
				);
			}
		};

		$endpoint = Mockery::mock(OrderTrackingEndpoint::class);
		$endpoint->shouldReceive('list_tracking_information')->once()->andReturn(array( $shipment ));

		$result = $this->create_handler($endpoint)->execute(array( 'wc_order_id' => 7 ));

		$serialized = $result['shipments'][0];
		$this->assertSame('CAPTURE_ID_VALUE', $serialized['capture_id'] ?? null, 'capture_id wire key must come from ::capture_id().');
		$this->assertSame('TRACKING_NUMBER_VALUE', $serialized['tracking_number'] ?? null, 'tracking_number wire key must come from ::tracking_number().');
		$this->assertSame('STATUS_VALUE', $serialized['status'] ?? null, 'status wire key must come from ::status().');
		$this->assertSame('CARRIER_VALUE', $serialized['carrier'] ?? null, 'carrier wire key must come from ::carrier().');
		$this->assertSame('CARRIER_NAME_OTHER_VALUE', $serialized['carrier_name_other'] ?? null, 'carrier_name_other wire key must come from ::carrier_name_other().');
		$this->assertSame(array( 42 ), $serialized['items'] ?? null, 'items wire key must come from ::line_items().');
	}

	/**
	 * GIVEN a shipment whose to_array() emits only a subset of the interface's
	 * fields
	 * WHEN the handler serializes it
	 * THEN only the keys to_array() actually emits appear — nothing is synthesized.
	 */
	public function test_execute_passes_through_only_the_keys_to_array_emits(): void
	{
		when('wc_get_order')->justReturn(Mockery::mock(WC_Order::class));

		$shipment = new class implements ShipmentInterface {
			public function capture_id(): string
			{
				return 'CAP-X';
			}
			public function tracking_number(): string
			{
				return '';
			}
			public function status(): string
			{
				return '';
			}
			public function carrier(): string
			{
				return '';
			}
			public function carrier_name_other(): string
			{
				return '';
			}
			public function line_items(): array
			{
				return array();
			}
			public function render(array $allowed_statuses): void
			{
			}
			public function to_array(): array
			{
				return array( 'capture_id' => $this->capture_id() );
			}
		};

		$endpoint = Mockery::mock(OrderTrackingEndpoint::class);
		$endpoint->shouldReceive('list_tracking_information')->once()->andReturn(array( $shipment ));

		$result = $this->create_handler($endpoint)->execute(array( 'wc_order_id' => 7 ));

		$this->assertSame(array( 'capture_id' ), array_keys($result['shipments'][0]));
		$this->assertSame('CAP-X', $result['shipments'][0]['capture_id']);
	}

	/**
	 * GIVEN the tracking endpoint throws while looking up shipments
	 * WHEN the handler executes
	 * THEN the caller receives a generic WP_Error carrying no exception text,
	 * while the injected logger receives the failure detail.
	 */
	public function test_execute_returns_a_generic_error_and_logs_the_detail_when_the_endpoint_throws(): void
	{
		when('wc_get_order')->justReturn(Mockery::mock(WC_Order::class));

		$endpoint = Mockery::mock(OrderTrackingEndpoint::class);
		$endpoint->shouldReceive('list_tracking_information')
			->once()
			->andThrow(new Exception('PayPal information_link https://api.paypal.com/x leaked here.'));

		$logger = Mockery::mock(LoggerInterface::class);
		$logger->shouldReceive('error')
			->once()
			->with(Mockery::on(static function ($message): bool {
				return is_string($message)
					&& false !== strpos($message, 'Exception')
					&& false !== strpos($message, 'information_link https://api.paypal.com');
			}));

		$handler = new GetOrderTrackingHandler($endpoint, $logger);

		$result = $handler->execute(array( 'wc_order_id' => 7 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertStringNotContainsString('information_link', $result->get_error_message());
		$this->assertStringNotContainsString('paypal.com', $result->get_error_message());
	}
}
