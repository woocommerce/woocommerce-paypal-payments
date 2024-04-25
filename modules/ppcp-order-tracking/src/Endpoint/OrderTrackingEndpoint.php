<?php
/**
 * The order tracking Endpoint.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Endpoint
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\OrderTracking\Endpoint;

use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\OrderTracking\OrderTrackingModule;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use function WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order;

/**
 * The OrderTrackingEndpoint.
 *
 * @psalm-type SupportedStatuses = 'SHIPPED'|'ON_HOLD'|'DELIVERED'|'CANCELLED'
 * @psalm-type TrackingInfo = array{
 *     capture_id: string,
 *     status: SupportedStatuses,
 *     tracking_number: string,
 *     carrier: string,
 *     items?: list<int>,
 *     carrier_name_other?: string,
 * }
 * Class OrderTrackingEndpoint
 */
class OrderTrackingEndpoint {

	use RequestTrait, TransactionIdHandlingTrait;

	const ENDPOINT = 'ppc-tracking-info';

	/**
	 * The RequestData.
	 *
	 * @var RequestData
	 */
	protected $request_data;

	/**
	 * The Host URL.
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * The bearer.
	 *
	 * @var Bearer
	 */
	protected $bearer;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * The ShipmentFactory.
	 *
	 * @var ShipmentFactoryInterface
	 */
	protected $shipment_factory;

	/**
	 * Allowed shipping statuses.
	 *
	 * @var string[]
	 */
	protected $allowed_statuses;

	/**
	 * Whether new API should be used.
	 *
	 * @var bool
	 */
	protected $should_use_new_api;

	/**
	 * PartnersEndpoint constructor.
	 *
	 * @param string                   $host The host.
	 * @param Bearer                   $bearer The bearer.
	 * @param LoggerInterface          $logger The logger.
	 * @param RequestData              $request_data The Request data.
	 * @param ShipmentFactoryInterface $shipment_factory The ShipmentFactory.
	 * @param string[]                 $allowed_statuses Allowed shipping statuses.
	 * @param bool                     $should_use_new_api Whether new API should be used.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		LoggerInterface $logger,
		RequestData $request_data,
		ShipmentFactoryInterface $shipment_factory,
		array $allowed_statuses,
		bool $should_use_new_api
	) {
		$this->host               = $host;
		$this->bearer             = $bearer;
		$this->logger             = $logger;
		$this->request_data       = $request_data;
		$this->shipment_factory   = $shipment_factory;
		$this->allowed_statuses   = $allowed_statuses;
		$this->should_use_new_api = $should_use_new_api;
	}

	/**
	 * Handles the request.
	 */
	public function handle_request(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Not admin.', 403 );
			return;
		}

