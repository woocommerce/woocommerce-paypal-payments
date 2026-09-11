<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Abilities\Handler;

use Exception;
use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpointCached;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use function Brain\Monkey\Functions\when;

/**
 * Unit tests for GetPaypalOrderHandler, the DI replacement for
 * Domain\GetPaypalOrder's static execute()/project_order().
 *
 * PCP-6418: identifier resolution, the ^[A-Z0-9]{1,64}$ format guard, and the
 * payer/payment_source/shipping redaction pinned in review must survive the
 * move to a handler constructed with the container-resolved
 * OrderEndpointCached instead of resolve_service().
 *
 * @covers \WooCommerce\PayPalCommerce\Abilities\Handler\GetPaypalOrderHandler
 */
class GetPaypalOrderHandlerTest extends TestCase
{
	/** @var LoggerInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		$this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
	}

	private function create_handler(?OrderEndpointCached $endpoint = null): GetPaypalOrderHandler
	{
		return new GetPaypalOrderHandler(
			$endpoint ?? Mockery::mock(OrderEndpointCached::class),
			$this->logger
		);
	}

	/**
	 * GIVEN neither paypal_order_id nor wc_order_id was supplied
	 * WHEN the handler executes
	 * THEN it reports a missing identifier without touching the endpoint.
	 */
	public function test_execute_returns_error_when_no_identifier_supplied(): void
	{
		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldNotReceive('order');

		$result = $this->create_handler($endpoint)->execute(array());

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_missing_identifier', $result->get_error_code());
	}

	/**
	 * GIVEN wc_order_id is 0 and no paypal_order_id was supplied
	 * WHEN the handler executes
	 * THEN it is treated the same as no identifier at all.
	 */
	public function test_execute_returns_error_when_wc_order_id_is_zero_and_no_paypal_id(): void
	{
		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldNotReceive('order');

		$result = $this->create_handler($endpoint)->execute(array( 'wc_order_id' => 0 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_missing_identifier', $result->get_error_code());
	}

	/**
	 * GIVEN a paypal_order_id containing a path-traversal-style payload
	 * WHEN the handler executes
	 * THEN the format guard rejects it before OrderEndpointCached::order() is
	 * ever called, since the endpoint concatenates the id unescaped.
	 */
	public function test_execute_rejects_a_path_traversal_paypal_order_id_without_calling_the_endpoint(): void
	{
		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldNotReceive('order');

		$result = $this->create_handler($endpoint)->execute(array( 'paypal_order_id' => 'ORDERID/../refunds' ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_invalid_input', $result->get_error_code());
	}

	/**
	 * GIVEN a lowercase paypal_order_id
	 * WHEN the handler executes
	 * THEN the uppercase-only format guard rejects it without calling the endpoint.
	 */
	public function test_execute_rejects_a_lowercase_paypal_order_id_without_calling_the_endpoint(): void
	{
		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldNotReceive('order');

		$result = $this->create_handler($endpoint)->execute(array( 'paypal_order_id' => 'lowercase' ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_invalid_input', $result->get_error_code());
	}

	/**
	 * GIVEN a wc_order_id that does not resolve to a WooCommerce order
	 * WHEN the handler executes
	 * THEN it reports not_found without calling the PayPal endpoint.
	 */
	public function test_execute_returns_not_found_when_wc_order_does_not_exist(): void
	{
		when('wc_get_order')->justReturn(false);

		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldNotReceive('order');

		$result = $this->create_handler($endpoint)->execute(array( 'wc_order_id' => 999 ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertSame('woocommerce_paypal_payments_not_found', $result->get_error_code());
	}

	/**
	 * GIVEN a PayPal order carrying payer PII, a synthetic payment_source, and
	 * per-purchase-unit shipping
	 * WHEN the handler executes without opting into PII
	 * THEN payer, payment_source, and shipping are all stripped from the result.
	 */
	public function test_execute_strips_payer_payment_source_and_shipping_by_default(): void
	{
		$order = Mockery::mock(Order::class);
		$order->shouldReceive('to_array')->andReturn(array(
			'id'             => '8XR43025NW123456A',
			'status'         => 'COMPLETED',
			'payer'          => array( 'email_address' => 'payer@example.test' ),
			'payment_source' => array( 'paypal' => array( 'email_address' => 'leaky@example.test' ) ),
			'purchase_units' => array(
				array(
					'reference_id' => 'default',
					'shipping'     => array( 'address' => array( 'country_code' => 'US' ) ),
					'amount'       => array( 'currency_code' => 'USD', 'value' => '10.00' ),
				),
			),
		));

		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldReceive('order')->once()->with('8XR43025NW123456A')->andReturn($order);

		$result = $this->create_handler($endpoint)->execute(array( 'paypal_order_id' => '8XR43025NW123456A' ));

		$this->assertIsArray($result);
		$this->assertArrayNotHasKey('payer', $result);
		$this->assertArrayNotHasKey('payment_source', $result);
		$this->assertArrayNotHasKey('shipping', $result['purchase_units'][0]);
		$this->assertSame('8XR43025NW123456A', $result['id']);
		$this->assertArrayHasKey('amount', $result['purchase_units'][0]);
	}

	/**
	 * GIVEN a PayPal order carrying payer PII
	 * WHEN the handler executes with include_payer_pii true
	 * THEN the payer block passes through unchanged.
	 */
	public function test_execute_passes_payer_through_when_include_payer_pii_is_true(): void
	{
		$order = Mockery::mock(Order::class);
		$order->shouldReceive('to_array')->andReturn(array(
			'id'    => 'ORDERID',
			'payer' => array( 'email_address' => 'payer@example.test' ),
		));

		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldReceive('order')->once()->with('ORDERID')->andReturn($order);

		$result = $this->create_handler($endpoint)->execute(array(
			'paypal_order_id'   => 'ORDERID',
			'include_payer_pii' => true,
		));

		$this->assertArrayHasKey('payer', $result);
		$this->assertSame('payer@example.test', $result['payer']['email_address']);
	}

	/**
	 * GIVEN the backing endpoint throws while looking up the order
	 * WHEN the handler executes
	 * THEN the caller receives a generic WP_Error carrying no exception text,
	 * while the injected logger receives the exception class and message.
	 */
	public function test_execute_returns_a_generic_error_and_logs_the_exception_when_the_endpoint_throws(): void
	{
		$endpoint = Mockery::mock(OrderEndpointCached::class);
		$endpoint->shouldReceive('order')->once()->andThrow(new Exception('PayPal information_link https://api.paypal.com/x leaked here.'));

		$logger = Mockery::mock(LoggerInterface::class);
		$logger->shouldReceive('error')
			->once()
			->with(Mockery::on(static function ($message): bool {
				return is_string($message)
					&& false !== strpos($message, 'Exception')
					&& false !== strpos($message, 'information_link https://api.paypal.com');
			}));

		$handler = new GetPaypalOrderHandler($endpoint, $logger);

		$result = $handler->execute(array( 'paypal_order_id' => 'ORDERID' ));

		$this->assertInstanceOf(WP_Error::class, $result);
		$this->assertStringNotContainsString('information_link', $result->get_error_message());
		$this->assertStringNotContainsString('paypal.com', $result->get_error_message());
	}
}
