<?php
/**
 * The Session Handler.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;

/**
 * Class SessionHandler
 */
class SessionHandler {

	const ID = 'ppcp';

	/**
	 * The Order.
	 *
	 * @var Order|null
	 */
	private $order;

	/**
	 * The BN Code.
	 *
	 * @var string
	 */
	private $bn_code = '';

	/**
	 * If PayPal respondes with INSTRUMENT_DECLINED, we only
	 * want to go max. three times through the process of trying again.
	 *
	 * @var int
	 */
	private $insufficient_funding_tries = 0;

	/**
	 * The funding source of the current checkout (venmo, ...) or null.
	 *
	 * @var string|null
	 */
	private $funding_source = null;

	/**
	 * Returns the order.
	 *
	 * @return Order|null
	 */
	public function order() {
		return $this->order;
	}

	/**
	 * Replaces the current order.
	 *
	 * @param Order $order The new order.
	 *
	 * @return SessionHandler
	 */
	public function replace_order( Order $order ) : SessionHandler {
		$this->order = $order;
		$this->store_session();
		return $this;
	}

	/**
	 * Returns the BN Code.
	 *
	 * @return string
	 */
	public function bn_code() : string {
		return $this->bn_code;
	}

	/**
	 * Replaces the BN Code.
	 *
	 * @param string $bn_code The new BN Code.
	 *
	 * @return SessionHandler
	 */
	public function replace_bn_code( string $bn_code ) : SessionHandler {
		$this->bn_code = $bn_code;
		$this->store_session();
		return $this;
	}

	/**
	 * Returns the funding source of the current checkout (venmo, ...) or null.
	 *
	 * @return string|null
	 */
	public function funding_source(): ?string {
		return $this->funding_source;
	}

	/**
	 * Replaces the funding source of the current checkout.
	 *
	 * @param string|null $funding_source The funding source.
	 *
	 * @return SessionHandler
	 */
	public function replace_funding_source( ?string $funding_source ): SessionHandler {
		$this->funding_source = $funding_source;
		$this->store_session();
		return $this;
	}

	/**
	 * Returns how many times the customer tried to use the PayPal Gateway in this session.
	 *
	 * @return int
	 */
	public function insufficient_funding_tries() : int {
		return $this->insufficient_funding_tries;
	}

	/**
	 * Increments the number of tries, the customer has done in this session.
	 *
	 * @return SessionHandler
	 */
	public function increment_insufficient_funding_tries() : SessionHandler {
		$this->insufficient_funding_tries++;
		$this->store_session();
		return $this;
	}

	/**
	 * Destroys the session data.
	 *
	 * @return SessionHandler
	 */
	public function destroy_session_data() : SessionHandler {
		$this->order                      = null;
		$this->bn_code                    = '';
		$this->insufficient_funding_tries = 0;
		$this->funding_source             = null;
		$this->store_session();
		return $this;
	}

	/**
	 * Stores the session.
	 */
	private function store_session() {
		WC()->session->set( self::ID, $this );
	}
}
