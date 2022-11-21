<?php
/**
 * The OXXO Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO;

use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;

/**
 * Class OXXOGateway.
 */
class OXXOGateway extends WC_Payment_Gateway {

	use OrderMetaTrait;

	const ID = 'ppcp-oxxo-gateway';

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	protected $purchase_unit_factory;

	/**
	 * The shipping preference factory.
	 *
	 * @var ShippingPreferenceFactory
	 */
	protected $shipping_preference_factory;

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The transaction url provider.
	 *
	 * @var TransactionUrlProvider
	 */
	protected $transaction_url_provider;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * OXXOGateway constructor.
	 *
	 * @param OrderEndpoint             $order_endpoint The order endpoint.
	 * @param PurchaseUnitFactory       $purchase_unit_factory The purchase unit factory.
	 * @param ShippingPreferenceFactory $shipping_preference_factory The shipping preference factory.
	 * @param string                    $module_url The URL to the module.
	 * @param TransactionUrlProvider    $transaction_url_provider The transaction url provider.
	 * @param Environment               $environment The environment.
	 * @param LoggerInterface           $logger The logger.
	 */
	public function __construct(
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		string $module_url,
		TransactionUrlProvider $transaction_url_provider,
		Environment $environment,
		LoggerInterface $logger
	) {
		$this->id = self::ID;

		$this->method_title       = __( 'OXXO', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'OXXO is a Mexican chain of convenience stores.<br />*Get PayPal account permission to use OXXO payment functionality by contacting us at (+52) 800-925-0304', 'woocommerce-paypal-payments' );

		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->description = $this->get_option( 'description', __( 'OXXO allows you to pay bills and online purchases in-store with cash.', 'woocommerce-paypal-payments' ) );

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		$this->order_endpoint              = $order_endpoint;
		$this->purchase_unit_factory       = $purchase_unit_factory;
		$this->shipping_preference_factory = $shipping_preference_factory;
		$this->module_url                  = $module_url;
		$this->logger                      = $logger;

		$this->icon                     = esc_url( $this->module_url ) . 'assets/images/oxxo.svg';
		$this->transaction_url_provider = $transaction_url_provider;
		$this->environment              = $environment;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'OXXO', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable OXXO payment gateway.', 'woocommerce-paypal-payments' ),
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
	public function process_payment( $order_id ) {
		$wc_order      = wc_get_order( $order_id );
		$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
		$payer_action  = '';

		try {
			$shipping_preference = $this->shipping_preference_factory->from_state(
				$purchase_unit,
				'checkout'
			);

			$order = $this->order_endpoint->create( array( $purchase_unit ), $shipping_preference );
			$this->add_paypal_meta( $wc_order, $order, $this->environment );

			$payment_source = array(
				'oxxo' => array(
					'name'         => $wc_order->get_billing_first_name() . ' ' . $wc_order->get_billing_last_name(),
					'email'        => $wc_order->get_billing_email(),
					'country_code' => $wc_order->get_billing_country(),
				),
			);
			$payment_method = $this->order_endpoint->confirm_payment_source( $order->id(), $payment_source );
			foreach ( $payment_method->links as $link ) {
				if ( $link->rel === 'payer-action' ) {
					$payer_action = $link->href;
					$wc_order->add_meta_data( 'ppcp_oxxo_payer_action', $payer_action );
					$wc_order->save_meta_data();
				}
			}
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$this->logger->error( $error );
			wc_add_notice( $error, 'error' );

			$wc_order->update_status(
				'failed',
				$error
			);

			return array(
				'result'   => 'failure',
				'redirect' => wc_get_checkout_url(),
			);
		}

		WC()->cart->empty_cart();

		$result = array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $wc_order ),
		);

		if ( $payer_action ) {
			$result['payer_action'] = $payer_action;
		}

		return $result;
	}

	/**
	 * Return transaction url for this gateway and given order.
	 *
	 * @param WC_Order $order WC order to get transaction url by.
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ): string {
		$this->view_transaction_url = $this->transaction_url_provider->get_transaction_url_base( $order );

		return parent::get_transaction_url( $order );
	}
}
