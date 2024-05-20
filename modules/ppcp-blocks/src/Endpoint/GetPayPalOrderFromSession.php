<?php
/**
 * Get PayPal order from the current session.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks\Endpoint;

use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Session\SessionHandler;

class GetPayPalOrderFromSession implements EndpointInterface
{
	const ENDPOINT = 'ppc-get-paypal-order-from-session';

	/**
	 * The session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	public function __construct(SessionHandler $session_handler)
	{
		$this->session_handler = $session_handler;
	}

	public static function nonce(): string
	{
		return self::ENDPOINT;
	}

	public function handle_request(): bool
	{
		$order = $this->session_handler->order();

		wp_send_json_success($order->id());
		return true;
	}
}
