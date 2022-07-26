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
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\FraudNet;
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
	 * @return Order
	 * @throws RuntimeException When there is a problem with the payment source.
	 * @throws PayPalApiException When there is a problem creating the order.
	 */
	public function create( array $items, PaymentSource $payment_source ): Order {

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

		$data = $this->ensure_tax( $data );
		$data = $this->ensure_tax_rate( $data );
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
				'PayPal-Request-Id'         => uniqid( 'ppcp-', true ),
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
	 * Ensures items contains tax.
	 *
	 * @param array $data The data.
	 * @return array
	 */
	private function ensure_tax( array $data ): array {
		$items_count = count( $data['purchase_units'][0]['items'] );

		for ( $i = 0; $i < $items_count; $i++ ) {
			if ( ! isset( $data['purchase_units'][0]['items'][ $i ]['tax'] ) ) {
				$data['purchase_units'][0]['items'][ $i ]['tax'] = array(
					'currency_code' => 'EUR',
					'value'         => '0.00',
				);
			}
		}

		return $data;
	}

	/**
	 * Ensures items contains tax rate.
	 *
	 * @param array $data The data.
	 * @return array
	 */
	private function ensure_tax_rate( array $data ): array {
		$items_count = count( $data['purchase_units'][0]['items'] );

		for ( $i = 0; $i < $items_count; $i++ ) {
			if ( ! isset( $data['purchase_units'][0]['items'][ $i ]['tax_rate'] ) ) {
				$data['purchase_units'][0]['items'][ $i ]['tax_rate'] = '0.00';
			}
		}

		return $data;
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
}
