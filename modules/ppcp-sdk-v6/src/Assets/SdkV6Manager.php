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
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

/**
 * Class SdkV6Manager
 */
class SdkV6Manager {

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
		$this->asset_getter            = $asset_getter;
		$this->version                 = $version;
		$this->environment             = $environment;
		$this->style_mapper            = $style_mapper;
		$this->should_handle_shipping  = $should_handle_shipping;
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

		wp_register_script(
			'wc-ppcp-sdk-v6-boot',
			$this->asset_getter->get_asset_url( 'boot.js' ),
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
	 * Whether the scripts should be enqueued on the current page.
	 *
	 * Mini-cart can appear on any frontend page, so we enqueue
	 * on all non-admin pages when the widget is active, plus
	 * product, cart, and checkout pages.
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

		$page_context = $this->get_page_context();

		$buyer_country = WC()->customer ? WC()->customer->get_billing_country() : '';
		if ( ! $buyer_country ) {
			$buyer_country = wc_get_base_location()['country'] ?? '';
		}

		$shipping_enabled = $this->should_handle_shipping && ! is_checkout();

		return array(
			'sdk_url'       => $base_url . '/web-sdk/v6/core',
			'page_context'  => $page_context,
			'currency'      => get_woocommerce_currency(),
			'buyer_country' => $buyer_country,
			'locale'        => str_replace( '_', '-', get_locale() ),
			'ajax'          => array(
				'client_token'  => array(
					'endpoint' => \WC_AJAX::get_endpoint( ClientTokenEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ClientTokenEndpoint::nonce() ),
				),
				'create_order'  => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreateOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreateOrderEndpoint::nonce() ),
				),
				'approve_order' => array(
					'endpoint' => \WC_AJAX::get_endpoint( ApproveOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ApproveOrderEndpoint::nonce() ),
				),
				'update_shipping' => array(
					'endpoint' => \WC_AJAX::get_endpoint( UpdateShippingEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( UpdateShippingEndpoint::nonce() ),
				),
				'wc_store_api'    => array(
					'select_shipping_rate'  => home_url( UpdateShippingEndpoint::WC_STORE_API_ENDPOINT . 'select-shipping-rate' ),
					'cart'                  => home_url( UpdateShippingEndpoint::WC_STORE_API_ENDPOINT ),
					'update_customer'       => home_url( UpdateShippingEndpoint::WC_STORE_API_ENDPOINT . 'update-customer' ),
					'nonce'                 => wp_create_nonce( 'wc_store_api' ),
				),
			),
			'shipping'      => array(
				'handle_in_paypal' => $shipping_enabled,
				'need_shipping'    => $this->need_shipping(),
			),
			'button_styles' => array(
				'product'   => $this->style_mapper->styles_for_context( 'product' ),
				'cart'      => $this->style_mapper->styles_for_context( 'cart' ),
				'checkout'  => $this->style_mapper->styles_for_context( 'checkout' ),
				'mini-cart' => $this->style_mapper->styles_for_context( 'mini-cart' ),
			),
			'wrapper'       => '#ppc-button-ppcp-gateway-v6',
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
