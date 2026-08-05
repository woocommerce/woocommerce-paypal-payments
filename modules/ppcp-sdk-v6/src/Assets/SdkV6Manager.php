<?php
/**
 * Manages the SDK v6 frontend assets.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\GetOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

class SdkV6Manager {

	public const WRAPPER_ID           = 'ppc-button-ppcp-gateway-v6';
	public const MINI_CART_WRAPPER_ID = 'ppc-button-minicart-v6';

	private AssetGetter $asset_getter;
	private string $version;
	private Environment $environment;
	private ButtonStyleMapper $style_mapper;
	private bool $should_handle_shipping;
	private SettingsStatus $settings_status;
	private Context $context;
	private SessionHandler $session_handler;
	private CancelView $cancel_view;
	private bool $final_review_enabled;
	private bool $vaulting_enabled;

	public function __construct(
		AssetGetter $asset_getter,
		string $version,
		Environment $environment,
		ButtonStyleMapper $style_mapper,
		bool $should_handle_shipping,
		SettingsStatus $settings_status,
		Context $context,
		SessionHandler $session_handler,
		CancelView $cancel_view,
		bool $final_review_enabled,
		bool $vaulting_enabled = false
	) {
		$this->asset_getter           = $asset_getter;
		$this->version                = $version;
		$this->environment            = $environment;
		$this->style_mapper           = $style_mapper;
		$this->should_handle_shipping = $should_handle_shipping;
		$this->settings_status        = $settings_status;
		$this->context                = $context;
		$this->session_handler        = $session_handler;
		$this->cancel_view            = $cancel_view;
		$this->final_review_enabled   = $final_review_enabled;
		$this->vaulting_enabled       = $vaulting_enabled;
	}

	/**
	 * Enqueues scripts/styles.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		// The classic bootstrap renders into PHP-printed wrappers that do not
		// exist on block pages, which the block payment method script serves.
		if ( ! $this->should_load_on_current_page() || $this->is_block_context() ) {
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
	 * Determines which button locations should render on the current page.
	 *
	 * @return array<string, bool> Location => enabled (product, cart, checkout, mini-cart).
	 */
	public function determine_render_places(): array {
		// Activate is_cart()/is_checkout() on classic-shortcode block pages;
		// otherwise this only happens as a side effect of constructing the
		// (discarded) v5 SmartButton.
		$this->context->init_context();

		return array(
			'product'   => $this->settings_status->is_smart_button_enabled_for_location( 'product' ),
			'cart'      => $this->settings_status->is_smart_button_enabled_for_location( 'cart' ),
			'checkout'  => $this->settings_status->is_smart_button_enabled_for_location( 'checkout' ),
			'mini-cart' => $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' ),
		);
	}

	/**
	 * Outputs the main button wrapper element.
	 *
	 * @return void
	 */
	public function render_wrapper(): void {
		echo '<div class="ppc-button-wrapper"><div id="' . esc_attr( self::WRAPPER_ID ) . '"></div></div>';
	}

	/**
	 * Outputs the mini-cart button wrapper element.
	 *
	 * @return void
	 */
	public function render_mini_cart_wrapper(): void {
		echo '<p class="woocommerce-mini-cart__buttons buttons">';
		echo '<span id="' . esc_attr( self::MINI_CART_WRAPPER_ID ) . '"></span>';
		echo '</p>';
	}

	/**
	 * Whether the v6 SDK loads on the current page.
	 *
	 * Follows the v5 SmartButton gating: each WC page type requires its
	 * location to be enabled in the button settings, and an enabled
	 * mini-cart location enqueues on every page (the classic mini-cart
	 * widget can appear anywhere). The bootstrap only loads the SDK once
	 * a button wrapper exists in the DOM.
	 *
	 * Also scopes the v5 suppression: v5 is disabled on every page v6 owns
	 * (both SDKs claim window.paypal) and keeps running on the pages v6 does
	 * not own (pay-now, add-payment-method). Migration-phase only — see
	 * extensions.php.
	 *
	 * @return bool
	 */
	public function should_load_on_current_page(): bool {
		$page_location = $this->get_page_context();
		if ( $page_location && $this->settings_status->is_smart_button_enabled_for_location( $page_location ) ) {
			return true;
		}

		// Only when the classic widget is in use: loading (and suppressing v5)
		// sitewide without a widget would break the v5-rendered block express
		// buttons for nothing.
		return $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' )
			&& is_active_widget( false, false, 'woocommerce_widget_cart' );
	}

	/**
	 * The configuration data for the SDK v6 bootstrap script.
	 *
	 * Also consumed by the block payment method (V6PaymentMethod), which
	 * exposes it under `wcSettings.paymentMethodData['ppcp-sdk-v6']` for the
	 * React entry.
	 *
	 * @return array
	 */
	public function script_data(): array {
		$base_url = $this->environment->is_sandbox()
			? 'https://www.sandbox.paypal.com'
			: 'https://www.paypal.com';

		$buyer_country = WC()->customer ? WC()->customer->get_billing_country() : '';
		if ( ! $buyer_country ) {
			$buyer_country = wc_get_base_location()['country'] ?? '';
		}

		$page_context = $this->get_page_context();

		// Must stay in lockstep with ShippingPreferenceFactory, which marks only
		// 'checkout' and 'pay-now' as fixed-address (SET_PROVIDED_ADDRESS, no
		// callbacks needed). Every other context, 'checkout-block' included,
		// gets GET_FROM_FILE, where PayPal offers the buyer's own addresses and
		// the popup callbacks must stay attached to sync the choice back.
		$shipping_enabled = $this->should_handle_shipping && 'checkout' !== $page_context;

		$store_api_base = rtrim( rest_url( 'wc/store/v1/cart' ), '/' );

		$button_styles = array();
		if ( $page_context ) {
			$button_styles[ $page_context ] = $this->style_mapper->styles_for_context( $page_context );
		}
		if ( $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' ) ) {
			$button_styles['mini-cart'] = $this->style_mapper->styles_for_context( 'mini-cart' );
		}

		$data = array(
			'sdk_url'           => $base_url . '/web-sdk/v6/core',
			'page_context'      => $page_context,
			'currency'          => get_woocommerce_currency(),
			'amount'            => $this->transaction_amount(),
			'buyer_country'     => $buyer_country,
			'locale'            => str_replace( '_', '-', get_locale() ),
			'vaulting_enabled'  => $this->vaulting_enabled,
			// Drives the post-approval fork; see V6ExpressComponent.approve().
			'final_review'      => $this->final_review_enabled,
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
				'get_order'       => array(
					'endpoint' => \WC_AJAX::get_endpoint( GetOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( GetOrderEndpoint::nonce() ),
				),
				'update_shipping' => array(
					'endpoint' => \WC_AJAX::get_endpoint( UpdateShippingEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( UpdateShippingEndpoint::nonce() ),
				),
				'wc_store_api'    => array(
					'cart'                 => $store_api_base,
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
			'button_styles'     => $button_styles,
			'wrapper'           => '#' . self::WRAPPER_ID,
			'mini_cart_wrapper' => '#' . self::MINI_CART_WRAPPER_ID,
		);

		$continuation = $this->continuation_data();
		if ( $continuation ) {
			$data['continuation'] = $continuation;
		}

		return $data;
	}

	/**
	 * Whether the buyer is returning from an approved PayPal order and should
	 * see the order review instead of the express buttons.
	 */
	public function is_continuation(): bool {
		return $this->context->is_paypal_continuation();
	}

	/**
	 * The continuation payload, or null when there is no approved order.
	 *
	 * The cancel link is load-bearing: while an approved order sits in the
	 * session the express buttons are suppressed everywhere, so it is the
	 * buyer's only way out.
	 *
	 * @return array|null
	 */
	private function continuation_data(): ?array {
		if ( ! $this->is_continuation() ) {
			return null;
		}

		$order = $this->session_handler->order();
		if ( ! $order ) {
			return null;
		}

		$cancel_url = add_query_arg(
			array( CancelController::NONCE => wp_create_nonce( CancelController::NONCE ) ),
			wc_get_checkout_url()
		);

		return array(
			'order_id'       => $order->id(),
			'order'          => $order->to_array(),
			// v5 reads this from a window global; carried in the payload here so
			// the gateway is told which source approved the order.
			'funding_source' => $this->session_handler->funding_source(),
			'cancel'         => array(
				'html' => $this->cancel_view->render_session_cancellation(
					$cancel_url,
					$this->session_handler->funding_source()
				),
			),
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
	 * Resolves through the shared Context helper (which handles
	 * classic-shortcode block pages) and narrows to the contexts this
	 * module supports: classic product/cart/checkout and block
	 * cart/checkout. pay-now and the block editor stay out of scope.
	 *
	 * @return string
	 */
	private function get_page_context(): string {
		$context = $this->context->context();

		if ( in_array(
			$context,
			array( 'product', 'cart', 'checkout', 'cart-block', 'checkout-block' ),
			true
		) ) {
			return $context;
		}

		return '';
	}

	/**
	 * Whether the current page is a WooCommerce Blocks (React) page.
	 *
	 * @return bool
	 */
	public function is_block_context(): bool {
		return in_array(
			$this->get_page_context(),
			array( 'cart-block', 'checkout-block' ),
			true
		);
	}
}
