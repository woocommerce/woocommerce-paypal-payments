<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Psr\Log\LoggerInterface;
use WC_Order;

/**
 * Helper class for managing order processing locks to prevent race conditions.
 */
class OrderLockHelper {
	const LOCK_META_KEY = '_ppcp_processing_lock';
	const LOCK_TIMEOUT  = 15;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * OrderLockHelper constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	public function acquire_lock( WC_Order $wc_order ): bool {
		$order_id   = $wc_order->get_id();
		$request_id = $this->get_request_id();

		$this->logger->info(
			sprintf(
				'Attempting to acquire lock for order #%d (request: %s)',
				$order_id,
				$request_id
			)
		);

		$current_time = time();
		$lock_value   = (string) $current_time . ':' . $request_id;

		// Get any existing lock.
		$existing_lock = $wc_order->get_meta( self::LOCK_META_KEY );

		if ( $existing_lock ) {
			$lock_time   = 0;
			$lock_holder = 'unknown';

			// Parse existing lock - expects 'timestamp:request_id' format.
			if ( strpos( $existing_lock, ':' ) !== false ) {
				list( $time_part, $holder_part ) = explode( ':', $existing_lock, 2 );

				// Only update time if it's numeric.
				if ( is_numeric( $time_part ) ) {
					$lock_time = (int) $time_part;
				}
				$lock_holder = $holder_part;
			} else {
				// Corrupted or malformed lock value - log and treat as very old (time 0).
				$this->logger->warning(
					sprintf(
						'⚠️ Found malformed lock value for order #%d: "%s" - treating as expired.',
						$order_id,
						$existing_lock
					)
				);
			}

			$age = $current_time - $lock_time;

			// If the lock is not expired, deny access.
			if ( $age < self::LOCK_TIMEOUT ) {
				$this->logger->info(
					sprintf(
						'✗ Lock already held for order #%d by request %s (age: %ds, current: %s)',
						$order_id,
						$lock_holder,
						$age,
						$request_id
					)
				);
				return false;
			}

			// Lock is expired, we can take it.
			$this->logger->info(
				sprintf(
					'⚠️ Found expired lock for order #%d (holder: %s, age: %ds) - taking over',
					$order_id,
					$lock_holder,
					$age
				)
			);
		}

		// Set our lock (this will overwrite any expired or malformed lock).
		$wc_order->update_meta_data( self::LOCK_META_KEY, $lock_value );
		$wc_order->save_meta_data();

		// Verify we actually have the lock.
		$stored_lock = $wc_order->get_meta( self::LOCK_META_KEY, true );

		if ( $stored_lock === $lock_value ) {
			$this->logger->info(
				sprintf(
					'✓ Lock acquired for order #%d by request %s',
					$order_id,
					$request_id
				)
			);
			return true;
		} else {
			$this->logger->error(
				sprintf(
					'✗ CRITICAL: Failed to verify lock for order #%d - expected: %s, got: %s',
					$order_id,
					$lock_value,
					$stored_lock
				)
			);
			return false;
		}
	}

	/**
	 * Releases the processing lock for the given order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return void
	 */
	public function release_lock( WC_Order $wc_order ): void {
		$order_id   = $wc_order->get_id();
		$request_id = $this->get_request_id();

		// Get the current lock value for debugging.
		$current_lock = $wc_order->get_meta( self::LOCK_META_KEY, true );

		if ( ! $current_lock ) {
			$this->logger->warning(
				sprintf(
					'⚠️ Attempted to release lock for order #%d but no lock exists (request: %s)',
					$order_id,
					$request_id
				)
			);
			return;
		}

		$lock_parts   = explode( ':', $current_lock );
		$lock_request = $lock_parts[1] ?? 'unknown';

		$wc_order->delete_meta_data( self::LOCK_META_KEY );
		$wc_order->save_meta_data();

		$this->logger->info(
			sprintf(
				'✓ Lock released for order #%d (was held by: %s, released by: %s)',
				$order_id,
				$lock_request,
				$request_id
			)
		);
	}

	/**
	 * Checks if the given order has an active processing lock.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return bool True if order is locked, false otherwise.
	 */
	public function is_locked( WC_Order $wc_order ): bool {
		$lock_value = $wc_order->get_meta( self::LOCK_META_KEY, true );

		if ( ! $lock_value ) {
			return false;
		}

		$lock_parts   = explode( ':', $lock_value );
		$lock_time    = (int) $lock_parts[0];
		$lock_request = $lock_parts[1] ?? 'unknown';

		$age       = time() - $lock_time;
		$is_locked = $age < self::LOCK_TIMEOUT;

		if ( $is_locked ) {
			$this->logger->debug(
				sprintf(
					'Order #%d is locked by request %s (age: %ds)',
					$wc_order->get_id(),
					$lock_request,
					$age
				)
			);
		} else {
			$this->logger->debug(
				sprintf(
					'Order #%d has expired lock from request %s (age: %ds)',
					$wc_order->get_id(),
					$lock_request,
					$age
				)
			);
		}

		return $is_locked;
	}

	/**
	 * Get a unique request identifier.
	 *
	 * @return string
	 */
	private function get_request_id(): string {
		static $request_id = null;

		if ( $request_id === null ) {
			// Use a combination of time and random for uniqueness.
			$request_id = substr( md5( (string) microtime( true ) . (string) wp_rand() ), 0, 8 );
		}

		return (string) $request_id;
	}
}
