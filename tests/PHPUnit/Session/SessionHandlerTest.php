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

	/**
	 * GIVEN a fresh session
	 * WHEN it is asked whether a free-trial PayPal account was saved
	 * THEN the answer is no
	 */
	public function test_free_trial_is_not_vaulted_by_default(): void
	{
		$this->assertFalse($this->testee->is_free_trial_vaulted());
	}

	/**
	 * GIVEN a fresh session
	 * WHEN the free trial is marked as vaulted
	 * THEN it is reported as vaulted
	 * AND a new handler reading the same session reports it as vaulted too
	 */
	public function test_marked_free_trial_is_vaulted_and_persisted(): void
	{
		$this->testee->mark_free_trial_vaulted_with_paypal();

		$this->assertTrue($this->testee->is_free_trial_vaulted());
		$this->assertTrue((new SessionHandler())->is_free_trial_vaulted());
	}

	/**
	 * GIVEN a session whose earlier checkout attempt used a given funding source
	 * WHEN the free trial is marked as vaulted
	 * THEN the funding source is PayPal, because only PayPal accounts are vaulted for free trials
	 *
	 * @dataProvider earlier_funding_source_provider
	 */
	public function test_marking_free_trial_vaulted_sets_funding_source_to_paypal(?string $earlier_source): void
	{
		$this->testee->replace_funding_source($earlier_source);

		$this->testee->mark_free_trial_vaulted_with_paypal();

		$this->assertSame('paypal', $this->testee->funding_source());
	}

	public function earlier_funding_source_provider(): array
	{
		return array(
			'no earlier funding source' => array(null),
			'earlier venmo attempt'     => array('venmo'),
		);
	}

	/**
	 * GIVEN a session in which the free trial was marked as vaulted
	 * WHEN the free trial vaulting is forgotten
	 * THEN the free trial is no longer vaulted
	 * AND the funding source is kept
	 */
	public function test_forgotten_free_trial_is_not_vaulted_and_keeps_funding_source(): void
	{
		$this->testee->mark_free_trial_vaulted_with_paypal();

		$this->testee->forget_free_trial_vaulted();

		$this->assertFalse($this->testee->is_free_trial_vaulted());
		$this->assertFalse((new SessionHandler())->is_free_trial_vaulted());
		$this->assertSame('paypal', $this->testee->funding_source());
	}

	/**
	 * GIVEN a session in which the free trial was marked as vaulted
	 * WHEN the session data is destroyed
	 * THEN the free trial is no longer vaulted
	 */
	public function test_free_trial_is_not_vaulted_after_session_data_is_destroyed(): void
	{
		$this->testee->mark_free_trial_vaulted_with_paypal();

		$this->testee->destroy_session_data();

		$this->assertFalse($this->testee->is_free_trial_vaulted());
		$this->assertFalse((new SessionHandler())->is_free_trial_vaulted());
	}
}
