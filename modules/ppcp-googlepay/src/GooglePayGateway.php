<?php
/**
 * The Google Pay Payment Gateway
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\WcGateway\Exception\PayPalOrderMissingException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\Messages;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\ProcessPaymentTrait;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;

/**
 * Class GooglePayGateway
 */
class GooglePayGateway extends WC_Payment_Gateway {
	use ProcessPaymentTrait;

	const ID = 'ppcp-googlepay';

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
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * GooglePayGateway constructor.
	 *
	 * @param OrderProcessor          $order_processor The Order Processor.
	 * @param callable(string):string $paypal_checkout_url_factory The function return the PayPal checkout URL for the given order ID.
	 * @param RefundProcessor         $refund_processor The Refund Processor.
	 * @param TransactionUrlProvider  $transaction_url_provider Service providing transaction view URL based on order.
	 * @param SessionHandler          $session_handler The Session Handler.
	 * @param string                  $module_url The URL to the module.
	 * @param LoggerInterface         $logger The logger.
	 */
	public function __construct(
		OrderProcessor $order_processor,
		callable $paypal_checkout_url_factory,
		RefundProcessor $refund_processor,
		TransactionUrlProvider $transaction_url_provider,
		SessionHandler $session_handler,
		string $module_url,
		LoggerInterface $logger
	) {
		$this->id = self::ID;

		$this->supports = array(
			'refunds',
			'products',
		);

		$this->method_title       = __( 'Google Pay (via PayPal) ', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'The separate payment gateway with the Google Pay button. If disabled, the button is included in the PayPal gateway.', 'woocommerce-paypal-payments' );

		$this->title       = $this->get_option( 'title', __( 'Google Pay', 'woocommerce-paypal-payments' ) );
		$this->description = $this->get_option( 'description', '' );

		$this->module_url = $module_url;
		$this->icon       = esc_url( $this->module_url ) . 'assets/images/googlepay.svg';

		$this->init_form_fields();
		$this->init_settings();
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
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Google Pay', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable Google Pay payment gateway.', 'woocommerce-paypal-payments' ),
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

		/**
		 * If the WC_Order is paid through the approved webhook.
		 */
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['ppcp-resume-order'] ) && $wc_order->has_status( 'processing' ) ) {
			return $this->handle_payment_success( $wc_order );
		}
		//phpcs:enable WordPress.Security.NonceVerification.Recommended

		do_action( 'woocommerce_paypal_payments_before_process_order', $wc_order );

		try {
			try {
				$this->order_processor->process( $wc_order );

				do_action( 'woocommerce_paypal_payments_before_handle_payment_success', $wc_order );

				return $this->handle_payment_success( $wc_order );
			} catch ( PayPalOrderMissingException $exc ) {
				$order = $this->order_processor->create_order( $wc_order );

				return array(
					'result'   => 'success',
					'redirect' => ( $this->paypal_checkout_url_factory )( $order->id() ),
				);
			}
		} catch ( PayPalApiException $error ) {
			return $this->handle_payment_failure(
				$wc_order,
				new Exception(
					Messages::generic_payment_error_message() . ' ' . $error->getMessage(),
					$error->getCode(),
					$error
				)
			);
		} catch ( Exception $error ) {
			return $this->handle_payment_failure( $wc_order, $error );
		}
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
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
