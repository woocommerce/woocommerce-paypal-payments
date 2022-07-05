<?php
/**
 * The OXXO Gateway
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO;

use WC_Payment_Gateway;

/**
 * Class PayUponInvoiceGateway.
 */
class OXXOGateway extends WC_Payment_Gateway {
	const ID = 'ppcp-oxxo-gateway';

	/**
	 * OXXOGateway constructor.
	 */
	public function __construct() {
		 $this->id = self::ID;

		$this->method_title       = __( 'OXXO', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'OXXO is a Mexican chain of convenience stores.', 'woocommerce-paypal-payments' );

		$gateway_settings  = get_option( 'woocommerce_ppcp-oxxo-gateway_settings' );
		$this->title       = $gateway_settings['title'] ?? $this->method_title;
		$this->description = $gateway_settings['description'] ?? __( 'OXXO allows you to pay bills and online purchases in-store with cash.', 'woocommerce-paypal-payments' );

		$this->init_form_fields();
		$this->init_settings();

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
}