		try {
			$data     = $this->request_data->read_request( $this->nonce() );
			$order_id = (int) $data['order_id'];
			$action   = $data['action'] ?? '';

			$this->validate_tracking_info( $data );
			$shipment = $this->create_shipment( $order_id, $data );

			$action === 'update'
				? $this->update_tracking_information( $shipment, $order_id )
				: $this->add_tracking_information( $shipment, $order_id );

			$message = $action === 'update'
				? _x( 'successfully updated', 'tracking info success message', 'woocommerce-paypal-payments' )
				: _x( 'successfully created', 'tracking info success message', 'woocommerce-paypal-payments' );

			ob_start();
			$shipment->render( $this->allowed_statuses );
			$shipment_html = ob_get_clean();

			wp_send_json_success(
				array(
					'message'  => $message,
					'shipment' => $shipment_html,
				)
			);
		} catch ( Exception $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ), 500 );
		}
	}

	/**
	 * Creates the tracking information of a given order with the given data.
	 *
	 * @param ShipmentInterface $shipment The shipment.
	 * @param int               $order_id The order ID.
	 *
	 * @throws RuntimeException If problem adding.
	 */
	public function add_tracking_information( ShipmentInterface $shipment, int $order_id ) : void {
		$wc_order = wc_get_order( $order_id );
		if ( ! $wc_order instanceof WC_Order ) {
			return;
		}

		$shipment_request_data = $this->generate_request_data( $wc_order, $shipment );

		$url  = $shipment_request_data['url'] ?? '';
		$args = $shipment_request_data['args'] ?? array();

		if ( ! $url || empty( $args ) ) {
			$this->throw_runtime_exception( $shipment_request_data, 'create' );
		}

		do_action( 'woocommerce_paypal_payments_before_tracking_is_added', $order_id, $shipment_request_data );

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$args = array(
				'args'     => $args,
				'response' => $response,
			);
			$this->throw_runtime_exception( $args, 'create' );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code && ! is_wp_error( $response ) ) {
			/**
			 * Cannot be WP_Error because we check for it above.
			 *
			 * @psalm-suppress PossiblyInvalidArgument
			 */
			$this->throw_paypal_api_exception( $status_code, $args, $response, 'create' );
		}

		$this->save_tracking_metadata( $wc_order, $shipment->tracking_number(), array_keys( $shipment->line_items() ) );

		do_action( 'woocommerce_paypal_payments_after_tracking_is_added', $order_id, $response );
	}

	/**
	 * Updates the tracking information of a given order with the given shipment.
	 *
	 * @param ShipmentInterface $shipment The shipment.
	 * @param int               $order_id The order ID.
	 *
	 * @throws RuntimeException If problem updating.
	 */
	public function update_tracking_information( ShipmentInterface $shipment, int $order_id ) : void {
		$host          = trailingslashit( $this->host );
		$tracker_id    = $this->find_tracker_id( $shipment->capture_id(), $shipment->tracking_number() );
		$url           = "{$host}v1/shipping/trackers/{$tracker_id}";
		$shipment_data = $shipment->to_array();

		$args = array(
			'method'  => 'PUT',
			'headers' => $this->request_headers(),
			'body'    => wp_json_encode( (array) apply_filters( 'woocommerce_paypal_payments_tracking_data_before_update', $shipment_data, $order_id ) ),
		);

		do_action( 'woocommerce_paypal_payments_before_tracking_is_updated', $order_id, $shipment_data );

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$args = array(
				'args'     => $args,
				'response' => $response,
			);
			$this->throw_runtime_exception( $args, 'update' );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code && ! is_wp_error( $response ) ) {
			/**
			 * Cannot be WP_Error because we check for it above.
			 *
			 * @psalm-suppress PossiblyInvalidArgument
			 */
			$this->throw_paypal_api_exception( $status_code, $args, $response, 'update' );
		}

		do_action( 'woocommerce_paypal_payments_after_tracking_is_updated', $order_id, $response );
	}

	/**
	 * Gets the tracking information of a given order.
	 *
	 * @param int    $wc_order_id The order ID.
	 * @param string $tracking_number The tracking number.
	 *
	 * @return ShipmentInterface|null The tracking information.
	 * @throws RuntimeException If problem getting.
	 */
	public function get_tracking_information( int $wc_order_id, string $tracking_number ) : ?ShipmentInterface {
		$wc_order = wc_get_order( $wc_order_id );
		if ( ! $wc_order instanceof WC_Order ) {
			return null;
		}

		$host         = trailingslashit( $this->host );
		$paypal_order = ppcp_get_paypal_order( $wc_order );
		$capture_id   = $this->get_paypal_order_transaction_id( $paypal_order ) ?? '';
		$tracker_id   = $this->find_tracker_id( $capture_id, $tracking_number );
		$url          = "{$host}v1/shipping/trackers/{$tracker_id}";

		$args = array(
			'method'  => 'GET',
			'headers' => $this->request_headers(),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$args = array(
				'args'     => $args,
				'response' => $response,
			);
			$this->throw_runtime_exception( $args, 'fetch' );
		}

		/**
		 * Need to ignore Method WP_Error::offsetGet does not exist
		 *
		 * @psalm-suppress UndefinedMethod
		 */
		$data        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return null;
		}

		return $this->create_shipment( $wc_order_id, (array) $data );
	}

	/**
	 * Gets the list of shipments of a given order.
	 *
	 * @param int $wc_order_id The order ID.
	 * @return ShipmentInterface[] The list of shipments.
	 * @throws RuntimeException If problem getting.
	 */
	public function list_tracking_information( int $wc_order_id ) : ?array {
		$wc_order = wc_get_order( $wc_order_id );
		if ( ! $wc_order instanceof WC_Order ) {
			return array();
		}

		$host         = trailingslashit( $this->host );
		$paypal_order = ppcp_get_paypal_order( $wc_order );
		$capture_id   = $this->get_paypal_order_transaction_id( $paypal_order );
		$url          = "{$host}v1/shipping/trackers?transaction_id={$capture_id}";

		$args = array(
			'method'  => 'GET',
			'headers' => $this->request_headers(),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$args = array(
				'args'     => $args,
				'response' => $response,
			);
			$this->throw_runtime_exception( $args, 'fetch' );
		}

		/**
		 * Need to ignore Method WP_Error::offsetGet does not exist
		 *
		 * @psalm-suppress UndefinedMethod
		 */
		$data        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return null;
		}

		$shipments = array();

		foreach ( $data->trackers as $shipment ) {
			$shipments[] = $this->create_shipment( $wc_order_id, (array) $shipment );
		}

		return $shipments;
	}

	/**
	 * The nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Creates the shipment based on requested data.
	 *
	 * @param int   $wc_order_id The WC order ID.
	 * @param array $data The request data map.
	 * @psalm-param TrackingInfo $data
	 *
	 * @return ShipmentInterface The shipment.
	 * @throws RuntimeException If problem creating.
	 */
	protected function create_shipment( int $wc_order_id, array $data ): ShipmentInterface {
		$carrier = $data['carrier'] ?? '';

		$tracking_info = array(
			'capture_id'         => $data['capture_id'] ?? '',
			'status'             => $data['status'] ?? '',
			'tracking_number'    => $data['tracking_number'] ?? '',
			'carrier'            => $carrier,
			'carrier_name_other' => $data['carrier_name_other'] ?? '',
		);

		if ( ! empty( $data['items'] ) ) {
			$tracking_info['items'] = array_map( 'intval', $data['items'] );
		}

		return $this->shipment_factory->create_shipment(
			$wc_order_id,
			$tracking_info['capture_id'],
			$tracking_info['tracking_number'],
			$tracking_info['status'],
			$tracking_info['carrier'],
			$tracking_info['carrier_name_other'],
			$tracking_info['items'] ?? array()
		);
	}

	/**
	 * Validates tracking info for given request values.
	 *
	 * @param array<string, mixed> $request_values A map of request keys to values.
	 * @return void
	 * @throws RuntimeException If validation failed.
	 */
	protected function validate_tracking_info( array $request_values ): void {
		$error_message = __( 'Missing required information: ', 'woocommerce-paypal-payments' );
		$empty_keys    = array();

		$carrier = $request_values['carrier'] ?? '';

		$data_to_check = array(
			'capture_id'      => $request_values['capture_id'] ?? '',
			'status'          => $request_values['status'] ?? '',
			'tracking_number' => $request_values['tracking_number'] ?? '',
			'carrier'         => $carrier,
		);

		if ( $carrier === 'OTHER' ) {
			$data_to_check['carrier_name_other'] = $request_values['carrier_name_other'] ?? '';
		}

		foreach ( $data_to_check as $key => $value ) {
			if ( ! empty( $value ) ) {
				continue;
			}

			$empty_keys[] = ucwords( str_replace( '_', ' ', $key ) );
		}

		if ( empty( $empty_keys ) ) {
			return;
		}

		$error_message .= implode( ' ,', $empty_keys );

		throw new RuntimeException( $error_message );
	}

	/**
	 * Creates the request headers.
	 *
	 * @return array The request headers.
	 */
	protected function request_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->bearer->bearer()->token(),
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Finds the tracker ID from given capture ID and tracking number.
	 *
	 * @param string $capture_id The capture ID.
	 * @param string $tracking_number The tracking number.
	 * @return string The tracker ID.
	 */
	protected function find_tracker_id( string $capture_id, string $tracking_number ): string {
		return ! empty( $tracking_number ) ? "{$capture_id}-{$tracking_number}" : "{$capture_id}-NOTRACKER";
	}

	/**
	 * Saves the tracking metadata for given line items.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param string   $tracking_number The tracking number.
	 * @param int[]    $line_items The list of shipment line items.
	 * @return void
	 */
	protected function save_tracking_metadata( WC_Order $wc_order, string $tracking_number, array $line_items ): void {
		$tracking_meta = $wc_order->get_meta( OrderTrackingModule::PPCP_TRACKING_INFO_META_NAME );

		if ( ! is_array( $tracking_meta ) ) {
			$tracking_meta = array();
		}

		foreach ( $line_items as $item ) {
			$tracking_meta[ $tracking_number ][] = $item;
		}

		$wc_order->update_meta_data( OrderTrackingModule::PPCP_TRACKING_INFO_META_NAME, $tracking_meta );
		$wc_order->save();
	}

	/**
	 * Generates the request data.
	 *
	 * @param WC_Order          $wc_order The WC order.
	 * @param ShipmentInterface $shipment The shipment.
	 * @return array
	 */
	protected function generate_request_data( WC_Order $wc_order, ShipmentInterface $shipment ): array {
		$paypal_order_id = $wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		$host            = trailingslashit( $this->host );
		$shipment_data   = $shipment->to_array();

		if ( ! $this->should_use_new_api ) {
			unset( $shipment_data['capture_id'] );
			unset( $shipment_data['items'] );
			$shipment_data['transaction_id'] = $shipment->capture_id();
			$shipment_data                   = array( 'trackers' => array( $shipment_data ) );
		}

		$url  = $this->should_use_new_api ? "{$host}v2/checkout/orders/{$paypal_order_id}/track" : "{$host}v1/shipping/trackers";
		$args = array(
			'method'  => 'POST',
			'headers' => $this->request_headers(),
			'body'    => wp_json_encode( (array) apply_filters( 'woocommerce_paypal_payments_tracking_data_before_sending', $shipment_data, $wc_order->get_id() ) ),
		);

		return array(
			'url'  => $url,
			'args' => $args,
		);
	}

	/**
	 * Throws PayPal APi exception and logs the error message with given arguments.
	 *
	 * @param int                  $status_code The response status code.
	 * @param array<string, mixed> $args The arguments.
	 * @param array                $response The request response.
	 * @param string               $message_part The part of the message.
	 * @return void
	 *
	 * @throws PayPalApiException PayPal APi exception.
	 */
	protected function throw_paypal_api_exception( int $status_code, array $args, array $response, string $message_part ): void {
		$error = new PayPalApiException(
			json_decode( $response['body'] ),
			$status_code
		);
		$this->logger->log(
			'warning',
			sprintf(
				"Failed to {$message_part} order tracking information. PayPal API response: %s",
				$error->getMessage()
			),
			array(
				'args'     => $args,
				'response' => $response,
			)
		);
		throw $error;
	}

	/**
	 * Throws the exception && logs the error message with given arguments.
	 *
	 * @param array  $args The arguments.
	 * @param string $message_part The part of the message.
	 * @return void
	 *
	 * @throws RuntimeException The exception.
	 */
	protected function throw_runtime_exception( array $args, string $message_part ): void {
		$error = new RuntimeException( "Could not {$message_part} the order tracking information." );
		$this->logger->log(
			'warning',
			$error->getMessage(),
			$args
		);
		throw $error;
	}
}
