<?php
/**
 * Authorizes payments for a given WooCommerce order.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;

/**
 * Class AuthorizedPaymentsProcessor
 */
class AuthorizedPaymentsProcessor {

	use PaymentsStatusHandlingTrait;

	const SUCCESSFUL        = 'SUCCESSFUL';
	const ALREADY_CAPTURED  = 'ALREADY_CAPTURED';
	const FAILED            = 'FAILED';
	const INACCESSIBLE      = 'INACCESSIBLE';
	const NOT_FOUND         = 'NOT_FOUND';
	const BAD_AUTHORIZATION = 'BAD_AUTHORIZATION';

	const CAPTURED_META_KEY = '_ppcp_paypal_captured';

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
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The capture results.
	 *
	 * @var Capture[]
	 */
	private $captures;

	/**
	 * The notice.
	 *
	 * @var AuthorizeOrderActionNotice
	 */
	private $notice;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	private $config;

	/**
	 * The subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	private $subscription_helper;

	/**
	 * AuthorizedPaymentsProcessor constructor.
	 *
	 * @param OrderEndpoint              $order_endpoint The Order endpoint.
	 * @param PaymentsEndpoint           $payments_endpoint The Payments endpoint.
	 * @param LoggerInterface            $logger The logger.
	 * @param AuthorizeOrderActionNotice $notice The notice.
	 * @param ContainerInterface         $config The settings.
	 * @param SubscriptionHelper         $subscription_helper The subscription helper.
	 */
	public function __construct(
		OrderEndpoint $order_endpoint,
		PaymentsEndpoint $payments_endpoint,
		LoggerInterface $logger,
		AuthorizeOrderActionNotice $notice,
		ContainerInterface $config,
		SubscriptionHelper $subscription_helper
	) {

		$this->order_endpoint      = $order_endpoint;
		$this->payments_endpoint   = $payments_endpoint;
		$this->logger              = $logger;
		$this->notice              = $notice;
		$this->config              = $config;
		$this->subscription_helper = $subscription_helper;
	}

	/**
	 * Process a WooCommerce order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 *
	 * @return string One of the AuthorizedPaymentsProcessor status constants.
	 */
	public function process( WC_Order $wc_order ): string {
		$this->captures = array();

		try {
			$order = $this->paypal_order_from_wc_order( $wc_order );
		} catch ( Exception $exception ) {
			if ( $exception->getCode() === 404 ) {
				return self::NOT_FOUND;
			}
			return self::INACCESSIBLE;
		}

		$authorizations = $this->all_authorizations( $order );

		if ( ! $this->authorizations_to_capture( ...$authorizations ) ) {
			if ( $this->captured_authorizations( ...$authorizations ) ) {
				return self::ALREADY_CAPTURED;
			}

			return self::BAD_AUTHORIZATION;
		}

		try {
			$this->captures[] = $this->capture_authorization( $wc_order, ...$authorizations );
		} catch ( Exception $exception ) {
			$this->logger->error( 'Failed to capture authorization: ' . $exception->getMessage() );
			return self::FAILED;
		}

		return self::SUCCESSFUL;
	}

	/**
	 * Returns the capture results.
	 *
	 * @return Capture[]
	 */
	public function captures(): array {
		return $this->captures;
	}

	/**
	 * Captures an authorized payment for an WooCommerce order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 *
	 * @return bool
	 */
	public function capture_authorized_payment( WC_Order $wc_order ): bool {
		$result_status = $this->process( $wc_order );
		$this->render_authorization_message_for_status( $result_status );

		if ( self::ALREADY_CAPTURED === $result_status ) {
			if ( $wc_order->get_status() === 'on-hold' ) {
				$wc_order->add_order_note(
					__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
				);
			}

			$wc_order->update_meta_data( self::CAPTURED_META_KEY, 'true' );
			$wc_order->save();
			$wc_order->payment_complete();
			return true;
		}

		$captures = $this->captures();
		if ( empty( $captures ) ) {
			return false;
		}

		$capture = end( $captures );

		$this->handle_capture_status( $capture, $wc_order );

		if ( self::SUCCESSFUL === $result_status ) {
			if ( $capture->status()->is( CaptureStatus::COMPLETED ) ) {
				$wc_order->add_order_note(
					__( 'Payment successfully captured.', 'woocommerce-paypal-payments' )
				);
			}
			$wc_order->update_meta_data( self::CAPTURED_META_KEY, 'true' );
			$wc_order->save();
			return true;
		}

		return false;
	}

