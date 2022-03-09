<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use Psr\Log\LoggerInterface;
use RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;

class OrderEndpoint {

	use RequestTrait;

	/**
	 * @var string
	 */
	protected $host;

	/**
	 * @var Bearer
	 */
	protected $bearer;

	/**
	 * @var OrderFactory
	 */
	protected $order_factory;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;
	/**
	 * @var FraudNet
	 */
	protected $fraudNet;

	public function __construct(
		string $host,
		Bearer $bearer,
		OrderFactory $order_factory,
		FraudNet $fraudNet,
		LoggerInterface $logger
	) {
		$this->host          = $host;
		$this->bearer        = $bearer;
		$this->order_factory = $order_factory;
		$this->logger        = $logger;
		$this->fraudNet = $fraudNet;
	}

	/**
	 * Creates an order.
	 *
	 * @param PurchaseUnit[] $items The purchase unit items for the order.
	 * @return Order
	 */
	public function create( array $items ): Order {
		$data = array(
			'intent'                 => 'CAPTURE',
			'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
			'purchase_units'         => array_map(
				static function ( PurchaseUnit $item ): array {
					return $item->to_array();
				},
				$items
			),
			'payment_source'         => array(
				'pay_upon_invoice' => array(
					'name'               => array(
						'given_name' => 'John',
						'surname'    => 'Doe',
					),
					'email'              => 'buyer@example.com',
					'birth_date'         => '1990-01-01',
					'phone'              => array(
						'national_number' => '6912345678',
						'country_code'    => '49',
					),
					'billing_address'    => array(
						'address_line_1' => 'Schönhauser Allee 84',
						'admin_area_2'   => 'Berlin',
						'postal_code'    => '10439',
						'country_code'   => 'DE',
					),
					'experience_context' => array(
						'locale'                        => 'en-DE',
						'brand_name'                    => 'EXAMPLE INC',
						'logo_url'                      => 'https://example.com/logoUrl.svg',
						'customer_service_instructions' => array(
							'Customer service phone is +49 6912345678.',
						),
					),
				),
			),
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'             => 'Bearer ' . $bearer->token(),
				'Content-Type'              => 'application/json',
				'Prefer'                    => 'return=representation',
				'PayPal-Client-Metadata-Id' => $this->fraudNet->sessionId(),
				'PayPal-Request-Id'         => uniqid( 'ppcp-', true ),
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			throw new PayPalApiException( $json, $status_code );
		}

		return $this->order_factory->from_paypal_response( $json );
	}
}
