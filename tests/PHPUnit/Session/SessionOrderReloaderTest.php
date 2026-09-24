<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Mockery;
use Psr\Log\LoggerInterface;
use stdClass;
use WC_Session;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Session\SessionOrderReloader
 */
class SessionOrderReloaderTest extends TestCase
{
	private const INTERVAL = 30;

	private const NOW = 1700000000;

	private OrderEndpoint $order_endpoint;

	private LoggerInterface $logger;

	private SessionHandler $session_handler;

	public function setUp(): void
	{
		parent::setUp();

		$this->order_endpoint   = Mockery::mock(OrderEndpoint::class);
		$this->logger           = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
		$this->session_handler  = Mockery::mock(SessionHandler::class);
	}

	private function create_reloader(): SessionOrderReloader
	{
		return new SessionOrderReloader(
			$this->order_endpoint,
			$this->logger,
			self::INTERVAL
		);
	}

	/**
	 * @param array<string, mixed> $store
	 */
	private function session_with(array &$store): WC_Session
	{
		$session = Mockery::mock(WC_Session::class);

		$session->shouldReceive('get')->andReturnUsing(
			static function (string $key) use (&$store) {
				return $store[$key] ?? null;
			}
		);

		$session->shouldReceive('set')->andReturnUsing(
			static function (string $key, $value) use (&$store): void {
				$store[$key] = $value;
			}
		);

		return $session;
	}

	private function order_with(string $id, string $status): Order
	{
		$order = Mockery::mock(Order::class);
		$order->shouldReceive('id')->andReturn($id);
		$order->shouldReceive('status')->andReturn(new OrderStatus($status));

		return $order;
	}

