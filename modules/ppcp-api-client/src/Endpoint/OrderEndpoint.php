<?php
/**
 * The order endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentMethod;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ErrorResponse;
use WooCommerce\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WP_Error;

/**
 * Class OrderEndpoint
 */
class OrderEndpoint {

	use RequestTrait;

	/**
	 * The subscription helper
	 *
	 * @var SubscriptionHelper
	 */
	protected $subscription_helper;

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
	 * The patch collection factory.
	 *
	 * @var PatchCollectionFactory
	 */
	private $patch_collection_factory;

	/**
	 * The intent.
	 *
	 * @var string
	 */
	private $intent;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * The application context repository.
	 *
	 * @var ApplicationContextRepository
	 */
	private $application_context_repository;

	/**
	 * The BN Code.
	 *
	 * @var string
	 */
	private $bn_code;

	/**
	 * The paypal request id repository.
	 *
	 * @var PayPalRequestIdRepository
	 */
	private $paypal_request_id_repository;

	/**
	 * OrderEndpoint constructor.
	 *
	 * @param string                       $host The host.
	 * @param Bearer                       $bearer The bearer.
	 * @param OrderFactory                 $order_factory The order factory.
	 * @param PatchCollectionFactory       $patch_collection_factory The patch collection factory.
	 * @param string                       $intent The intent.
	 * @param LoggerInterface              $logger The logger.
	 * @param ApplicationContextRepository $application_context_repository The application context repository.
	 * @param PayPalRequestIdRepository    $paypal_request_id_repository The paypal request id repository.
	 * @param SubscriptionHelper           $subscription_helper The subscription helper.
	 * @param string                       $bn_code The BN Code.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		OrderFactory $order_factory,
		PatchCollectionFactory $patch_collection_factory,
		string $intent,
		LoggerInterface $logger,
		ApplicationContextRepository $application_context_repository,
		PayPalRequestIdRepository $paypal_request_id_repository,
		SubscriptionHelper $subscription_helper,
		string $bn_code = ''
	) {

		$this->host                           = $host;
		$this->bearer                         = $bearer;
		$this->order_factory                  = $order_factory;
		$this->patch_collection_factory       = $patch_collection_factory;
		$this->intent                         = $intent;
		$this->logger                         = $logger;
		$this->application_context_repository = $application_context_repository;
		$this->bn_code                        = $bn_code;
		$this->paypal_request_id_repository   = $paypal_request_id_repository;
		$this->subscription_helper            = $subscription_helper;
	}

	/**
	 * Changes the used BN Code.
	 *
	 * @param string $bn_code The new BN Code to use.
	 *
	 * @return OrderEndpoint
	 * @throws RuntimeException If the request fails.
	 */
	public function with_bn_code( string $bn_code ): OrderEndpoint {

		$this->bn_code = $bn_code;
		return $this;
	}

	/**
	 * Creates an order.
	 *
	 * @param PurchaseUnit[]     $items The purchase unit items for the order.
	 * @param string             $shipping_preference One of ApplicationContext::SHIPPING_PREFERENCE_ values.
	 * @param Payer|null         $payer The payer off the order.
	 * @param PaymentToken|null  $payment_token The payment token.
	 * @param PaymentMethod|null $payment_method The payment method.
	 * @param string             $paypal_request_id The paypal request id.
	 *
	 * @return Order
	 * @throws RuntimeException If the request fails.
	 */
	public function create(
		array $items,
		string $shipping_preference,
		Payer $payer = null,
		PaymentToken $payment_token = null,
		PaymentMethod $payment_method = null,
		string $paypal_request_id = ''
	): Order {
		$bearer = $this->bearer->bearer();
		$data   = array(
			'intent'              => ( $this->subscription_helper->cart_contains_subscription() || $this->subscription_helper->current_product_is_subscription() ) ? 'AUTHORIZE' : $this->intent,
			'purchase_units'      => array_map(
				static function ( PurchaseUnit $item ): array {
					return $item->to_array();
				},
				$items
			),
			'application_context' => $this->application_context_repository
				->current_context( $shipping_preference )->to_array(),
		);
		if ( $payer && ! empty( $payer->email_address() ) ) {
			$data['payer'] = $payer->to_array();
		}
		if ( $payment_token ) {
			$data['payment_source']['token'] = $payment_token->to_array();
		}
		if ( $payment_method ) {
			$data['payment_method'] = $payment_method->to_array();
		}

		/**
		 * The filter can be used to modify the order creation request body data.
		 */
		$data = apply_filters( 'ppcp_create_order_request_body_data', $data );
		$url  = trailingslashit( $this->host ) . 'v2/checkout/orders';
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
			'body'    => wp_json_encode( $data ),
		);

		$paypal_request_id                    = $paypal_request_id ? $paypal_request_id : uniqid( 'ppcp-', true );
		$args['headers']['PayPal-Request-Id'] = $paypal_request_id;
		if ( $this->bn_code ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = $this->bn_code;
		}
		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not create order.', 'woocommerce-paypal-payments' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);
			$this->logger->log(
				'warning',
				sprintf(
					'Failed to create order. PayPal API response: %1$s',
					$error->getMessage()
				),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		$order = $this->order_factory->from_paypal_response( $json );
		$this->paypal_request_id_repository->set_for_order( $order, $paypal_request_id );
		return $order;
	}

