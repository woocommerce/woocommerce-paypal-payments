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
	 * @param WC_Order $wc_order
	 * @param array    $data
	 * @param array    $items
	 * @return array
	 */
	private function ensure_taxes( WC_Order $wc_order, array $data ): array {
		$items = array_map(
			function ( WC_Order_Item_Product $item ) use ( $wc_order ): Item {
				$product     = $item->get_product();
				$currency    = $wc_order->get_currency();
				$quantity    = $item->get_quantity();
				$unit_amount = $wc_order->get_item_subtotal( $item, false, false );
				$tax_rates   = WC_Tax::get_rates( $product->get_tax_class() );
				$tax_rate    = reset( $tax_rates )['rate'] ?? 0;
				$tax         = $unit_amount * ( $tax_rate / 100 );
				$tax         = new Money( $tax, $currency );

				return new Item(
					mb_substr( $item->get_name(), 0, 127 ),
					new Money( $wc_order->get_item_subtotal( $item, false, false ), $currency ),
					$quantity,
					substr(
						wp_strip_all_tags( $product instanceof WC_Product ? $product->get_description() : '' ),
						0,
						127
					) ?: '',
					$tax,
					$product instanceof WC_Product ? $product->get_sku() : '',
					( $product instanceof WC_Product && $product->is_virtual() ) ? Item::DIGITAL_GOODS : Item::PHYSICAL_GOODS,
					$tax_rate
				);
			},
			$wc_order->get_items(),
			array_keys( $wc_order->get_items() )
		);

		$fees = array_map(
			function ( WC_Order_Item_Fee $item ) use ( $wc_order ): Item {
				$currency    = $wc_order->get_currency();
				$unit_amount = $item->get_amount();
				$total_tax   = $item->get_total_tax();
				$tax_rate    = ( $total_tax / $unit_amount ) * 100;
				$tax         = $unit_amount * ( $tax_rate / 100 );
				$tax         = new Money( $tax, $currency );

				return new Item(
					$item->get_name(),
					new Money( (float) $item->get_amount(), $wc_order->get_currency() ),
					$item->get_quantity(),
					'',
					$tax,
					'',
					'PHYSICAL_GOODS',
					$tax_rate
				);
			},
			$wc_order->get_fees()
		);

		$items = array_merge( $items, $fees );

		$items_count = count( $data['purchase_units'][0]['items'] );
		for ( $i = 0; $i < $items_count; $i++ ) {
			if ( ! isset( $data['purchase_units'][0]['items'][ $i ]['tax'] ) ) {
				$data['purchase_units'][0]['items'][ $i ] = $items[ $i ]->to_array();
			}
		}

		$shipping  = (float) $wc_order->calculate_shipping();
		$total     = 0;
		$tax_total = 0;
		foreach ( $items as $item ) {
			$unit_amount = (float) $item->unit_amount()->value();
			$tax         = (float) $item->tax()->value();
			$qt          = $item->quantity();

			$total     += ( ( $unit_amount + $tax ) * $qt );
			$tax_total += $tax * $qt;
		}

		$data['purchase_units'][0]['amount']['value']                           = number_format( $total + $shipping, 2, '.', '' );
		$data['purchase_units'][0]['amount']['breakdown']['tax_total']['value'] = number_format( $tax_total, 2, '.', '' );

		$shipping_taxes = (float) $wc_order->get_shipping_tax();

		$fees_taxes = 0;
		foreach ( $wc_order->get_fees() as $fee ) {
			$unit_amount = $fee->get_amount();
			$total_tax   = $fee->get_total_tax();
			$tax_rate    = ( $total_tax / $unit_amount ) * 100;
			$tax         = $unit_amount * ( $tax_rate / 100 );

			$fees_taxes += $tax;
		}

		if ( $shipping_taxes > 0 || $fees_taxes > 0 ) {
			$name     = $data['purchase_units'][0]['items'][0]['name'];
			$category = $data['purchase_units'][0]['items'][0]['category'];
			$tax_rate = $data['purchase_units'][0]['items'][0]['tax_rate'];

			unset( $data['purchase_units'][0]['items'] );
			$data['purchase_units'][0]['items'][0] = array(
				'name'        => $name,
				'unit_amount' => array(
					'currency_code' => 'EUR',
					'value'         => $data['purchase_units'][0]['amount']['breakdown']['item_total']['value'],
				),
				'category'    => $category,
				'quantity'    => 1,
				'tax'         => array(
					'currency_code' => 'EUR',
					'value'         => number_format( $tax_total + $shipping_taxes, 2, '.', '' ),
				),
				'tax_rate'    => $tax_rate,
			);

			$data['purchase_units'][0]['amount']['value']                           = number_format( $total + $shipping + $shipping_taxes, 2, '.', '' );
			$data['purchase_units'][0]['amount']['breakdown']['tax_total']['value'] = number_format( $tax_total + $shipping_taxes, 2, '.', '' );
		}

		return $data;
	}
}
