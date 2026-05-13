<?php
/**
 * The SEPA Payment Gateway
 *
 * @package WooCommerce\PayPalCommerce\SEPA
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SEPA;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\ProcessPaymentTrait;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;

/**
 * Class SEPAGateway
 */
class SEPAGateway extends WC_Payment_Gateway {

	use ProcessPaymentTrait;

	const ID = 'ppcp-sepa';

	/**
	 * PayPal Orders endpoint.
	 *
	 * @var Orders
	 */
	private $orders_endpoint;

	/**
	 * Purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private $purchase_unit_factory;

	/**
	 * The processor for orders.
	 *
	 * @var OrderProcessor
	 */
	protected $order_processor;

	/**
	 * The function return the PayPal checkout URL for the given order ID.
	 *
	 * @var callable(string):string
	 */
	private $paypal_checkout_url_factory;

	/**
	 * The Refund Processor.
	 *
	 * @var RefundProcessor
	 */
	private $refund_processor;

	/**
	 * Service able to provide transaction url for an order.
	 *
	 * @var TransactionUrlProvider
	 */
	protected $transaction_url_provider;

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * SEPAGateway constructor.
	 *
	 * @param OrderProcessor          $order_processor The Order Processor.
	 * @param callable(string):string $paypal_checkout_url_factory The function return the PayPal checkout URL for the given order ID.
	 * @param RefundProcessor         $refund_processor The Refund Processor.
	 * @param TransactionUrlProvider  $transaction_url_provider Service providing transaction view URL based on order.
	 * @param SessionHandler          $session_handler The Session Handler.
	 * @param LoggerInterface         $logger The logger.
	 */
	public function __construct(
		Orders $orders_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		OrderProcessor $order_processor,
		callable $paypal_checkout_url_factory,
		RefundProcessor $refund_processor,
		TransactionUrlProvider $transaction_url_provider,
		SessionHandler $session_handler,
		LoggerInterface $logger
	) {
		$this->id = self::ID;

		$this->supports = array(
			'refunds',
			'products',
		);

		$this->method_title       = __( 'SEPA (via PayPal) ', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'SEPA enables customers to make cashless euro payments to any account located anywhere in the area, using a single bank account', 'woocommerce-paypal-payments' );

		$this->title       = $this->get_option( 'title', __( 'SEPA', 'woocommerce-paypal-payments' ) );
		$this->description = $this->get_option( 'description', '' );

		$this->init_form_fields();
		$this->init_settings();

		$this->orders_endpoint             = $orders_endpoint;
		$this->order_processor             = $order_processor;
		$this->paypal_checkout_url_factory = $paypal_checkout_url_factory;
		$this->refund_processor            = $refund_processor;
		$this->transaction_url_provider    = $transaction_url_provider;
		$this->session_handler             = $session_handler;
		$this->logger                      = $logger;

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
		$this->purchase_unit_factory = $purchase_unit_factory;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'SEPA', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable SEPA payment gateway.', 'woocommerce-paypal-payments' ),
			),
			'title'       => array(
				'title'       => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'        => 'text',
				'default'     => $this->title,
				'desc_tip'    => true,
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-paypal-payments' ),
				'type'        => 'text',
				'default'     => $this->description,
				'desc_tip'    => true,
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-paypal-payments' ),
			),
		);
	}

	/**
	 * Process payment for a WooCommerce order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, WC_Order::class ) ) {
			return $this->handle_payment_failure(
				null,
				new GatewayGenericException( new Exception( 'WC order was not found.' ) )
			);
		}

		$iban          = wc_clean( wp_unslash( $_POST['ppcp_sepa_iban'] ?? '' ) );
		$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$amount        = $purchase_unit->amount()->to_array();

		$request_body = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'reference_id' => $purchase_unit->reference_id(),
					'amount'       => array(
						'currency_code' => $amount['currency_code'],
						'value'         => $amount['value'],
					),
					'custom_id'    => $purchase_unit->custom_id(),
					'invoice_id'   => $purchase_unit->invoice_id(),
				),
			),
		);

		$response = $this->orders_endpoint->create( $request_body );
		$body     = json_decode( $response['body'] );

		$request_body = array(
			'payment_source' => array(
				'bank' => array(
					'sepa_debit' => array(
						'iban'                => $iban,
						'account_holder_name' => 'John Doe',
						'billing_address'     => array(
							'address_line_1' => 'Kantstraße 64',
							'address_line_2' => '#100',
							'admin_area_1'   => 'Freistaat Sachsen',
							'admin_area_2'   => 'Annaberg-buchholz',
							'postal_code'    => '09456',
							'country_code'   => 'DE',
						),
						'attributes'          => array(
							'mandate'  => array(
								'type' => 'ONE_OFF',
							),
							'customer' => array(
								'id' => 'jd120252fg4dm',
							),
						),
						'experience_context'  => array(
							'locale'     => 'en-DE',
							'return_url' => $this->get_return_url( $wc_order ),
							'cancel_url' => add_query_arg( 'cancelled', 'true', $this->get_return_url( $wc_order ) ),
						),
					),
				),
			),
		);

		$response = $this->orders_endpoint->confirm_payment_source( $request_body, $body->id );
		$body     = json_decode( $response['body'] );

		$payer_action = '';
		foreach ( $body->links as $link ) {
			if ( $link->rel === 'payer-action' ) {
				$payer_action = $link->href;
			}
		}

		WC()->cart->empty_cart();

		$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, $body->id );
		$wc_order->save_meta_data();

		return array(
			'result'   => 'success',
			'redirect' => esc_url( $payer_action ),
		);
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param int    $order_id Order ID.
	 * @param float  $amount Refund amount.
	 * @param string $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, \WC_Order::class ) ) {
			return false;
		}
		return $this->refund_processor->process( $order, (float) $amount, (string) $reason );
	}

	/**
	 * Return transaction url for this gateway and given order.
	 *
	 * @param \WC_Order $order WC order to get transaction url by.
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ): string {
		$this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base( $order );

		return parent::get_transaction_url( $order );
	}
}