	/**
	 * Captures an order.
	 *
	 * @param Order $order The order.
	 *
	 * @return Order
	 * @throws RuntimeException If the request fails.
	 */
	public function capture( Order $order ): Order {
		if ( $order->status()->is( OrderStatus::COMPLETED ) ) {
			return $order;
		}
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $order->id() . '/capture';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'Prefer'            => 'return=representation',
				'PayPal-Request-Id' => $this->paypal_request_id_repository->get_for_order( $order ),
			),
		);
		if ( $this->bn_code ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = $this->bn_code;
		}
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not capture order.', 'woocommerce-paypal-payments' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);
			// If the order has already been captured, we return the updated order.
			if ( strpos( $response['body'], ErrorResponse::ORDER_ALREADY_CAPTURED ) !== false ) {
				return $this->order( $order->id() );
			}
			$this->logger->log(
				'warning',
				sprintf(
					'Failed to capture order. PayPal API response: %1$s',
					$error->getMessage()
				),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$order = $this->order_factory->from_paypal_response( $json );

		$capture_status = $order->purchase_units()[0]->payments()->captures()[0]->status() ?? null;
		if ( $capture_status && $capture_status->is( CaptureStatus::DECLINED ) ) {
			throw new RuntimeException( __( 'Payment provider declined the payment, please use a different payment method.', 'woocommerce-paypal-payments' ) );
		}

		return $order;
	}

	/**
	 * Authorize an order.
	 *
	 * @param Order $order The order.
	 *
	 * @return Order
	 * @throws RuntimeException If the request fails.
	 */
	public function authorize( Order $order ): Order {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $order->id() . '/authorize';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'Prefer'            => 'return=representation',
				'PayPal-Request-Id' => $this->paypal_request_id_repository->get_for_order( $order ),
			),
		);
		if ( $this->bn_code ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = $this->bn_code;
		}
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__(
					'Could not authorize order.',
					'woocommerce-paypal-payments'
				)
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			if ( false !== strpos( $response['body'], ErrorResponse::ORDER_ALREADY_AUTHORIZED ) ) {
				return $this->order( $order->id() );
			}
			$error = new PayPalApiException(
				$json,
				$status_code
			);
			$this->logger->log(
				'warning',
				sprintf(
					'Failed to authorize order. PayPal API response: %1$s',
					$error->getMessage()
				),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		$order = $this->order_factory->from_paypal_response( $json );

		$authorization_status = $order->purchase_units()[0]->payments()->authorizations()[0]->status() ?? null;
		if ( $authorization_status && $authorization_status->is( AuthorizationStatus::DENIED ) ) {
			throw new RuntimeException( __( 'Payment provider declined the payment, please use a different payment method.', 'woocommerce-paypal-payments' ) );
		}

		return $order;
	}

	/**
	 * Fetches an order for a given ID.
	 *
	 * @param string $id The ID.
	 *
	 * @return Order
	 * @throws RuntimeException If the request fails.
	 */
	public function order( string $id ): Order {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $id;
		$args   = array(
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' => $this->paypal_request_id_repository->get_for_order_id( $id ),
			),
		);
		if ( $this->bn_code ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = $this->bn_code;
		}
		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not retrieve order.', 'woocommerce-paypal-payments' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $status_code || empty( $response['body'] ) ) {
			$error = new RuntimeException(
				__( 'Could not retrieve order.', 'woocommerce-paypal-payments' ),
				404
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		if ( 200 !== $status_code ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		$order = $this->order_factory->from_paypal_response( $json );
		return $order;
	}

	/**
	 * Patches an order.
	 *
	 * @param Order $order_to_update The order to patch.
	 * @param Order $order_to_compare The target order.
	 *
	 * @return Order
	 * @throws RuntimeException If the request fails.
	 */
	public function patch_order_with( Order $order_to_update, Order $order_to_compare ): Order {
		$patches = $this->patch_collection_factory->from_orders( $order_to_update, $order_to_compare );
		if ( ! count( $patches->patches() ) ) {
			return $order_to_update;
		}

		$patches_array = $patches->to_array();
		if ( ! isset( $patches_array[0]['value']['shipping'] ) ) {
			$shipping = isset( $order_to_update->purchase_units()[0] ) && null !== $order_to_update->purchase_units()[0]->shipping() ? $order_to_update->purchase_units()[0]->shipping() : null;
			if ( $shipping ) {
				$patches_array[0]['value']['shipping'] = $shipping->to_array();
			}
		}

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $order_to_update->id();
		$args   = array(
			'method'  => 'PATCH',
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'Prefer'            => 'return=representation',
				'PayPal-Request-Id' => $this->paypal_request_id_repository->get_for_order(
					$order_to_update
				),
			),
			'body'    => wp_json_encode( $patches_array ),
		);
		if ( $this->bn_code ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = $this->bn_code;
		}
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException(
				__( 'Could not retrieve order.', 'woocommerce-paypal-payments' )
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);
			$this->logger->log(
				'warning',
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}

		$new_order = $this->order( $order_to_update->id() );
		return $new_order;
	}

	/**
	 * Confirms payment source.
	 *
	 * @param string $id The PayPal order ID.
	 * @param array  $payment_source The payment source.
	 * @return stdClass
	 * @throws PayPalApiException If the request fails.
	 * @throws RuntimeException If something unexpected happens.
	 */
	public function confirm_payment_source( string $id, array $payment_source ): stdClass {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $id . '/confirm-payment-source';

		$data = array(
			'payment_source'         => $payment_source,
			'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
			'application_context'    => array(
				'locale' => 'es-MX',
			),
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new PayPalApiException( $json, $status_code );
		}

		return $json;
	}
}
