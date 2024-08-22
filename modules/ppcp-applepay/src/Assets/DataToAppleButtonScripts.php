<?php
/**
 * Prepares the necessary data for the Apple button script.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WC_Product;

/**
 * Class DataToAppleButtonScripts
 */
class DataToAppleButtonScripts {

	/**
	 * The URL to the SDK.
	 *
	 * @var string
	 */
	private $sdk_url;
	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * DataToAppleButtonScripts constructor.
	 *
	 * @param string   $sdk_url  The URL to the SDK.
	 * @param Settings $settings The settings.
	 */
	public function __construct( string $sdk_url, Settings $settings ) {
		$this->sdk_url  = $sdk_url;
		$this->settings = $settings;
	}

	/**
	 * Sets the appropriate data to send to ApplePay script
	 * Data differs between product page and cart page
	 *
	 * @return array
	 */
	public function apple_pay_script_data() : array {
		if ( is_product() ) {
			return $this->data_for_product_page();
		}

		return $this->data_for_cart_page();
	}

	/**
	 * Returns the appropriate admin data to send to ApplePay script
	 *
	 * @return array
	 */
	public function apple_pay_script_data_for_admin() : array {
		return $this->data_for_admin_page();
	}

	/**
	 * Returns the full config array for the Apple Pay integration with default values.
	 *
	 * @param array $product - Optional. Product details for the payment button.
	 *
	 * @return array
	 */
	private function get_apple_pay_data( array $product = array() ) : array {
		// true: Use Apple Pay as distinct gateway.
		// false: integrate it with the smart buttons.
		$available_gateways    = WC()->payment_gateways->get_available_payment_gateways();
		$is_wc_gateway_enabled = isset( $available_gateways[ ApplePayGateway::ID ] );

		// use_wc: Use WC checkout data
		// use_applepay: Use data provided by Apple Pay.
		$checkout_data_mode = $this->settings->has( 'applepay_checkout_data_mode' )
			? $this->settings->get( 'applepay_checkout_data_mode' )
			: PropertiesDictionary::BILLING_DATA_MODE_DEFAULT;

		// Store country, currency and name.
		$base_location     = wc_get_base_location();
		$shop_country_code = $base_location['country'];
		$currency_code     = get_woocommerce_currency();
		$total_label       = get_bloginfo( 'name' );

		// Button layout (label, color, language).
		$type       = $this->settings->has( 'applepay_button_type' ) ? $this->settings->get( 'applepay_button_type' ) : '';
		$color      = $this->settings->has( 'applepay_button_color' ) ? $this->settings->get( 'applepay_button_color' ) : '';
		$lang       = $this->settings->has( 'applepay_button_language' ) ? $this->settings->get( 'applepay_button_language' ) : '';
		$lang       = apply_filters( 'woocommerce_paypal_payments_applepay_button_language', $lang );
		$is_enabled = $this->settings->has( 'applepay_button_enabled' ) && $this->settings->get( 'applepay_button_enabled' );

		return array(
			'sdk_url'               => $this->sdk_url,
			'is_debug'              => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'is_admin'              => false,
			'is_enabled'            => $is_enabled,
			'is_wc_gateway_enabled' => $is_wc_gateway_enabled,
			'preferences'           => array(
				'checkout_data_mode' => $checkout_data_mode,
			),
			'button'                => array(
				'wrapper'           => 'ppc-button-applepay-container',
				'mini_cart_wrapper' => 'ppc-button-applepay-container-minicart',
				'type'              => $type,
				'color'             => $color,
				'lang'              => $lang,
			),
			'product'               => $product,
			'shop'                  => array(
				'countryCode'  => $shop_country_code,
				'currencyCode' => $currency_code,
				'totalLabel'   => $total_label,
			),
			'ajax_url'              => admin_url( 'admin-ajax.php' ),
			'nonce'                 => wp_create_nonce( 'woocommerce-process_checkout' ),
		);
	}

	/**
	 * Check if the product needs shipping
	 *
	 * @param WC_Product $product Product to check.
	 *
	 * @return bool
	 */
	protected function check_if_need_shipping( WC_Product $product ) : bool {
		if (
			! wc_shipping_enabled()
			|| 0 === wc_get_shipping_method_count(
				true
			)
		) {
			return false;
		}

		if ( $product->needs_shipping() ) {
			return true;
		}

		return false;
	}

	/**
	 * Prepares the data for the product page.
	 *
	 * @return array
	 */
	protected function data_for_product_page() : array {
		$product = wc_get_product( get_the_id() );
		if ( ! $product ) {
			return array();
		}
		$is_variation = false;
		if ( $product->get_type() === 'variable' || $product->get_type() === 'variable-subscription' ) {
			$is_variation = true;
		}

		$product_need_shipping = $this->check_if_need_shipping( $product );
		$product_id            = get_the_id();
		$product_price         = $product->get_price();
		$product_stock         = $product->get_stock_status();

		return $this->get_apple_pay_data(
			array(
				'needShipping' => $product_need_shipping,
				'id'           => $product_id,
				'price'        => $product_price,
				'isVariation'  => $is_variation,
				'stock'        => $product_stock,
			)
		);
	}

	/**
	 * Prepares the data for the cart page.
	 *
	 * @return array
	 */
	protected function data_for_cart_page() : array {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return array();
		}

		return $this->get_apple_pay_data(
			array(
				'needShipping' => $cart->needs_shipping(),
				'subtotal'     => $cart->get_subtotal(),
			)
		);
	}

	/**
	 * Prepares the data for the cart page.
	 * Consider refactoring this method along with data_for_cart_page() and data_for_product_page()
	 * methods.
	 *
	 * @return array
	 */
	protected function data_for_admin_page() : array {
		$data = $this->get_apple_pay_data(
			array(
				'needShipping' => false,
				'subtotal'     => 0,
			)
		);

		$data['is_admin'] = true;

		return $data;
	}
}
