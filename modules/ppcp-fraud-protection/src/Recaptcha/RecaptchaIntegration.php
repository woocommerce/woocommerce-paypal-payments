<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection\Recaptcha;

use WC_Integration;

class RecaptchaIntegration extends WC_Integration {
	public const ID = 'ppcp-recaptcha';

	public function __construct() {

		$this->id                 = self::ID;
		$this->method_title       = __( 'WooCommerce PayPal Payments reCAPTCHA', 'woocommerce-paypal-payments' );
		$this->method_description = __(
			'Protects PayPal for WooCommerce checkout and cart with Google reCAPTCHA v3 (primary) and v2 (fallback).',
			'woocommerce-paypal-payments'
		);

		$this->init_form_fields();
		$this->init_settings();

		add_action(
			'woocommerce_update_options_integration_' . $this->id,
			array( $this, 'process_admin_options' )
		);
	}

	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable reCAPTCHA protection', 'woocommerce-paypal-payments' ),
				'default' => 'no',
			),

			'v3_title'        => array(
				'title'       => __( 'reCAPTCHA v3 Settings', 'woocommerce-paypal-payments' ),
				'type'        => 'title',
				'description' => sprintf(
					// translators: %s - URL.
					__(
						'Primary invisible protection. To get the keys go to <a href="%s" target="_blank">Google reCAPTCHA Admin</a> and create a site with <b>Score based (v3)</b> reCAPTCHA type.',
						'woocommerce-paypal-payments'
					),
					'https://www.google.com/recaptcha/admin'
				),
			),
			'site_key_v3'     => array(
				'title'       => __( 'v3 Site Key', 'woocommerce-paypal-payments' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Your reCAPTCHA v3 site key', 'woocommerce-paypal-payments' ),
			),
			'secret_key_v3'   => array(
				'title'       => __( 'v3 Secret Key', 'woocommerce-paypal-payments' ),
				'type'        => 'password',
				'desc_tip'    => true,
				'description' => __( 'Your reCAPTCHA v3 secret key', 'woocommerce-paypal-payments' ),
			),
			'score_threshold' => array(
				'title'             => __( 'Score Threshold', 'woocommerce-paypal-payments' ),
				'type'              => 'number',
				'default'           => '0.5',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '1',
					'step' => '0.1',
				),
				'desc_tip'          => true,
				'description'       => __(
					'Minimum score to pass (0.0–1.0). Lower scores trigger v2 fallback. Recommended: 0.5',
					'woocommerce-paypal-payments'
				),
			),

			'v2_title'        => array(
				'title'       => __( 'reCAPTCHA v2 Settings', 'woocommerce-paypal-payments' ),
				'type'        => 'title',
				// translators: %s - URL.
				'description' => __(
					'Fallback visible checkbox when v3 score is below threshold. To get the keys go to <a href="%s" target="_blank">Google reCAPTCHA Admin</a> and create a site with <b>Challenge (v2) -> "I\'m not a robot" Checkbox</b> reCAPTCHA type.',
					'woocommerce-paypal-payments'
				),
			),
			'site_key_v2'     => array(
				'title'       => __( 'v2 Site Key', 'woocommerce-paypal-payments' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Your reCAPTCHA v2 (checkbox) site key', 'woocommerce-paypal-payments' ),
			),
			'secret_key_v2'   => array(
				'title'       => __( 'v2 Secret Key', 'woocommerce-paypal-payments' ),
				'type'        => 'password',
				'desc_tip'    => true,
				'description' => __( 'Your reCAPTCHA v2 secret key', 'woocommerce-paypal-payments' ),
			),
			'v2_theme'        => array(
				'title'       => __( 'v2 Theme', 'woocommerce-paypal-payments' ),
				'type'        => 'select',
				'default'     => 'light',
				'options'     => array(
					'light' => __( 'Light', 'woocommerce-paypal-payments' ),
					'dark'  => __( 'Dark', 'woocommerce-paypal-payments' ),
				),
				'desc_tip'    => true,
				'description' => __( 'Color theme for the v2 checkbox', 'woocommerce-paypal-payments' ),
			),

			'scope_title'     => array(
				'title'       => __( 'Protection Scope', 'woocommerce-paypal-payments' ),
				'type'        => 'title',
				'description' => __( 'Configure where reCAPTCHA protection is applied', 'woocommerce-paypal-payments' ),
			),
			'guest_only'      => array(
				'title'       => __( 'Guest Orders Only', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Only verify for non-logged-in users', 'woocommerce-paypal-payments' ),
				'default'     => 'yes',
				'description' => __(
					'Skip reCAPTCHA for logged-in customers',
					'woocommerce-paypal-payments'
				),
			),

			'advanced_title'  => array(
				'title' => __( 'Advanced Options', 'woocommerce-paypal-payments' ),
				'type'  => 'title',
			),
			'show_metabox'    => array(
				'title'       => __( 'Order Metabox', 'woocommerce-paypal-payments' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show reCAPTCHA status metabox on order pages', 'woocommerce-paypal-payments' ),
				'default'     => 'no',
				'description' => __(
					'Display reCAPTCHA verification details in a metabox on order edit pages',
					'woocommerce-paypal-payments'
				),
			),
		);
	}
}
