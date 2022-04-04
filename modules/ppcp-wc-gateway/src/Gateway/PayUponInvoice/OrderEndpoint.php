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
	 * @var FraudNet
	 */
	protected $fraudNet;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

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
		$this->fraudNet      = $fraudNet;
	}

	/**
	 * Creates an order.
	 *
	 * @param PurchaseUnit[] $items The purchase unit items for the order.
	 * @return Order
	 */
	public function create( array $items, PaymentSource $payment_source, $fraudnet_session_id = '' ): Order {
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
				'pay_upon_invoice' => $payment_source->to_array(),
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
				'PayPal-Client-Metadata-Id' => $fraudnet_session_id ?: $this->fraudNet->sessionId(),
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
		if ( ! in_array($status_code, [200,201] ) ) {
			$issue = $json->details[0]->issue ?? null;
			if($issue === 'PAYMENT_SOURCE_INFO_CANNOT_BE_VERIFIED') {
				throw new RuntimeException('The combination of your name and address could not be validated. Please correct your data and try again. You can find further information in the <a href="https://www.ratepay.com/en/ratepay-data-privacy-statement/" target="_blank">Ratepay Data Privacy Statement</a> or you can contact Ratepay using this <a href="https://www.ratepay.com/en/contact/" target="_blank">contact form</a>.');
			}
			if($issue === 'PAYMENT_SOURCE_DECLINED_BY_PROCESSOR') {
				throw new RuntimeException('It is not possible to use the selected payment method. This decision is based on automated data processing. You can find further information in the <a href="https://www.ratepay.com/en/ratepay-data-privacy-statement/" target="_blank">Ratepay Data Privacy Statement</a> or you can contact Ratepay using this <a href="https://www.ratepay.com/en/contact/" target="_blank">contact form</a>.');
			}

			throw new PayPalApiException( $json, $status_code );
		}

		return $this->order_factory->from_paypal_response( $json );
	}

	public function order_payment_instructions(string $id): array  {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $id;
		$args   = array(
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' =>  uniqid( 'ppcp-', true ),
			),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new PayPalApiException( $json, $status_code );
		}

		$this->logger->info('Payment instructions');
		$this->logger->info($json->payment_source->pay_upon_invoice->payment_reference);
		$this->logger->info(wc_print_r($json->payment_source->pay_upon_invoice->deposit_bank_details, true));

		return array(
			$json->payment_source->pay_upon_invoice->payment_reference,
			$json->payment_source->pay_upon_invoice->deposit_bank_details
		);
	}
}
