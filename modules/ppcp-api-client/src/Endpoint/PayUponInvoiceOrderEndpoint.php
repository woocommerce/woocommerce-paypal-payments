<?php
/**
 * Create order for PUI.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use WC_Customer;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Product;
use WC_Tax;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PaymentSource;
use WP_Error;

/**
 * Class OrderEndpoint.
 */
class PayUponInvoiceOrderEndpoint {

	use RequestTrait;

	/**
	 * The host.
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
	 * The order factory.
	 *
	 * @var OrderFactory
	 */
	protected $order_factory;

	/**
	 * The FraudNet entity.
	 *
	 * @var FraudNet
	 */
	protected $fraudnet;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * OrderEndpoint constructor.
	 *
	 * @param string          $host The host.
	 * @param Bearer          $bearer The bearer.
	 * @param OrderFactory    $order_factory The order factory.
	 * @param FraudNet        $fraudnet FrauNet entity.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		OrderFactory $order_factory,
		FraudNet $fraudnet,
		LoggerInterface $logger
	) {
		$this->host          = $host;
		$this->bearer        = $bearer;
		$this->order_factory = $order_factory;
		$this->logger        = $logger;
		$this->fraudnet      = $fraudnet;
	}

	/**
	 * Creates an order.
	 *
	 * @param PurchaseUnit[] $items The purchase unit items for the order.
	 * @param PaymentSource  $payment_source The payment source.
	 * @param WC_Order       $wc_order The WC order.
	 * @return Order
	 * @throws RuntimeException When there is a problem with the payment source.
	 * @throws PayPalApiException When there is a problem creating the order.
	 */
	public function create( array $items, PaymentSource $payment_source, WC_Order $wc_order ): Order {

		$data = array(
			'intent'                 => 'CAPTURE',
			'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
			'purchase_units'         => array_map(
				static function ( PurchaseUnit $item ): array {
					return $item->to_array( false );
				},
				$items
			),
			'payment_source'         => array(
				'pay_upon_invoice' => $payment_source->to_array(),
			),
		);

		$data = $this->ensure_taxes( $wc_order, $data );
		$data = $this->ensure_shipping( $data, $payment_source->to_array() );

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization'             => 'Bearer ' . $bearer->token(),
				'Content-Type'              => 'application/json',
				'Prefer'                    => 'return=representation',
				'PayPal-Client-Metadata-Id' => $this->fraudnet->session_id(),
				'PayPal-Request-Id'         => uniqid( 'ppcp-', true ), // Request-Id header is required.
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );
		if ( $response instanceof WP_Error ) {
			throw new RuntimeException( $response->get_error_message() );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			$issue = $json->details[0]->issue ?? null;

			$site_country_code = explode( '-', get_bloginfo( 'language' ) )[0] ?? '';
			if ( 'PAYMENT_SOURCE_INFO_CANNOT_BE_VERIFIED' === $issue ) {
				if ( 'de' === $site_country_code ) {
					throw new RuntimeException( 'Die Kombination aus Ihrem Namen und Ihrer Anschrift konnte nicht validiert werden. Bitte korrigieren Sie Ihre Daten und versuchen Sie es erneut. Weitere Informationen finden Sie in den Ratepay <a href="https://www.ratepay.com/legal-payment-dataprivacy/?lang=de" target="_blank">Datenschutzbestimmungen</a> oder nutzen Sie das Ratepay <a href="https://www.ratepay.com/kontakt/" target="_blank">Kontaktformular</a>.' );
				} else {
					throw new RuntimeException( 'The combination of your name and address could not be validated. Please correct your data and try again. You can find further information in the <a href="https://www.ratepay.com/en/ratepay-data-privacy-statement/" target="_blank">Ratepay Data Privacy Statement</a> or you can contact Ratepay using this <a href="https://www.ratepay.com/en/contact/" target="_blank">contact form</a>.' );
				}
			}
			if ( 'PAYMENT_SOURCE_DECLINED_BY_PROCESSOR' === $issue ) {
				if ( 'de' === $site_country_code ) {
					throw new RuntimeException( 'Die gewählte Zahlungsart kann nicht genutzt werden. Diese Entscheidung basiert auf einem automatisierten <a href="https://www.ratepay.com/legal-payment-dataprivacy/?lang=de" target="_blank">Datenverarbeitungsverfahren</a>. Weitere Informationen finden Sie in den Ratepay Datenschutzbestimmungen oder nutzen Sie das Ratepay <a href="https://www.ratepay.com/kontakt/" target="_blank">Kontaktformular</a>.' );
				} else {
					throw new RuntimeException( 'It is not possible to use the selected payment method. This decision is based on automated data processing. You can find further information in the <a href="https://www.ratepay.com/en/ratepay-data-privacy-statement/" target="_blank">Ratepay Data Privacy Statement</a> or you can contact Ratepay using this <a href="https://www.ratepay.com/en/contact/" target="_blank">contact form</a>.' );
				}
			}

			throw new PayPalApiException( $json, $status_code );
		}

		return $this->order_factory->from_paypal_response( $json );
	}

	/**
	 * Get PayPal order as object.
	 *
	 * @param string $id The PayPal order ID.
	 * @return stdClass
	 * @throws RuntimeException When there is a problem getting the order.
	 * @throws PayPalApiException When there is a problem getting the order.
	 */
	public function order( string $id ): stdClass {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v2/checkout/orders/' . $id;
		$args   = array(
			'headers' => array(
				'Authorization'     => 'Bearer ' . $bearer->token(),
				'Content-Type'      => 'application/json',
				'PayPal-Request-Id' => uniqid( 'ppcp-', true ),
			),
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

	/**
	 * Ensures purchase units contains shipping by using payment source data.
	 *
	 * @param array $data The data.
	 * @param array $payment_source The payment source.
	 * @return array
	 */
	private function ensure_shipping( array $data, array $payment_source ): array {
		if ( isset( $data['purchase_units'][0]['shipping'] ) ) {
			return $data;
		}

		$given_name = $payment_source['name']['given_name'] ?? '';
		$surname    = $payment_source['name']['surname'] ?? '';
		$address    = $payment_source['billing_address'] ?? array();

		$data['purchase_units'][0]['shipping']['name']    = array( 'full_name' => $given_name . ' ' . $surname );
		$data['purchase_units'][0]['shipping']['address'] = $address;

		return $data;
	}

	/**
	 * Ensure items contains taxes.
	 *
	 * @param WC_Order $wc_order The WC order.
	 * @param array    $data The data.
	 * @return array
	 */
	private function ensure_taxes( WC_Order $wc_order, array $data ): array {
		$tax_total  = $data['purchase_units'][0]['amount']['breakdown']['tax_total']['value'];
		$item_total = $data['purchase_units'][0]['amount']['breakdown']['item_total']['value'];
		$shipping   = $data['purchase_units'][0]['amount']['breakdown']['shipping']['value'];

		$handling          = isset( $data['purchase_units'][0]['amount']['breakdown']['handling'] ) ? $data['purchase_units'][0]['amount']['breakdown']['handling']['value'] : 0;
		$insurance         = isset( $data['purchase_units'][0]['amount']['breakdown']['insurance'] ) ? $data['purchase_units'][0]['amount']['breakdown']['insurance']['value'] : 0;
		$shipping_discount = isset( $data['purchase_units'][0]['amount']['breakdown']['shipping_discount'] ) ? $data['purchase_units'][0]['amount']['breakdown']['shipping_discount']['value'] : 0;
		$discount          = isset( $data['purchase_units'][0]['amount']['breakdown']['discount'] ) ? $data['purchase_units'][0]['amount']['breakdown']['discount']['value'] : 0;

		$order_tax_total = $wc_order->get_total_tax();
		$tax_rate        = round( ( $order_tax_total / $item_total ) * 100, 1 );

		$item_name        = $data['purchase_units'][0]['items'][0]['name'];
		$item_currency    = $data['purchase_units'][0]['items'][0]['unit_amount']['currency_code'];
		$item_description = $data['purchase_units'][0]['items'][0]['description'];
		$item_sku         = $data['purchase_units'][0]['items'][0]['sku'];

		unset( $data['purchase_units'][0]['items'] );
		$data['purchase_units'][0]['items'][0] = array(
			'name'        => $item_name,
			'unit_amount' => array(
				'currency_code' => $item_currency,
				'value'         => $item_total,
			),
			'quantity'    => 1,
			'description' => $item_description,
			'sku'         => $item_sku,
			'category'    => 'PHYSICAL_GOODS',
			'tax'         => array(
				'currency_code' => 'EUR',
				'value'         => $tax_total,
			),
			'tax_rate'    => number_format( $tax_rate, 2, '.', '' ),
		);

		$total_amount    = $data['purchase_units'][0]['amount']['value'];
		$breakdown_total = $item_total + $tax_total + $shipping + $handling + $insurance - $shipping_discount - $discount;
		$diff            = round( $total_amount - $breakdown_total, 2 );
		if ( $diff === -0.01 || $diff === 0.01 ) {
			$data['purchase_units'][0]['amount']['value'] = number_format( $breakdown_total, 2, '.', '' );
		}

		return $data;
	}
}
