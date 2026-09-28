<?php
/**
 * Refreshes the PayPal order stored in the session.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Session;

use Psr\Log\LoggerInterface;
use Throwable;
use WC_Session_Handler;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;

/**
 * Re-fetches a pending session order on the checkout page, so that an approval
 * made outside the buttons (e.g. an APM that never redirects back) is picked up.
 * The session order is read on almost every request, so fetches are throttled
 * per order, and an order that PayPal no longer knows is removed from the session.
 */
class SessionOrderReloader {

	public const LAST_RELOAD_SESSION_KEY = 'ppcp_session_order_last_reload';

	private const RELOAD_INTERVAL_SECONDS = 15;

	private const STATUSES_WITHOUT_RELOAD = array(
		OrderStatus::APPROVED,
		OrderStatus::COMPLETED,
		OrderStatus::VOIDED,
	);

	private OrderEndpoint $order_endpoint;

	private LoggerInterface $logger;

	private bool $reloaded = false;

	public function __construct(
		OrderEndpoint $order_endpoint,
		LoggerInterface $logger
	) {
		$this->order_endpoint = $order_endpoint;
		$this->logger         = $logger;
	}

	public function maybe_reload( ?Order $order, SessionHandler $session_handler ): void {
		if ( $this->reloaded || ! $order || ! isset( WC()->session ) ) {
			return;
		}

		foreach ( self::STATUSES_WITHOUT_RELOAD as $status ) {
			if ( $order->status()->is( $status ) ) {
				return;
			}
		}

		if ( ! $this->is_checkout_request() ) {
			return;
		}

		$order_id = $order->id();
		if ( $this->reloaded_recently( $order_id ) ) {
			return;
		}

		$this->mark_as_reloaded( $order_id );

		try {
			$session_handler->replace_order( $this->order_endpoint->order( $order_id ) );
		} catch ( Throwable $exception ) {
			if ( $this->is_not_found( $exception ) ) {
				$this->logger->info( sprintf( 'PayPal order %s no longer exists, removing it from the session.', $order_id ) );
				$session_handler->forget_order();

				return;
			}

			$this->logger->warning( 'Failed to reload PayPal order in the session: ' . $exception->getMessage() );
		}
	}

	/**
	 * The checkout page is known only after `wp` parsed the main query, so the first
	 * session read after `wp` does the reload. REST requests never fire `wp` and do
	 * not need the reload.
	 *
	 * Checkout AJAX requests also pass `is_checkout()`, but skip the PayPal GET:
	 * the page load already refreshed the session order.
	 */
	private function is_checkout_request(): bool {
		if ( ! did_action( 'wp' ) ) {
			return false;
		}

		return ! wp_doing_ajax() && is_checkout();
	}

	private function reloaded_recently( string $order_id ): bool {
		$last_reload = WC()->session->get( self::LAST_RELOAD_SESSION_KEY );
		if ( ! is_array( $last_reload ) || ( $last_reload['order_id'] ?? '' ) !== $order_id ) {
			return false;
		}

		$last_reload_age = time() - (int) ( $last_reload['time'] ?? 0 );

		return $last_reload_age < self::RELOAD_INTERVAL_SECONDS;
	}

	private function mark_as_reloaded( string $order_id ): void {
		$this->reloaded = true;

		WC()->session->set(
			self::LAST_RELOAD_SESSION_KEY,
			array(
				'order_id' => $order_id,
				'time'     => time(),
			)
		);

		// WooCommerce saves the session at shutdown; save now so concurrent requests see the mark.
		if ( WC()->session instanceof WC_Session_Handler ) {
			WC()->session->save_data();
		}
	}

	/**
	 * The endpoint also uses code 404 for an empty response body. That order is lost
	 * too; the next button click creates a new one.
	 */
	private function is_not_found( Throwable $exception ): bool {
		if ( $exception instanceof PayPalApiException ) {
			return $exception->status_code() === 404 || $exception->name() === 'RESOURCE_NOT_FOUND';
		}

		return $exception->getCode() === 404;
	}
}
