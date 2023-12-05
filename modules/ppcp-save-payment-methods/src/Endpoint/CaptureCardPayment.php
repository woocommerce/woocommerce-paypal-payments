<?php
/**
 * The Capture Card Payment endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WP_Error;

/**
 * Class CaptureCardPayment
 */
class CaptureCardPayment implements EndpointInterface {

	use RequestTrait;

	const ENDPOINT = 'ppc-capture-card-payment';

	/**
	 * The request data.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The host.
	 *
	 * @var string
	 */
	private $host;

	/**
	 * The bearer.
	 *
	 * @var Bearer
	 */
	private $bearer;

	/**
	 * The order factory.
	 *
	 * @var OrderFactory
	 */
	private $order_factory;

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	private $order_endpoint;

	/**
	 * The session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * CaptureCardPayment constructor.
	 *
	 * @param RequestData         $request_data The request data.
	 * @param string              $host The host.
	 * @param Bearer              $bearer The bearer.
	 * @param OrderFactory        $order_factory The order factory.
	 * @param PurchaseUnitFactory $purchase_unit_factory The purchase unit factory.
	 * @param OrderEndpoint       $order_endpoint The order endpoint.
	 * @param SessionHandler      $session_handler The session handler.
	 * @param LoggerInterface     $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		string $host,
		Bearer $bearer,
		OrderFactory $order_factory,
		PurchaseUnitFactory $purchase_unit_factory,
		OrderEndpoint $order_endpoint,
		SessionHandler $session_handler,
		LoggerInterface $logger
	) {
		$this->request_data          = $request_data;
		$this->host                  = $host;
		$this->bearer                = $bearer;
		$this->order_factory         = $order_factory;
		$this->purchase_unit_factory = $purchase_unit_factory;
		$this->order_endpoint        = $order_endpoint;
		$this->logger                = $logger;
		$this->session_handler       = $session_handler;
	}

	/**
	 * Returns the nonce.
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::ENDPOINT;
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 */
	public function handle_request(): bool {
		$data = $this->request_data->read_request( $this->nonce() );

		$tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id() );
		foreach ( $tokens as $token ) {
			if ( $token->get_id() === (int) $data['payment_token'] ) {
				try {
					$order = $this->create_order( $token->get_token() );

					$id             = $order->id ?? '';
					$status         = $order->status ?? '';
					$payment_source = isset( $order->payment_source->card ) ? 'card' : '';
					if ( $id && $status && $payment_source ) {
						WC()->session->set(
							'ppcp_saved_payment_card',
							array(
								'order_id'       => $id,
								'status'         => $status,
								'payment_source' => $payment_source,
							)
						);

						wp_send_json_success();
						return true;
					}
				} catch ( RuntimeException $exception ) {
					wp_send_json_error();
					return false;
				}
			}
		}

		wp_send_json_error();
		return false;
	}

	/**
	 * Creates PayPal order from the given card vault id.
	 *
	 * @param string $vault_id Vault id.
	 * @return stdClass
	 * @throws RuntimeException When request fails.
	 */
	private function create_order( string $vault_id ): stdClass {
		$items = array( $this->purchase_unit_factory->from_wc_cart() );

		$data = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array_map(
				static function ( PurchaseUnit $item ): array {
					return $item->to_array( true, false );
				},
				$items
			),
			'payment_source' => array(
				'card' => array(
					'vault_id' => $vault_id,
				),
			),
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' => uniqid( 'ppcp-', true ),
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		return json_decode( $response['body'] );
	}
}

