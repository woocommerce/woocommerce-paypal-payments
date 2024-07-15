<?php
/**
 * The Google Pay Payment Gateway
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use WC_Payment_Gateway;

class GooglePayGateway extends WC_Payment_Gateway {
	const ID = 'ppcp-googlepay';

	public function __construct() {
		$this->id = self::ID;

		$this->method_title       = __( 'Google Pay', 'woocommerce-paypal-payments' );
		$this->method_description = __( 'Google Pay', 'woocommerce-paypal-payments' );

		$this->title       = $this->get_option( 'title', $this->method_title );
		$this->description = $this->get_option( 'description', $this->method_description );

		$this->init_form_fields();
		$this->init_settings();
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
}