	/**
	 * GIVEN a session order that is still CREATED and was never reloaded before
	 * WHEN maybe_reload is called
	 * THEN the order is fetched again from PayPal and the session handler replaces it
	 * AND the session records the reload time for that order id
	 */
	public function test_fetches_and_replaces_created_order_never_reloaded_before(): void
	{
		$store = [];
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));
		when('time')->justReturn(self::NOW);

		$order       = $this->order_with('WC-ORDER-1', OrderStatus::CREATED);
		$fresh_order = Mockery::mock(Order::class);

		$this->order_endpoint->shouldReceive('order')->once()->with('WC-ORDER-1')->andReturn($fresh_order);
		$this->session_handler->shouldReceive('replace_order')->once()->with($fresh_order);

		$this->create_reloader()->maybe_reload($order, $this->session_handler);

		$this->assertSame(
			array(
				'order_id' => 'WC-ORDER-1',
				'time'     => self::NOW,
			),
			$store[SessionOrderReloader::LAST_RELOAD_SESSION_KEY]
		);
	}

	/**
	 * GIVEN a session order that already reached a terminal PayPal status
	 * WHEN maybe_reload is called
	 * THEN PayPal is never queried and the order is left untouched
	 *
	 * @dataProvider terminal_status_provider
	 */
	public function test_skips_terminal_statuses(string $status): void
	{
		$store = [];
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));

		$order = $this->order_with('WC-ORDER-1', $status);

		$this->order_endpoint->shouldNotReceive('order');
		$this->session_handler->shouldNotReceive('replace_order');

		$this->create_reloader()->maybe_reload($order, $this->session_handler);

		$this->assertArrayNotHasKey(SessionOrderReloader::LAST_RELOAD_SESSION_KEY, $store);
	}

	public function terminal_status_provider(): array
	{
		return array(
			'approved order is left alone'  => array(OrderStatus::APPROVED),
			'completed order is left alone' => array(OrderStatus::COMPLETED),
			'voided order is left alone'    => array(OrderStatus::VOIDED),
		);
	}

	/**
	 * GIVEN no session order, or a session order with no active WooCommerce session
	 * WHEN maybe_reload is called
	 * THEN nothing is fetched and nothing is stored
	 */
	public function test_skips_null_order(): void
	{
		$store = [];
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));

		$this->order_endpoint->shouldNotReceive('order');
		$this->session_handler->shouldNotReceive('replace_order');

		$this->create_reloader()->maybe_reload(null, $this->session_handler);

		$this->assertSame(array(), $store);
	}

	/**
	 * GIVEN a WooCommerce instance whose session property is not set
	 * WHEN maybe_reload is called with a reloadable order
	 * THEN nothing is fetched because there is no session to read or write
	 */
	public function test_skips_when_wc_session_is_not_set(): void
	{
		when('WC')->justReturn((object) array());

		$order = $this->order_with('WC-ORDER-1', OrderStatus::CREATED);

		$this->order_endpoint->shouldNotReceive('order');
		$this->session_handler->shouldNotReceive('replace_order');

		$this->create_reloader()->maybe_reload($order, $this->session_handler);

		$this->addToAssertionCount(1);
	}

	/**
	 * GIVEN a reloader instance that already performed one fetch
	 * WHEN maybe_reload is called again on the same instance
	 * THEN the second call does nothing, even for a fresh reloadable order
	 */
	public function test_only_reloads_once_per_instance(): void
	{
		$store = [];
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));

		$order       = $this->order_with('WC-ORDER-1', OrderStatus::CREATED);
		$other_order = $this->order_with('WC-ORDER-2', OrderStatus::CREATED);
		$fresh_order = Mockery::mock(Order::class);

		$this->order_endpoint->shouldReceive('order')->once()->with('WC-ORDER-1')->andReturn($fresh_order);
		$this->session_handler->shouldReceive('replace_order')->once()->with($fresh_order);

		$reloader = $this->create_reloader();
		$reloader->maybe_reload($order, $this->session_handler);
		$reloader->maybe_reload($other_order, $this->session_handler);

		$this->addToAssertionCount(1);
	}

	/**
	 * GIVEN the same order was already reloaded a number of seconds ago
	 * WHEN a new reloader instance evaluates it again
	 * THEN it is skipped while inside the interval, fetched again once the interval elapses,
	 * AND a different order id within the interval is fetched regardless
	 *
	 * @dataProvider repeat_reload_provider
	 */
	public function test_reload_across_instances_respects_interval(
		string $stored_order_id,
		int $seconds_since_stored,
		string $requested_order_id,
		bool $expects_fetch
	): void {
		$store = array(
			SessionOrderReloader::LAST_RELOAD_SESSION_KEY => array(
				'order_id' => $stored_order_id,
				'time'     => self::NOW - $seconds_since_stored,
			),
		);
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));
		when('time')->justReturn(self::NOW);

		$order       = $this->order_with($requested_order_id, OrderStatus::CREATED);
		$fresh_order = Mockery::mock(Order::class);

		if ($expects_fetch) {
			$this->order_endpoint->shouldReceive('order')->once()->with($requested_order_id)->andReturn($fresh_order);
			$this->session_handler->shouldReceive('replace_order')->once()->with($fresh_order);
		} else {
			$this->order_endpoint->shouldNotReceive('order');
			$this->session_handler->shouldNotReceive('replace_order');
		}

		$this->create_reloader()->maybe_reload($order, $this->session_handler);

		$this->addToAssertionCount(1);
	}

	public function repeat_reload_provider(): array
	{
		return array(
			'same order one second before interval elapses is skipped' => array('WC-ORDER-1', self::INTERVAL - 1, 'WC-ORDER-1', false),
			'same order exactly at interval boundary is fetched'       => array('WC-ORDER-1', self::INTERVAL, 'WC-ORDER-1', true),
			'different order within interval is fetched'    => array('WC-ORDER-1', 5, 'WC-ORDER-2', true),
		);
	}

	/**
	 * GIVEN PayPal responds that the order no longer exists via a generic runtime exception
	 * WHEN maybe_reload attempts to fetch it
	 * THEN only the session order is forgotten, no replacement is stored,
	 *      and the rest of the session data is left untouched
	 * AND no warning is logged for this expected condition
	 */
	public function test_forgets_session_order_on_runtime_exception_with_404_code(): void
	{
		$store = [];
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));

		$order = $this->order_with('WC-ORDER-1', OrderStatus::CREATED);

		$this->order_endpoint->shouldReceive('order')->once()->with('WC-ORDER-1')
			->andThrow(new RuntimeException('Not found', 404));

		$this->session_handler->shouldReceive('forget_order')->once();
		$this->session_handler->shouldNotReceive('destroy_session_data');
		$this->session_handler->shouldNotReceive('replace_order');
		$this->logger->shouldNotReceive('warning');

		$this->create_reloader()->maybe_reload($order, $this->session_handler);

		$this->addToAssertionCount(1);
	}

	/**
	 * GIVEN PayPal responds with a PayPalApiException identifying the order as gone
	 * WHEN maybe_reload attempts to fetch it
	 * THEN only the session order is forgotten regardless of whether the exception
	 *      identifies the condition via HTTP status or via the RESOURCE_NOT_FOUND name
	 *
	 * @dataProvider not_found_api_exception_provider
	 */
	public function test_forgets_session_order_on_not_found_api_exception(int $status_code, string $name): void
	{
		$store = [];
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));

		$order = $this->order_with('WC-ORDER-1', OrderStatus::CREATED);

		$response       = new stdClass();
		$response->name = $name;

		$this->order_endpoint->shouldReceive('order')->once()->with('WC-ORDER-1')
			->andThrow(new PayPalApiException($response, $status_code));

		$this->session_handler->shouldReceive('forget_order')->once();
		$this->session_handler->shouldNotReceive('destroy_session_data');
		$this->session_handler->shouldNotReceive('replace_order');

		$this->create_reloader()->maybe_reload($order, $this->session_handler);

		$this->addToAssertionCount(1);
	}

	public function not_found_api_exception_provider(): array
	{
		return array(
			'identified by 404 status code'       => array(404, 'SOME_OTHER_NAME'),
			'identified by RESOURCE_NOT_FOUND name' => array(500, 'RESOURCE_NOT_FOUND'),
		);
	}

	/**
	 * GIVEN PayPal fails for a reason unrelated to the order being gone
	 * WHEN maybe_reload attempts to fetch it
	 * THEN the failure is logged as a warning and the session order is left in place
	 */
	public function test_logs_warning_and_keeps_session_on_other_exception(): void
	{
		$store = [];
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));

		$order = $this->order_with('WC-ORDER-1', OrderStatus::CREATED);

		$this->order_endpoint->shouldReceive('order')->once()->with('WC-ORDER-1')
			->andThrow(new RuntimeException('Server error', 500));

		$this->session_handler->shouldNotReceive('forget_order');
		$this->session_handler->shouldNotReceive('destroy_session_data');
		$this->session_handler->shouldNotReceive('replace_order');
		$this->logger->shouldReceive('warning')->once();

		$this->create_reloader()->maybe_reload($order, $this->session_handler);

		$this->addToAssertionCount(1);
	}
}
