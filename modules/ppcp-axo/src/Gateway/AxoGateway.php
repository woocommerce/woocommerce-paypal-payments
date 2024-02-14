<?php
/**
 * The AXO Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Gateway;

use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AXOGateway.
 */
class AxoGateway extends WC_Payment_Gateway {

	const ID = 'ppcp-axo-gateway';

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $ppcp_settings;

	/**
	 * AXOGateway constructor.
	 *
	 * @param ContainerInterface $ppcp_settings The settings.
	 */
	public function __construct(
		ContainerInterface $ppcp_settings
	) {
		$this->ppcp_settings = $ppcp_settings;

		$this->id = self::ID;

		$this->method_title       = __( 'Fastlane Debit & Credit Cards', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Fastlane Debit & Credit Cards', 'woocommerce-paypal-payments' );

		$is_axo_enabled = $this->ppcp_settings->has( 'axo_enabled' ) && $this->ppcp_settings->get( 'axo_enabled' );
		$this->update_option( 'enabled', $is_axo_enabled ? 'yes' : 'no' );

		$this->title = $this->ppcp_settings->has( 'axo_gateway_title' )
			? $this->ppcp_settings->get( 'axo_gateway_title' )
			: $this->get_option( 'title', $this->method_title );

		$this->description = $this->get_option( 'description', __( '', 'woocommerce-paypal-payments' ) );

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

//		$this->order_endpoint              = $order_endpoint;
//		$this->purchase_unit_factory       = $purchase_unit_factory;
//		$this->shipping_preference_factory = $shipping_preference_factory;
//		$this->module_url                  = $module_url;
//		$this->logger                      = $logger;

//		$this->icon                     = esc_url( $this->module_url ) . 'assets/images/axo.svg'; // TODO
//		$this->environment              = $environment;
	}

	/**
	 * Initialize the form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'AXO', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable AXO payment gateway.', 'woocommerce-paypal-payments' ),
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

		// TODO ...

		WC()->cart->empty_cart();

		$result = array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $wc_order ),
		);

		return $result;
	}

//	public function is_available()
//	{
//		return $this->is_enabled(); // parent::is_available();
//	}
//
//	/**
//	 * Returns if the gateway is enabled.
//	 *
//	 * @return bool
//	 */
//	private function is_enabled(): bool {
//		return true;
//		//return $this->ppcp_settings->has( 'axo_enabled' ) && $this->ppcp_settings->get( 'axo_enabled' ); // TODO
//	}

	/**
	 * Returns the icons of the gateway.
	 *
	 * @return string
	 */
	public function get_icon() {
		$images = array();

		$cards = array(
			array('title' => 'Visa',             'file' => 'visa-dark.svg'),
			array('title' => 'MasterCard',       'file' => 'mastercard-dark.svg'),
			array('title' => 'American Express', 'file' => 'amex.svg'),
			array('title' => 'Discover',         'file' => 'discover.svg'),
		);

		foreach ($cards as $card) {
			$images[] = '<img
				class="ppcp-card-icon"
				title="' . $card['title'] . '"
				src="/wp-content/plugins/woocommerce-paypal-payments/modules/ppcp-wc-gateway/assets/images/' . $card['file'] . '"
			> ';
		}

		return '<div class="axo-card-icons">' . implode( '', $images ) . '</div>';
	}

}
