<?php
/**
 * Refreshes the PayPal order stored in the session.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;

/**
 * Re-fetches a pending session order so that an approval made outside the
 * buttons (e.g. an APM that never redirects back) is picked up.
 *
 * The session order is read on almost every request, so the fetch is limited
 * to once per interval per order, and an order PayPal no longer knows about
 * is dropped from the session instead of being fetched again.
 */
class SessionOrderReloader {

	public const LAST_RELOAD_SESSION_KEY = 'ppcp_session_order_last_reload';

	private const TERMINAL_STATUSES = array(
		OrderStatus::APPROVED,
		OrderStatus::COMPLETED,
		OrderStatus::VOIDED,
	);

	private OrderEndpoint $order_endpoint;

	private LoggerInterface $logger;

	private int $reload_interval;

	private bool $reloaded = false;

	/**
	 * @param OrderEndpoint   $order_endpoint  The order endpoint.
	 * @param LoggerInterface $logger          The logger.
	 * @param int             $reload_interval Minimum seconds between two fetches of the same order.
	 */
	public function __construct(
		OrderEndpoint $order_endpoint,
		LoggerInterface $logger,
		int $reload_interval
	) {
		$this->order_endpoint  = $order_endpoint;
		$this->logger          = $logger;
		$this->reload_interval = $reload_interval;
	}

	public function maybe_reload( ?Order $order, SessionHandler $session_handler ): void {
		if ( ! isset( WC()->session ) || $this->reloaded || ! $order ) {
			return;
		}

		foreach ( self::TERMINAL_STATUSES as $status ) {
			if ( $order->status()->is( $status ) ) {
				return;
			}
		}

		$now = time();
		if ( $this->reloaded_recently( $order->id(), $now ) ) {
			return;
		}

		$this->reloaded = true;
		WC()->session->set(
			self::LAST_RELOAD_SESSION_KEY,
			array(
				'order_id' => $order->id(),
				'time'     => $now,
			)
		);

		try {
			$session_handler->replace_order( $this->order_endpoint->order( $order->id() ) );
		} catch ( Throwable $exception ) {
			if ( $this->is_not_found( $exception ) ) {
				$this->logger->info( sprintf( 'PayPal order %s no longer exists, removing it from the session.', $order->id() ) );
				$session_handler->forget_order();
				return;
			}

			$this->logger->warning( 'Failed to reload PayPal order in the session: ' . $exception->getMessage() );
		}
	}

	private function reloaded_recently( string $order_id, int $now ): bool {
		$last_reload = WC()->session->get( self::LAST_RELOAD_SESSION_KEY );
		if ( ! is_array( $last_reload ) || ( $last_reload['order_id'] ?? '' ) !== $order_id ) {
			return false;
		}

		return $now - (int) ( $last_reload['time'] ?? 0 ) < $this->reload_interval;
	}

	private function is_not_found( Throwable $exception ): bool {
		if ( $exception instanceof PayPalApiException ) {
			return $exception->status_code() === 404 || $exception->name() === 'RESOURCE_NOT_FOUND';
		}

		return $exception->getCode() === 404;
	}
}
