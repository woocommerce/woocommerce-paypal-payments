<?php
/**
 * The Bancontact payment gateway.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WC_Payment_Gateway;

class BancontactGateway extends WC_Payment_Gateway {

	const ID = 'ppcp-bancontact';

	public function __construct() {
		$this->id = self::ID;

		$this->method_title       = __( 'Bancontact', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Bancontact', 'woocommerce-paypal-payments' );

		$this->title       = $this->get_option( 'title', __( 'Bancontact', 'woocommerce-paypal-payments' ) );
		$this->description = $this->get_option( 'description', '' );

		$this->icon = esc_url( 'https://www.paypalobjects.com/images/checkout/alternative_payments/paypal_bancontact_color.svg' );

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Bancontact', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'desc_tip'    => true,
				'description' => __( 'Enable/Disable Bancontact payment gateway.', 'woocommerce-paypal-payments' ),
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

	public function process_payment( $order_id ) {
		$wc_order = wc_get_order( $order_id );



		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $wc_order ),
		);
	}
}