	/**
	 * Captures the authorized payments for the given customer.
	 *
	 * @param int $customer_id The customer id.
	 */
	public function capture_authorized_payments_for_customer( int $customer_id ): void {

		$wc_orders = wc_get_orders(
			array(
				'customer_id' => $customer_id,
				'status'      => array( 'wc-on-hold' ),
				'limit'       => -1,
			)
		);

		if (
			$this->config->has( 'intent' )
			&& strtoupper( (string) $this->config->get( 'intent' ) ) === 'CAPTURE'
			&& is_array( $wc_orders )
		) {
			foreach ( $wc_orders as $wc_order ) {
				if (
					$this->subscription_helper->has_subscription( $wc_order->get_id() )
					&& $wc_order->get_meta( '_ppcp_captured_vault_webhook' ) === 'false'
				) {
					$this->capture_authorized_payment( $wc_order );
					$wc_order->update_meta_data( '_ppcp_captured_vault_webhook', 'true' );
				}
			}
		}
	}

	/**
	 * Voids authorizations for the given PayPal order.
	 *
	 * @param Order $order The PayPal order.
	 * @return void
	 * @throws RuntimeException When there is a problem voiding authorizations.
	 */
	public function void_authorizations( Order $order ): void {
		$purchase_units = $order->purchase_units();
		if ( ! $purchase_units ) {
			throw new RuntimeException( 'No purchase units.' );
		}

		$payments = $purchase_units[0]->payments();
		if ( ! $payments ) {
			throw new RuntimeException( 'No payments.' );
		}

		$voidable_authorizations = array_filter(
			$payments->authorizations(),
			function ( Authorization $authorization ): bool {
				return $authorization->is_voidable();
			}
		);
		if ( ! $voidable_authorizations ) {
			throw new RuntimeException( 'No voidable authorizations.' );
		}

		foreach ( $voidable_authorizations as $authorization ) {
			$this->payments_endpoint->void( $authorization );
		}
	}

	/**
	 * Displays the notice for a status.
	 *
	 * @param string $status The status.
	 */
	private function render_authorization_message_for_status( string $status ): void {

		$message_mapping = array(
			self::SUCCESSFUL        => AuthorizeOrderActionNotice::SUCCESS,
			self::ALREADY_CAPTURED  => AuthorizeOrderActionNotice::ALREADY_CAPTURED,
			self::INACCESSIBLE      => AuthorizeOrderActionNotice::NO_INFO,
			self::NOT_FOUND         => AuthorizeOrderActionNotice::NOT_FOUND,
			self::BAD_AUTHORIZATION => AuthorizeOrderActionNotice::BAD_AUTHORIZATION,
		);
		$display_message = ( isset( $message_mapping[ $status ] ) ) ?
			$message_mapping[ $status ]
			: AuthorizeOrderActionNotice::FAILED;
		$this->notice->display_message( $display_message );
	}

	/**
	 * Returns the PayPal order from a given WooCommerce order.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 *
	 * @return Order
	 */
	private function paypal_order_from_wc_order( WC_Order $wc_order ): Order {
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
	 * Captures the authorization.
	 *
	 * @param WC_Order      $order The order.
	 * @param Authorization ...$authorizations All authorizations.
	 * @throws Exception If capture failed.
	 */
	private function capture_authorization( WC_Order $order, Authorization ...$authorizations ): Capture {
		$uncaptured_authorizations = $this->authorizations_to_capture( ...$authorizations );
		if ( ! $uncaptured_authorizations ) {
			throw new Exception( 'No authorizations to capture.' );
		}

		$authorization = end( $uncaptured_authorizations );

		return $this->payments_endpoint->capture( $authorization->id(), new Money( (float) $order->get_total(), $order->get_currency() ) );
	}

	/**
	 * The authorizations which need to be captured.
	 *
	 * @param Authorization ...$authorizations All Authorizations.
	 * @return Authorization[]
	 */
	private function authorizations_to_capture( Authorization ...$authorizations ): array {
		return $this->filter_authorizations(
			$authorizations,
			array( AuthorizationStatus::CREATED, AuthorizationStatus::PENDING )
		);
	}

	/**
	 * The authorizations which were captured.
	 *
	 * @param Authorization ...$authorizations All Authorizations.
	 * @return Authorization[]
	 */
	private function captured_authorizations( Authorization ...$authorizations ): array {
		return $this->filter_authorizations(
			$authorizations,
			array( AuthorizationStatus::CAPTURED )
		);
	}

	/**
	 * The authorizations which need to be filtered.
	 *
	 * @param Authorization[] $authorizations All Authorizations.
	 * @param string[]        $statuses Allowed statuses, the constants from AuthorizationStatus.
	 * @return Authorization[]
	 */
	private function filter_authorizations( array $authorizations, array $statuses ): array {
		return array_filter(
			$authorizations,
			static function ( Authorization $authorization ) use ( $statuses ): bool {
				$status = $authorization->status();
				return in_array( $status->name(), $statuses, true );
			}
		);
	}
}
