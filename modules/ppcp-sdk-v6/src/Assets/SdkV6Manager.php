<?php
/**
 * Manages the SDK v6 frontend assets.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

/**
 * Class SdkV6Manager
 */
class SdkV6Manager {

	public const WRAPPER_ID           = 'ppc-button-ppcp-gateway-v6';
	public const MINI_CART_WRAPPER_ID = 'ppc-button-minicart-v6';

	/**
	 * The asset getter.
	 *
	 * @var AssetGetter
	 */
	private AssetGetter $asset_getter;

	/**
	 * The assets version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * The environment object.
	 *
	 * @var Environment
	 */
	private Environment $environment;

	/**
	 * The button style mapper.
	 *
	 * @var ButtonStyleMapper
	 */
	private ButtonStyleMapper $style_mapper;

	/**
	 * Whether shipping should be handled inside the PayPal popup.
	 *
	 * @var bool
	 */
	private bool $should_handle_shipping;

	/**
	 * SdkV6Manager constructor.
	 *
	 * @param AssetGetter       $asset_getter The asset getter.
	 * @param string            $version The assets version.
	 * @param Environment       $environment The environment object.
	 * @param ButtonStyleMapper $style_mapper The button style mapper.
	 * @param bool              $should_handle_shipping Whether to handle shipping in PayPal.
	 */
	public function __construct(
		AssetGetter $asset_getter,
		string $version,
		Environment $environment,
		ButtonStyleMapper $style_mapper,
		bool $should_handle_shipping
	) {
		$this->asset_getter           = $asset_getter;
		$this->version                = $version;
		$this->environment            = $environment;
		$this->style_mapper           = $style_mapper;
		$this->should_handle_shipping = $should_handle_shipping;
	}

	/**
	 * Enqueues scripts/styles.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		$script_url = $this->asset_getter->get_asset_url( 'boot.js' );
		if ( ! $script_url ) {
			return;
		}

		wp_register_script(
			'wc-ppcp-sdk-v6-boot',
			$script_url,
			array(),
			$this->version,
			true
		);

		wp_localize_script(
			'wc-ppcp-sdk-v6-boot',
			'wc_ppcp_sdk_v6',
			$this->script_data()
		);

		wp_enqueue_script( 'wc-ppcp-sdk-v6-boot' );
	}

	/**
	 * Registers the render hooks that output the button wrapper elements.
	 *
	 * Uses the same theme hooks as the v5 SmartButton so v6 buttons appear
	 * in the same locations.
	 *
	 * @return void
	 */
	public function register_render_hooks(): void {
		add_action(
			'woocommerce_single_product_summary',
			function (): void {
				$this->render_wrapper();
			},
			31
		);

		add_action(
			'woocommerce_proceed_to_checkout',
			function (): void {
				if ( ! is_cart() ) {
					return;
				}
				$this->render_wrapper();
			},
			20
		);

		add_action(
			'woocommerce_review_order_after_payment',
			function (): void {
				$this->render_wrapper();
			}
		);

		add_action(
			'woocommerce_widget_shopping_cart_after_buttons',
			function (): void {
				echo '<p class="woocommerce-mini-cart__buttons buttons">';
				echo '<span id="' . esc_attr( self::MINI_CART_WRAPPER_ID ) . '"></span>';
				echo '</p>';
			},
			30
		);
	}

	/**
	 * Outputs the main button wrapper element.
	 *
	 * @return void
	 */
	private function render_wrapper(): void {
		echo '<div class="ppc-button-wrapper"><div id="' . esc_attr( self::WRAPPER_ID ) . '"></div></div>';
	}

