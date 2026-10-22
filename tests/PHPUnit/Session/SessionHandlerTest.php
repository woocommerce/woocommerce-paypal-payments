<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Mockery;
use WC_Session;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Session\SessionHandler
 */
class SessionHandlerTest extends TestCase
{
	private SessionHandler $testee;

	public function setUp(): void
	{
		parent::setUp();

		$store = array();
		when('WC')->justReturn((object) array('session' => $this->session_with($store)));

		$this->testee = new SessionHandler();
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

	/**
	 * GIVEN a session in which a PayPal order was recorded as created by this server
	 * WHEN the same order ID is checked
	 * THEN the order is recognized
	 */
	public function test_recorded_order_is_recognized(): void
	{
		$this->testee->remember_created_order('ORDER-1');

		$this->assertTrue($this->testee->is_created_order('ORDER-1'));
	}

	/**
	 * GIVEN a session in which one order was recorded
	 * WHEN an ID that was never recorded is checked
	 * THEN the order is not recognized
	 */
	public function test_unrecorded_order_is_not_recognized(): void
	{
		$this->testee->remember_created_order('ORDER-1');

		$this->assertFalse($this->testee->is_created_order('ORDER-2'));
	}

	/**
	 * GIVEN a session in which an order was recorded
	 * WHEN an empty order ID is checked
	 * THEN the order is not recognized
	 */
	public function test_empty_order_id_is_not_recognized(): void
	{
		$this->testee->remember_created_order('ORDER-1');

		$this->assertFalse($this->testee->is_created_order(''));
	}

	/**
	 * GIVEN a session in which an empty order ID was recorded
	 * WHEN an empty order ID is checked
	 * THEN the order is still not recognized
	 */
	public function test_recorded_empty_order_id_is_not_recognized(): void
	{
		$this->testee->remember_created_order('');

		$this->assertFalse($this->testee->is_created_order(''));
	}

	/**
	 * GIVEN a session in which an order was recorded
	 * WHEN the session data is destroyed
	 * THEN the earlier order is no longer recognized
	 */
	public function test_no_earlier_order_is_recognized_after_session_data_is_destroyed(): void
	{
		$this->testee->remember_created_order('ORDER-1');

		$this->testee->destroy_session_data();

		$this->assertFalse($this->testee->is_created_order('ORDER-1'));
	}

	/**
	 * GIVEN a session in which an order was recorded
	 * WHEN a newer order is recorded afterwards
	 * THEN the older order is no longer recognized
	 * AND the newer order is recognized
	 */
	public function test_newer_order_replaces_older_order(): void
	{
		$this->testee->remember_created_order('ORDER-1');
		$this->testee->remember_created_order('ORDER-2');

		$this->assertFalse($this->testee->is_created_order('ORDER-1'));
		$this->assertTrue($this->testee->is_created_order('ORDER-2'));
	}
}
