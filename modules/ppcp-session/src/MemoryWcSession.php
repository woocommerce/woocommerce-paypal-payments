<?php
/**
 * A WC_Session_Handler subclass for loading the session when it is normally not available (e.g. in webhooks).
 *
 * @package WooCommerce\PayPalCommerce\Session
 *
 * phpcs:disable Generic.Commenting.DocComment
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use WC_Session_Handler;

/**
 * MemoryWcSession class.
 */
class MemoryWcSession extends WC_Session_Handler {
	/**
	 * The session data (from WC()->session->get_session).
	 *
	 * @var array
	 */
	private static $data;

	/**
	 * The customer ID.
	 *
	 * @var string|int
	 */
	private static $customer_id;

	/**
	 * Enqueues this session handler with the given data to be used by WC.
	 *
	 * @param array      $session_data The session data (from WC()->session->get_session).
	 * @param int|string $customer_id The customer ID.
	 */
	public static function replace_session_handler( array $session_data, $customer_id ): void {
		self::$data        = $session_data;
		self::$customer_id = $customer_id;

		add_filter(
			'woocommerce_session_handler',
			function () {
				return MemoryWcSession::class;
			}
		);
	}

	/**
	 * @inerhitDoc
	 */
	public function init_session_cookie() {
		$this->_customer_id = self::$customer_id;
		$this->_data        = self::$data;
	}

	/**
	 * @inerhitDoc
	 */
	public function get_session_data() {
		return self::$data;
	}

	/**
	 * @inerhitDoc
	 */
	public function forget_session() {
		self::$data = array();

		parent::forget_session();
	}
}
