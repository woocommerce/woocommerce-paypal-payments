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
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ErrorResponse;
use WooCommerce\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;
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
	 * True if FraudNet support is enabled in settings, otherwise false.
	 *
	 * @var bool
	 */
	protected $is_fraudnet_enabled;

	/**
	 * The FraudNet entity.
	 *
	 * @var FraudNet
	 */
	protected $fraudnet;

	/**
	 * The BN Code.
	 *
	 * @var string
	 */
	private $bn_code;

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
	 * @param SubscriptionHelper           $subscription_helper The subscription helper.
	 * @param bool                         $is_fraudnet_enabled true if FraudNet support is enabled in settings, otherwise false.
	 * @param FraudNet                     $fraudnet The FraudNet entity.
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
		SubscriptionHelper $subscription_helper,
		bool $is_fraudnet_enabled,
		FraudNet $fraudnet,
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
		$this->is_fraudnet_enabled            = $is_fraudnet_enabled;
		$this->subscription_helper            = $subscription_helper;
		$this->fraudnet                       = $fraudnet;
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
	 * @param string             $paypal_request_id The PayPal request id.
	 * @param string             $user_action The user action.
	 * @param string             $payment_method WC payment method.
	 * @param array              $request_data Request data.
	 * @param PaymentSource|null $payment_source The payment source.
	 *
	 * @return Order
	 * @throws RuntimeException If the request fails.
	 */
	public function create(
		array $items,
		string $shipping_preference,
		Payer $payer = null,
		PaymentToken $payment_token = null,
		string $paypal_request_id = '',
		string $user_action = ApplicationContext::USER_ACTION_CONTINUE,
		string $payment_method = '',
		array $request_data = array(),
		PaymentSource $payment_source = null
	): Order {
		$bearer = $this->bearer->bearer();
		$data   = array(
			'intent'              => apply_filters( 'woocommerce_paypal_payments_order_intent', $this->intent ),
			'purchase_units'      => array_map(
				static function ( PurchaseUnit $item ) use ( $shipping_preference ): array {
					$data = $item->to_array();

					if ( $shipping_preference !== ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE ) {
						// Shipping options are not allowed to be sent when not getting the address from PayPal.
						unset( $data['shipping']['options'] );
					}

					return $data;
				},
				$items
			),
			'application_context' => $this->application_context_repository
				->current_context( $shipping_preference, $user_action )->to_array(),
		);
		if ( $payer && ! empty( $payer->email_address() ) ) {
			$data['payer'] = $payer->to_array();
		}
		if ( $payment_token ) {
			$data['payment_source']['token'] = $payment_token->to_array();
		}
		if ( $payment_source ) {
			$data['payment_source'] = array(
				$payment_source->name() => $payment_source->properties(),
			);
		}

		/**
		 * The filter can be used to modify the order creation request body data.
		 */
		$data = apply_filters( 'ppcp_create_order_request_body_data', $data, $payment_method, $request_data );
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

		if ( $this->bn_code ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = $this->bn_code;
		}

		if ( $this->is_fraudnet_enabled ) {
			$args['headers']['PayPal-Client-Metadata-Id'] = $this->fraudnet->session_id();
		}

		if ( isset( $data['payment_source'] ) ) {
			$args['headers']['PayPal-Request-Id'] = uniqid( 'ppcp-', true );
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
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			$error = new PayPalApiException(
				$json,
				$status_code
			);

			$this->logger->warning(
				sprintf(
					'Failed to create order. PayPal API response: %1$s',
					$error->getMessage()
				)
			);

			throw $error;
		}

		$order = $this->order_factory->from_paypal_response( $json );

		do_action( 'woocommerce_paypal_payments_paypal_order_created', $order );

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
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
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
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
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
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
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
			$this->logger->warning( $error->getMessage() );

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

		return $this->order_factory->from_paypal_response( $json );
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

		$this->patch( $order_to_update->id(), $patches );

		$new_order = $this->order( $order_to_update->id() );
		return $new_order;
	}

	/**
	 * Patches an order.
	 *
	 * @param string          $order_id The PayPal order ID.
	 * @param PatchCollection $patches The patches.
	 *
	 * @throws RuntimeException If the request fails.
	 */
	public function patch( string $order_id, PatchCollection $patches ): void {
		$patches_array = $patches->to_array();

		/**
		 * The filter can be used to modify the order patching request body data (the final prices, items).
		 */
		$patches_array = apply_filters( 'ppcp_patch_order_request_body_data', $patches_array );

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $order_id;
		$args   = array(
			'method'  => 'PATCH',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
			'body'    => wp_json_encode( $patches_array ),
		);
		if ( $this->bn_code ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = $this->bn_code;
		}
		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error = new RuntimeException( 'Could not patch order.' );
			$this->logger->warning(
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
			$this->logger->warning(
				$error->getMessage(),
				array(
					'args'     => $args,
					'response' => $response,
				)
			);
			throw $error;
		}
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
