<?php
/**
 * Authorizes payments for a given WooCommerce order.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class AuthorizedPaymentsProcessor
 */
class AuthorizedPaymentsProcessor {

	const SUCCESSFUL       = 'SUCCESSFUL';
	const ALREADY_CAPTURED = 'ALREADY_CAPTURED';
	const FAILED           = 'FAILED';
	const INACCESSIBLE     = 'INACCESSIBLE';
	const NOT_FOUND        = 'NOT_FOUND';

	/**
	 * The Order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The Payments endpoint.
	 *
	 * @var PaymentsEndpoint
	 */
	private $payments_endpoint;

	/**
	 * The last status.
	 *
	 * @var string
	 */
	private $last_status = '';

	/**
	 * AuthorizedPaymentsProcessor constructor.
	 *
	 * @param OrderEndpoint    $order_endpoint The Order endpoint.
	 * @param PaymentsEndpoint $payments_endpoint The Payments endpoint.
	 */
	public function __construct(
		OrderEndpoint $order_endpoint,
		PaymentsEndpoint $payments_endpoint
	) {

		$this->order_endpoint    = $order_endpoint;
		$this->payments_endpoint = $payments_endpoint;
	}

	/**
	 * Process a WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @return bool
	 */
	public function process( \WC_Order $wc_order ): bool {
		try {
			$order = $this->paypal_order_from_wc_order( $wc_order );
		} catch ( Exception $exception ) {
			if ( $exception->getCode() === 404 ) {
				$this->last_status = self::NOT_FOUND;
				return false;
			}
			$this->last_status = self::INACCESSIBLE;
			return false;
		}

		$authorizations = $this->all_authorizations( $order );

		if ( ! $this->are_authorzations_to_capture( ...$authorizations ) ) {
			$this->last_status = self::ALREADY_CAPTURED;
			return false;
		}

		try {
			$this->capture_authorizations( ...$authorizations );
		} catch ( Exception $exception ) {
			$this->last_status = self::FAILED;
			return false;
		}

		$this->last_status = self::SUCCESSFUL;
		return true;
	}

	/**
	 * Returns the last status.
	 *
	 * @return string
	 */
	public function last_status(): string {

		return $this->last_status;
	}

	/**
	 * Returns the PayPal order from a given WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @return Order
	 */
	private function paypal_order_from_wc_order( \WC_Order $wc_order ): Order {
		$order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		return $this->order_endpoint->order( $order_id );
	}

	/**
	 * Returns all Authorizations from an order.
	 *
	 * @param Order $order The order.
	 *
	 * @return array
	 */
	private function all_authorizations( Order $order ): array {
		$authorizations = array();
		foreach ( $order->purchase_units() as $purchase_unit ) {
			foreach ( $purchase_unit->payments()->authorizations() as $authorization ) {
				$authorizations[] = $authorization;
			}
		}

		return $authorizations;
	}

	/**
	 * Whether Authorizations need to be captured.
	 *
	 * @param Authorization ...$authorizations All Authorizations.
	 *
	 * @return bool
	 */
	private function are_authorzations_to_capture( Authorization ...$authorizations ): bool {
		return (bool) count( $this->authorizations_to_capture( ...$authorizations ) );
	}

	/**
	 * Captures the authorizations.
	 *
	 * @param Authorization ...$authorizations All authorizations.
	 */
	private function capture_authorizations( Authorization ...$authorizations ) {
		$uncaptured_authorizations = $this->authorizations_to_capture( ...$authorizations );
		foreach ( $uncaptured_authorizations as $authorization ) {
			$this->payments_endpoint->capture( $authorization->id() );
		}
	}

	/**
	 * The authorizations which need to be captured.
	 *
	 * @param Authorization ...$authorizations All Authorizations.
	 * @return Authorization[]
	 */
	private function authorizations_to_capture( Authorization ...$authorizations ): array {
		return array_filter(
			$authorizations,
			static function ( Authorization $authorization ): bool {
				return $authorization->status()->is( AuthorizationStatus::CREATED )
					|| $authorization->status()->is( AuthorizationStatus::PENDING );
			}
		);
	}
}