	/**
	 * Whether the scripts should be enqueued on the current page.
	 *
	 * Mini-cart can appear on any frontend page, so the script is also
	 * enqueued when the classic cart widget is active; the bootstrap
	 * only loads the SDK once a button wrapper exists in the DOM.
	 *
	 * @return bool
	 */
	private function should_enqueue(): bool {
		if ( is_product() || is_checkout() || is_cart() ) {
			return true;
		}

		if ( is_active_widget( false, false, 'woocommerce_widget_cart' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The configuration data for the SDK v6 bootstrap script.
	 *
	 * @return array
	 */
	private function script_data(): array {
		$base_url = $this->environment->is_sandbox()
			? 'https://www.sandbox.paypal.com'
			: 'https://www.paypal.com';

		$buyer_country = WC()->customer ? WC()->customer->get_billing_country() : '';
		if ( ! $buyer_country ) {
			$buyer_country = wc_get_base_location()['country'] ?? '';
		}

		$shipping_enabled = $this->should_handle_shipping && ! is_checkout();

		$store_api_base = rtrim( rest_url( 'wc/store/v1/cart' ), '/' );

		return array(
			'sdk_url'           => $base_url . '/web-sdk/v6/core',
			'page_context'      => $this->get_page_context(),
			'currency'          => get_woocommerce_currency(),
			'amount'            => $this->transaction_amount(),
			'buyer_country'     => $buyer_country,
			'locale'            => str_replace( '_', '-', get_locale() ),
			'ajax'              => array(
				'client_token'    => array(
					'endpoint' => \WC_AJAX::get_endpoint( ClientTokenEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ClientTokenEndpoint::nonce() ),
				),
				'change_cart'     => array(
					'endpoint' => \WC_AJAX::get_endpoint( ChangeCartEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ChangeCartEndpoint::nonce() ),
				),
				'create_order'    => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreateOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreateOrderEndpoint::nonce() ),
				),
				'approve_order'   => array(
					'endpoint' => \WC_AJAX::get_endpoint( ApproveOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ApproveOrderEndpoint::nonce() ),
				),
				'update_shipping' => array(
					'endpoint' => \WC_AJAX::get_endpoint( UpdateShippingEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( UpdateShippingEndpoint::nonce() ),
				),
				'wc_store_api'    => array(
					'select_shipping_rate' => $store_api_base . '/select-shipping-rate',
					'update_customer'      => $store_api_base . '/update-customer',
					'nonce'                => wp_create_nonce( 'wc_store_api' ),
				),
			),
			'urls'              => array(
				'checkout' => wc_get_checkout_url(),
			),
			'labels'            => array(
				'generic_error' => __(
					'Something went wrong. Please try again or choose another payment source.',
					'woocommerce-paypal-payments'
				),
			),
			'shipping'          => array(
				'handle_in_paypal' => $shipping_enabled,
				'need_shipping'    => $this->need_shipping(),
			),
			'button_styles'     => array(
				'product'   => $this->style_mapper->styles_for_context( 'product' ),
				'cart'      => $this->style_mapper->styles_for_context( 'cart' ),
				'checkout'  => $this->style_mapper->styles_for_context( 'checkout' ),
				'mini-cart' => $this->style_mapper->styles_for_context( 'mini-cart' ),
			),
			'wrapper'           => '#' . self::WRAPPER_ID,
			'mini_cart_wrapper' => '#' . self::MINI_CART_WRAPPER_ID,
		);
	}

	/**
	 * Whether the current cart needs shipping.
	 *
	 * @return bool
	 */
	private function need_shipping(): bool {
		$cart = WC()->cart;
		return $cart && $cart->needs_shipping();
	}

	/**
	 * Returns the expected transaction amount for eligibility checks
	 * (affects Pay Later thresholds): the cart total, or the product
	 * price on product pages while the cart is empty.
	 *
	 * @return string The amount as a decimal string, or empty when unknown.
	 */
	private function transaction_amount(): string {
		$cart = WC()->cart;
		if ( $cart && ! $cart->is_empty() ) {
			return number_format( (float) $cart->get_total( 'edit' ), 2, '.', '' );
		}

		if ( is_product() ) {
			$product = wc_get_product( get_the_ID() );
			if ( $product ) {
				$price = (float) wc_get_price_including_tax( $product );
				if ( $price ) {
					return number_format( $price, 2, '.', '' );
				}
			}
		}

		return '';
	}

	/**
	 * Returns the page context for the current WC page.
	 *
	 * @return string
	 */
	private function get_page_context(): string {
		if ( is_product() ) {
			return 'product';
		}
		if ( is_cart() ) {
			return 'cart';
		}
		if ( is_checkout() ) {
			return 'checkout';
		}

		return '';
	}
}
