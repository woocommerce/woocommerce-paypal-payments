<?php
/**
 * Pay with Crypto payment gateway.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;

/**
 * Class PWCGateway
 */
class PWCGateway extends WC_Payment_Gateway {

	public const ID = 'ppcp-pwc';

	/**
	 * PayPal Orders endpoint.
	 *
	 * @var Orders
	 */
	private Orders $orders_endpoint;

	/**
	 * Purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	private PurchaseUnitFactory $purchase_unit_factory;

	/**
	 * The Refund Processor.
	 *
	 * @var RefundProcessor
	 */
	private RefundProcessor $refund_processor;

	/**
	 * Service able to provide transaction url for an order.
	 *
	 * @var TransactionUrlProvider
	 */
	protected TransactionUrlProvider $transaction_url_provider;

	/**
	 * The ExperienceContextBuilder.
	 *
	 * @var ExperienceContextBuilder
	 */
	protected ExperienceContextBuilder $experience_context_builder;

	/**
	 * PWCGateway constructor.
	 *
	 * @param Orders                   $orders_endpoint PayPal Orders endpoint.
	 * @param PurchaseUnitFactory      $purchase_unit_factory Purchase unit factory.
	 * @param RefundProcessor          $refund_processor The Refund Processor.
	 * @param TransactionUrlProvider   $transaction_url_provider Service providing transaction view URL based on order.
	 * @param ExperienceContextBuilder $experience_context_builder The ExperienceContextBuilder.
	 */
	public function __construct(
		Orders $orders_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		RefundProcessor $refund_processor,
		TransactionUrlProvider $transaction_url_provider,
		ExperienceContextBuilder $experience_context_builder
	) {
		$this->id = self::ID;

		$this->supports = array(
			'refunds',
			'products',
		);

		$this->method_title       = __( 'Pay with Crypto (via PayPal)', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Accept cryptocurrency payments through PayPal, supporting various digital currencies for global customers.', 'woocommerce-paypal-payments' );

		$this->title       = $this->get_option( 'title', __( 'Pay with Crypto', 'woocommerce-paypal-payments' ) );
		$this->description = $this->get_option( 'description', '' );

		$this->icon = esc_url( 'https://www.paypalobjects.com/images/checkout/alternative_payments/paypal_crypto_color.svg' );

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		$this->orders_endpoint            = $orders_endpoint;
		$this->purchase_unit_factory      = $purchase_unit_factory;
		$this->refund_processor           = $refund_processor;
		$this->transaction_url_provider   = $transaction_url_provider;
		$this->experience_context_builder = $experience_context_builder;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Pay with Crypto', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable Pay with Crypto payment gateway.', 'woocommerce-paypal-payments' ),
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
	 * Processes the order.
	 *
	 * @param int $order_id The WC order ID.
	 * @return array
	 */
	public function process_payment( $order_id ): array {
		// TODO: Implement pwc payment processing
		return array();
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return bool True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): bool {
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
