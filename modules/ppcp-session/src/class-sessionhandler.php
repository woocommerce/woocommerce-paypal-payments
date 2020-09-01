<?php
/**
 * The Session Handler.
 *
 * @package Inpsyde\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;

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
	 * Returns the order.
	 *
	 * @return Order|null
	 */
	public function order() : ?Order {
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
	 * Destroys the session data.
	 *
	 * @return SessionHandler
	 */
	public function destroy_session_data() : SessionHandler {
		$this->order   = null;
		$this->bn_code = '';
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
