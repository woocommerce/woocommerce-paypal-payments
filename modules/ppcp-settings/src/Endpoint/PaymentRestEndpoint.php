<?php
/**
 * REST endpoint to manage the payment methods page.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;

/**
 * REST controller for the "Payment Methods" settings tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
 */
class PaymentRestEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'payment';

	/**
	 * The settings instance.
	 *
	 * @var PaymentSettings
	 */
	protected PaymentSettings $settings;

	/**
	 * The payment method details.
	 *
	 * @var PaymentMethodsDefinition
	 */
	protected PaymentMethodsDefinition $methods_definition;

	/**
	 * Field mapping for request to profile transformation.
	 *
	 * @var array
	 */
	private array $field_map = array(
		'paypal_show_logo'           => array(
			'js_name'  => 'paypalShowLogo',
			'sanitize' => 'to_boolean',
		),
		'three_d_secure'             => array(
			'js_name'  => 'threeDSecure',
			'sanitize' => 'sanitize_text_field',
		),
		'fastlane_cardholder_name'   => array(
			'js_name'  => 'fastlaneCardholderName',
			'sanitize' => 'to_boolean',
		),
		'fastlane_display_watermark' => array(
			'js_name'  => 'fastlaneDisplayWatermark',
			'sanitize' => 'to_boolean',
		),
	);

	/**
	 * Constructor.
	 *
	 * @param PaymentSettings          $settings           The settings instance.
	 * @param PaymentMethodsDefinition $methods_definition Payment Method details.
	 */
	public function __construct( PaymentSettings $settings, PaymentMethodsDefinition $methods_definition ) {
		$this->settings           = $settings;
		$this->methods_definition = $methods_definition;
	}

	/**
	 * Field mapping for request to profile transformation.
	 *
	 * @return array[]
	 */
	protected function gateways() : array {
		return $this->methods_definition->get_definitions();
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() : void {
		/**
		 * GET wc/v3/wc_paypal/payment
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_details' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		/**
		 * POST wc/v3/wc_paypal/payment
		 * {
		 *     [gateway_id]: {
		 *         enabled
		 *         title
		 *         description
		 *     }
		 * }
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_details' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Returns all payment methods details.
	 *
	 * @return WP_REST_Response The current payment methods details.
	 */
	public function get_details() : WP_REST_Response {
		$gateway_settings = array();
		$all_methods      = $this->gateways();

		foreach ( $all_methods as $key => $method ) {
			$gateway_settings[ $key ] = array(
				'id'              => $method['id'],
				'title'           => $method['title'],
				'description'     => $method['description'],
				'enabled'         => $method['enabled'],
				'icon'            => $method['icon'],
				'itemTitle'       => $method['itemTitle'],
				'itemDescription' => $method['itemDescription'],
			);

			if ( isset( $method['fields'] ) ) {
				$gateway_settings[ $key ]['fields'] = $method['fields'];
			}
		}

		$gateway_settings['paypalShowLogo']           = $this->settings->get_paypal_show_logo();
		$gateway_settings['threeDSecure']             = $this->settings->get_three_d_secure();
		$gateway_settings['fastlaneCardholderName']   = $this->settings->get_fastlane_cardholder_name();
		$gateway_settings['fastlaneDisplayWatermark'] = $this->settings->get_fastlane_display_watermark();

		return $this->return_success( apply_filters( 'woocommerce_paypal_payments_payment_methods', $gateway_settings ) );
	}

	/**
	 * Updates payment methods details based on the request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The updated payment methods details.
	 */
	public function update_details( WP_REST_Request $request ) : WP_REST_Response {
		$request_data = $request->get_params();
		$all_methods  = $this->gateways();

		foreach ( $all_methods as $key => $value ) {
			$new_data = $request_data[ $key ];
			if ( ! $new_data ) {
				continue;
			}

			if ( isset( $new_data['enabled'] ) ) {
				$this->settings->toggle_method_state( $key, $new_data['enabled'] );
			}

			if ( isset( $new_data['title'] ) ) {
				$this->settings->set_method_title( $key, sanitize_text_field( $new_data['title'] ) );
			}

			if ( isset( $new_data['description'] ) ) {
				$this->settings->set_method_description( $key, wp_kses_post( $new_data['description'] ) );
			}
		}

		$wp_data = $this->sanitize_for_wordpress(
			$request->get_params(),
			$this->field_map
		);

		$this->settings->from_array( $wp_data );
		$this->settings->save();

		return $this->get_details();
	}
}
