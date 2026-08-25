<?php
/**
 * Manages the SDK v6 frontend assets.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WC_Product;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\GetOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\SimulateCartEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\WalletShippingEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ApplePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\SdkV6\Helper\GooglePayConfig;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

class SdkV6Manager {

	public const WRAPPER_ID            = 'ppc-button-ppcp-gateway-v6';
	public const MINI_CART_WRAPPER_ID  = 'ppc-button-minicart-v6';
	public const GOOGLE_PAY_WRAPPER_ID = 'ppc-button-ppcp-googlepay-v6';
	public const APPLE_PAY_WRAPPER_ID  = 'ppc-button-ppcp-applepay-v6';

	public const PAYMENT_BUTTON_HEIGHT = '48px';

	/**
	 * The contexts that print a payment-method radio list a wallet can own a row in.
	 */
	private const CONTEXTS_WITH_GATEWAY_ROWS = array( 'checkout', 'pay-now' );

	// Existing WC credit-card-form field IDs (see CardFieldsModule's
	// woocommerce_credit_card_form_fields filter and WC core's own
	// card-number/expiry/cvc fields) that the v6 card fields mount into,
	// replacing v5's paypal.CardFields()-rendered inputs in the same slots.
	private const CARD_FIELD_NAME_ID   = 'ppcp-credit-card-gateway-card-name';
	private const CARD_FIELD_NUMBER_ID = 'ppcp-credit-card-gateway-card-number';
	private const CARD_FIELD_EXPIRY_ID = 'ppcp-credit-card-gateway-card-expiry';
	private const CARD_FIELD_CVV_ID    = 'ppcp-credit-card-gateway-card-cvc';

	private AssetGetter $asset_getter;
	private string $version;
	private Environment $environment;
	private ButtonStyleMapper $style_mapper;
	private SettingsStatus $settings_status;
	private Context $context;
	private SessionHandler $session_handler;
	private CancelView $cancel_view;
	private bool $final_review_enabled;
	private bool $vaulting_enabled;
	private CardPaymentsConfiguration $card_payments_configuration;
	private bool $card_vaulting_enabled;
	private SubscriptionHelper $subscription_helper;
	private string $merchant_country;

	/**
	 * Card brand icons ({type, title, url}); empty when "Show logos" is off.
	 *
	 * @var array<int, array{type:string, title:string, url:string}>
	 */
	private array $credit_card_icons;

	/**
	 * Kept alongside $wallets, which holds the same object as a WalletConfig:
	 * display_name() exists only on the Apple subclass, so reaching it through
	 * the base type would not type-check.
	 */
	private ApplePayConfig $apple_pay_config;

	/**
	 * Every wallet this module places, in the order their rows are printed.
	 *
	 * @var WalletPlacement[]
	 */
	private array $wallets;

	public function __construct(
		AssetGetter $asset_getter,
		string $version,
		Environment $environment,
		ButtonStyleMapper $style_mapper,
		SettingsStatus $settings_status,
		Context $context,
		SessionHandler $session_handler,
		CancelView $cancel_view,
		bool $final_review_enabled,
		bool $vaulting_enabled,
		CardPaymentsConfiguration $card_payments_configuration,
		bool $card_vaulting_enabled,
		SubscriptionHelper $subscription_helper,
		array $credit_card_icons,
		string $merchant_country,
		GooglePayConfig $google_pay_config,
		ApplePayConfig $apple_pay_config
	) {
		$this->asset_getter                = $asset_getter;
		$this->version                     = $version;
		$this->environment                 = $environment;
		$this->style_mapper                = $style_mapper;
		$this->settings_status             = $settings_status;
		$this->context                     = $context;
		$this->session_handler             = $session_handler;
		$this->cancel_view                 = $cancel_view;
		$this->final_review_enabled        = $final_review_enabled;
		$this->vaulting_enabled            = $vaulting_enabled;
		$this->card_payments_configuration = $card_payments_configuration;
		$this->card_vaulting_enabled       = $card_vaulting_enabled;
		$this->subscription_helper         = $subscription_helper;
		$this->credit_card_icons           = $credit_card_icons;
		$this->merchant_country            = $merchant_country;
		$this->apple_pay_config            = $apple_pay_config;

		$this->wallets = array(
			new WalletPlacement(
				'google_pay',
				GooglePayGateway::ID,
				self::GOOGLE_PAY_WRAPPER_ID,
				'https://pay.google.com/gp/p/js/pay.js',
				$google_pay_config,
				static function ( string $context ) use ( $google_pay_config ): array {
					return $google_pay_config->styles( $context );
				}
			),
			new WalletPlacement(
				'apple_pay',
				ApplePayGateway::ID,
				self::APPLE_PAY_WRAPPER_ID,
				// Loaded by the frontend rather than by the applepay-payments
				// component, which only loads it for a session type this module does
				// not use. It registers the <apple-pay-button> element.
				'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
				$apple_pay_config,
				static function ( string $context ) use ( $apple_pay_config ): array {
					return $apple_pay_config->styles( $context );
				}
			),
		);
	}

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
	 * @return array<string, bool> Location => enabled (product, cart, checkout, pay-now, mini-cart).
	 */
	public function determine_render_places(): array {
		// Activate is_cart()/is_checkout() on classic-shortcode block pages;
		// otherwise this only happens as a side effect of constructing the
		// (discarded) v5 SmartButton.
		$this->context->init_context();

		// Free orders ($0 total, e.g. a 100%-off coupon or free trial) do not
		// need payment, so the cart/checkout wallet buttons must not render —
		// matching v5's is_cart_price_total_zero() suppression.
		$needs_payment = $this->cart_needs_payment();

		// pay-now is driven by the existing WC order, not the cart, so the
		// zero-total cart guard does not apply. Its location normalizes to
		// 'checkout' in SettingsStatus.
		return array(
			'product'   => $this->settings_status->is_smart_button_enabled_for_location( 'product' ),
			'cart'      => $needs_payment && $this->settings_status->is_smart_button_enabled_for_location( 'cart' ),
			'checkout'  => $needs_payment && $this->settings_status->is_smart_button_enabled_for_location( 'checkout' ),
			'pay-now'   => $this->settings_status->is_smart_button_enabled_for_location( 'pay-now' ),
			'mini-cart' => $needs_payment && $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' ),
		);
	}

	/**
	 * Whether the current cart still needs payment.
	 *
	 * Guards the wallet buttons against $0 / free orders (e.g. a full-value
	 * coupon or a free trial), where no payment method should be offered.
	 *
	 * @return bool
	 */
	private function cart_needs_payment(): bool {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return true;
		}

		return $cart->needs_payment();
	}

	public function render_wrapper(): void {
		echo '<div class="ppc-button-wrapper"><div id="' . esc_attr( self::WRAPPER_ID ) . '"></div></div>';
	}

	/**
	 * Renders each wallet's gateway container, hidden until eligible.
	 *
	 * On classic checkout a wallet is its own payment-method row rather than an
	 * express button, so its button needs a container next to the place-order
	 * area instead of the shared express wrapper.
	 *
	 * Every row starts hidden and the JS reveals it once the browser confirms the
	 * buyer can pay, because eligibility is only knowable client-side: a browser
	 * without a saved card, or without Apple Pay set up, would otherwise be
	 * offered a row that cannot complete. Same attribute shape as v5's
	 * GooglePayButton, which the v6 JS removes the same way its PaymentButton did.
	 */
	public function render_wallet_gateway_wrappers(): void {
		foreach ( $this->wallets as $wallet ) {
			if ( ! $this->is_wallet_gateway( $wallet ) ) {
				continue;
			}
			?>
			<style data-hide-gateway='<?php echo esc_attr( $wallet->gateway_id ); ?>'>
				.wc_payment_method.payment_method_<?php echo esc_attr( $wallet->gateway_id ); ?> {
					display: none;
				}
			</style>
			<div id="<?php echo esc_attr( $wallet->wrapper_id ); ?>"></div>
			<?php
		}
	}

	public function render_mini_cart_wrapper(): void {
		echo '<p class="woocommerce-mini-cart__buttons buttons">';
		echo '<span id="' . esc_attr( self::MINI_CART_WRAPPER_ID ) . '"></span>';
		echo '</p>';
	}

	/**
	 * Whether a wallet renders as its own payment-method row.
	 *
	 * True only where there is a list to join and the gateway is available there.
	 *
	 * Only the gateway walk is memoized, never a refusal from the context check,
	 * so a call made before the context resolves cannot poison the answer. The
	 * memo spans one request, where this is asked twice per wallet: once to print
	 * the row, once to build the script data.
	 */
	private function is_wallet_gateway( WalletPlacement $wallet ): bool {
		if ( null !== $wallet->is_gateway ) {
			return $wallet->is_gateway;
		}

		if ( ! in_array( $this->get_page_context(), self::CONTEXTS_WITH_GATEWAY_ROWS, true ) || $this->is_block_context() ) {
			return false;
		}

		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();

		$wallet->is_gateway = isset( $gateways[ $wallet->gateway_id ] );

		return $wallet->is_gateway;
	}

	/**
	 * The script data every wallet has, before its own keys are added.
	 *
	 * @param WalletPlacement $wallet       The wallet to describe.
	 * @param string          $page_context The current context, empty off a button page.
	 * @return array<string, mixed>
	 */
	private function wallet_script_data( WalletPlacement $wallet, string $page_context ): array {
		// Styled per context rather than globally: `enabled` then follows from
		// whether any context on this page wants the wallet at all.
		$styles = array();
		if ( $page_context && $wallet->config->should_render( $page_context ) ) {
			$styles[ $page_context ] = $wallet->styles( $page_context );
		}
		if ( $wallet->config->should_render( 'mini-cart' ) ) {
			$styles['mini-cart'] = $wallet->styles( 'mini-cart' );
		}

		return array(
			'enabled' => ! empty( $styles ),
			'sdk_url' => $wallet->sdk_url,
			'styles'  => $styles,
			// Present only on classic checkout, where a payment-method list
			// exists: everywhere else the wallet stays an express button, as in
			// v5. Null rather than a flag plus two values the JS would have to
			// pair up again.
			'gateway' => $this->is_wallet_gateway( $wallet )
				? array(
					'id'      => $wallet->gateway_id,
					'wrapper' => '#' . $wallet->wrapper_id,
				)
				: null,
		);
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
	 * Card fields (ACDC) are independent of the smart-button location: the
	 * card gateway is a regular WC payment method that can be selectable
	 * at checkout even when the wallet button is disabled there, so it
	 * gets its own OR'd condition rather than being folded into the
	 * location check above.
	 *
	 * @return bool
	 */
	public function should_load_on_current_page(): bool {
		$page_location = $this->get_page_context();
		if ( $page_location && $this->settings_status->is_smart_button_enabled_for_location( $page_location ) ) {
			return true;
		}

		if ( $this->is_card_fields_enabled( $page_location ) ) {
			return true;
		}

		if ( $page_location && $this->any_wallet_renders( $page_location ) ) {
			return true;
		}

		// Load sitewide whenever the mini-cart location is enabled, matching the
		// v5 SmartButton (should_load_buttons()'s default branch). The mini-cart
		// can appear on any page — as the classic "Cart" widget OR the block
		// Mini-Cart in a block theme's header — and is_active_widget() only
		// detects the classic widget, so gating on it dropped the SDK (and every
		// mini-cart button, Venmo included) on shop/home pages that use the block
		// Mini-Cart. boot.js always renders into the mini-cart wrapper when it is
		// present, so loading sitewide keeps parity with v5.
		return $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' )
			|| $this->any_wallet_renders( 'mini-cart' );
	}

	/**
	 * Whether the v6 Advanced Card Fields should render on the given page.
	 *
	 * Gates both the JS `card_fields.enabled` flag and the suppression of the
	 * v5 card block, so a page never ends up with neither card option.
	 *
	 * @param string|null $location Page context to test; defaults to the current page.
	 */
	public function is_card_fields_enabled( ?string $location = null ): bool {
		$location = $location ?? $this->get_page_context();

		return in_array( $location, array( 'checkout', 'checkout-block', 'pay-now' ), true )
			&& $this->card_payments_configuration->is_acdc_enabled();
	}

	/**
	 * Whether any wallet renders in the given context.
	 */
	private function any_wallet_renders( string $context ): bool {
		foreach ( $this->wallets as $wallet ) {
			if ( $wallet->config->should_render( $context ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The configuration data for the SDK v6 bootstrap script.
	 *
	 * Also consumed by the block payment method (V6PaymentMethod), which
	 * exposes it under `wcSettings.paymentMethodData['ppcp-sdk-v6']` for the
	 * React entry.
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

		$shipping_contexts = $this->shipping_contexts( $page_context );

		$store_api_base = rtrim( rest_url( 'wc/store/v1/cart' ), '/' );

		$button_styles = array();
		if ( $page_context ) {
			$button_styles[ $page_context ] = $this->style_mapper->styles_for_context( $page_context );
		}
		if ( $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' ) ) {
			$button_styles['mini-cart'] = $this->style_mapper->styles_for_context( 'mini-cart' );
		}

		$card_fields_enabled = $this->is_card_fields_enabled();

		$data = array(
			'sdk_url'           => $base_url . '/web-sdk/v6/core',
			'page_context'      => $page_context,
			'currency'          => get_woocommerce_currency(),
			'amount'            => $this->transaction_amount(),
			'buyer_country'     => $buyer_country,
			'merchant_country'  => $this->merchant_country,
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
				'simulate_cart'   => array(
					'endpoint' => \WC_AJAX::get_endpoint( SimulateCartEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( SimulateCartEndpoint::nonce() ),
				),
				'wallet_shipping' => array(
					'endpoint' => \WC_AJAX::get_endpoint( WalletShippingEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( WalletShippingEndpoint::nonce() ),
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
				'generic_error'          => __(
					'Something went wrong. Please try again or choose another payment source.',
					'woocommerce-paypal-payments'
				),
				'shipping_unserviceable' => __(
					'Cannot ship to the selected address.',
					'woocommerce-paypal-payments'
				),
				// The Apple Pay sheet itemises the total with these.
				'subtotal'               => __( 'Subtotal', 'woocommerce-paypal-payments' ),
				'shipping'               => __( 'Shipping', 'woocommerce-paypal-payments' ),
				'tax'                    => __( 'Tax', 'woocommerce-paypal-payments' ),
				'discount'               => __( 'Discount', 'woocommerce-paypal-payments' ),
			),
			'shipping'          => array(
				'in_context' => $shipping_contexts,
				'countries'  => $this->shipping_countries( $shipping_contexts ),
			),
			'button_styles'     => $button_styles,
			'button_height'     => self::PAYMENT_BUTTON_HEIGHT,
			'wrapper'           => '#' . self::WRAPPER_ID,
			'mini_cart_wrapper' => '#' . self::MINI_CART_WRAPPER_ID,
			'card_fields'       => array(
				'enabled'             => $card_fields_enabled,
				'payment_method'      => CreditCardGateway::ID,
				'funding_source'      => 'card',
				// Label, name-field flag and card-brand logos for the block's own
				// card method. card_icons is empty when "Show logos" is disabled
				// (the credit-card-icons service already guards on the setting).
				'title'               => $this->card_payments_configuration->gateway_title(),
				'name_field'          => 'yes' === $this->card_payments_configuration->show_name_on_card(),
				// Card "save during purchase" (vaulting). The block checkout uses
				// WC Blocks' native save option (supports.showSaveOption, gated on
				// is_vaulting_enabled); the classic checkout reads WC's own
				// tokenization checkbox instead. has_subscriptions force-saves,
				// since a subscription card must be vaulted for renewals.
				'is_vaulting_enabled' => $this->card_vaulting_enabled,
				'has_subscriptions'   => $this->subscription_helper->cart_contains_subscription(),
				'card_icons'          => array_map(
					static function ( array $icon ): array {
						return array(
							'id'  => $icon['type'],
							'alt' => $icon['title'],
							'src' => $icon['url'],
						);
					},
					$this->credit_card_icons
				),
				'fields'              => array(
					'name'   => '#' . self::CARD_FIELD_NAME_ID,
					'number' => '#' . self::CARD_FIELD_NUMBER_ID,
					'expiry' => '#' . self::CARD_FIELD_EXPIRY_ID,
					'cvv'    => '#' . self::CARD_FIELD_CVV_ID,
				),
			),
		);

		foreach ( $this->wallets as $wallet ) {
			$data[ $wallet->config_key ] = $this->wallet_script_data( $wallet, $page_context );
		}

		// The keys only one wallet has. Everything above this line is the shape
		// every wallet shares.
		$data['google_pay']['environment'] = $this->environment->is_sandbox() ? 'TEST' : 'PRODUCTION';
		// Labels the sheet total and identifies the merchant during validation.
		$data['apple_pay']['display_name'] = $this->apple_pay_config->display_name();
		// Where the frontend reports merchant validation, which keeps the admin
		// "domain not validated" notice accurate. The Apple Pay module owns this
		// admin-ajax action and dictates its nonce action.
		$data['apple_pay']['validation'] = array(
			'endpoint' => admin_url( 'admin-ajax.php' ),
			'action'   => PropertiesDictionary::VALIDATE,
			'nonce'    => wp_create_nonce( PropertiesDictionary::NONCE_ACTION ),
		);

		// The pay-for-order page creates the PayPal order from an existing WC
		// order (not the cart), so the front end must forward its identifiers to
		// the create-order endpoint's from_wc_order branch.
		if ( 'pay-now' === $page_context ) {
			$data['pay_now'] = array(
				'order_id'  => $this->order_pay_id(),
				'order_key' => $this->order_pay_key(),
			);
		}

		$continuation = $this->continuation_data();
		if ( $continuation ) {
			$data['continuation'] = $continuation;
		}

		return $data;
	}

	/**
	 * The WC order ID on the pay-for-order (order-pay) page, or 0.
	 *
	 * @return int
	 */
	private function order_pay_id(): int {
		global $wp;

		if ( ! isset( $wp->query_vars['order-pay'] ) ) {
			return 0;
		}

		return absint( $wp->query_vars['order-pay'] );
	}

	/**
	 * The order key from the pay-for-order page URL, or empty string.
	 *
	 * @return string
	 */
	private function order_pay_key(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		return is_string( $key ) ? $key : '';
	}

	/**
	 * The WC order for the current pay-for-order page, validated against the
	 * URL order key, or null.
	 *
	 * @return \WC_Order|null
	 */
	private function order_pay(): ?\WC_Order {
		$order_id = $this->order_pay_id();
		if ( ! $order_id ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		// Guard against reading another customer's order total from a crafted URL.
		if ( ! hash_equals( (string) $order->get_order_key(), $this->order_pay_key() ) ) {
			return null;
		}

		return $order;
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
	 * Whether shipping details are collected, per context.
	 *
	 * One decision per context, shared by every surface that asks it: the PayPal
	 * popup and the wallet payment sheets. A map rather than a single flag because
	 * the mini-cart renders on any page, so two contexts can be live at once and
	 * answer differently.
	 *
	 * @param string $page_context The context of the current page.
	 * @return array<string, bool> Keyed by context.
	 */
	private function shipping_contexts( string $page_context ): array {
		$contexts = array();

		if ( $page_context ) {
			$contexts[ $page_context ] = $this->shipping_for_context( $page_context );
		}

		$contexts['mini-cart'] = $this->shipping_for_context( 'mini-cart' );

		return $contexts;
	}

	/**
	 * Whether the given context collects a shipping address and shipping options.
	 *
	 * Requires the "Pay Now" experience, which builds the WC order from the approved
	 * PayPal order and the address collected during payment. Continuation mode ends
	 * on a final review page instead, and that page collects shipping itself.
	 *
	 * The product page judges the product rather than the cart, because the product
	 * is what gets bought there: it is added to the cart on click, so the cart's
	 * current contents describe a basket that is about to be replaced.
	 *
	 * @param string $context The context to judge.
	 * @return bool
	 */
	private function shipping_for_context( string $context ): bool {
		if ( $this->final_review_enabled ) {
			return false;
		}

		// Both pages already own the address and the total the order will use, so
		// the wallet only authorizes what the page shows. This prevents conflicting
		// addresses/details between checkout form and payment sheet.
		if ( in_array( $context, array( 'checkout', 'pay-now' ), true ) ) {
			return false;
		}

		// Block surfaces read needsShipping live from the React cart and combine it
		// with this value themselves, so answering with the cart as it stood when the
		// page was built would gate them twice, on a snapshot that goes stale the
		// moment the buyer edits the cart.
		if ( in_array( $context, array( 'cart-block', 'checkout-block' ), true ) ) {
			return true;
		}

		if ( 'product' === $context ) {
			$product = wc_get_product();

			return $product instanceof WC_Product
				&& ! $product->is_virtual()
				&& ! $product->is_downloadable();
		}

		$cart = WC()->cart;

		return $cart && $cart->needs_shipping();
	}

	/**
	 * The countries a payment sheet may offer, for Google Pay's address allow-list.
	 *
	 * Sent whole whenever any context collects shipping, as the classic integration
	 * did, so the buyer can never select an address the store would reject.
	 *
	 * @param array<string, bool> $shipping_contexts The per-context map.
	 * @return array<int, string> ISO-2 country codes.
	 */
	private function shipping_countries( array $shipping_contexts ): array {
		if ( ! in_array( true, $shipping_contexts, true ) ) {
			return array();
		}

		$countries = WC()->countries;

		return $countries ? array_keys( $countries->get_shipping_countries() ) : array();
	}

	/**
	 * Returns the expected transaction amount for eligibility checks
	 * (affects Pay Later thresholds): the cart total, or the product
	 * price on product pages while the cart is empty.
	 *
	 * @return string The amount as a decimal string, or empty when unknown.
	 */
	private function transaction_amount(): string {
		// The pay-for-order page has no cart; the amount is the existing WC
		// order's total, used for Pay Later eligibility.
		if ( 'pay-now' === $this->get_page_context() ) {
			$order = $this->order_pay();
			if ( $order ) {
				return number_format( (float) $order->get_total(), 2, '.', '' );
			}

			return '';
		}

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
	 * module supports: classic product/cart/checkout/pay-now and block
	 * cart/checkout. The block editor stays out of scope.
	 *
	 * @return string
	 */
	private function get_page_context(): string {
		$context = $this->context->context();

		if ( in_array(
			$context,
			array( 'product', 'cart', 'checkout', 'pay-now', 'cart-block', 'checkout-block' ),
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
