<?php
/**
 * Manages the SDK v6 frontend assets.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WC_Payment_Gateway;
use WC_Product;
use WP_Post;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\FrontendLogEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\GetOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\PayLaterBlock\PayLaterBlockModule;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentTokenForGuest;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreateSetupToken;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\SimulateCartEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\CartQuoteEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ApplePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\SdkV6\Helper\CardFieldStyles;
use WooCommerce\PayPalCommerce\SdkV6\Helper\FastlaneConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\GooglePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\MessagesEligibility;
use WooCommerce\PayPalCommerce\SdkV6\Helper\MessageStyleMapper;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

class SdkV6Manager {

	public const WRAPPER_ID             = 'ppc-button-ppcp-gateway-v6';
	public const MINI_CART_WRAPPER_ID   = 'ppc-button-minicart-v6';
	public const GOOGLE_PAY_WRAPPER_ID  = 'ppc-button-ppcp-googlepay-v6';
	public const APPLE_PAY_WRAPPER_ID   = 'ppc-button-ppcp-applepay-v6';
	public const CARD_BUTTON_WRAPPER_ID = 'ppc-button-ppcp-card-button-gateway-v6';

	/**
	 * The height every payment button on the page renders at.
	 *
	 * One value for all of them: the buttons are meant to match, and the styling
	 * DTOs carry no height of their own (see ButtonStyleMapper).
	 */
	public const PAYMENT_BUTTON_HEIGHT = '48px';

	// The pay-for-order page has no pre-payment hook, so the message renders
	// after the submit button and is relocated by SdkV6Module.
	public const PAY_ORDER_MESSAGE_HOOK = 'woocommerce_pay_order_before_submit';

	/**
	 * The contexts that print a payment-method radio list a method can own a row in.
	 */
	private const CONTEXTS_WITH_GATEWAY_ROWS = array( 'checkout', 'pay-now' );

	// Existing WC credit-card-form field IDs the v6 card fields mount into; see
	// CardFieldsModule's woocommerce_credit_card_form_fields filter.
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
	private FreeTrialSubscriptionHelper $free_trial_helper;

	/**
	 * Resolves the current subscriptions mode ('subscriptions_api',
	 * 'vaulting_api', …). Native PayPal Subscriptions run in 'subscriptions_api'.
	 *
	 * @var callable():string
	 */
	private $get_subscriptions_mode;

	private string $three_d_secure_contingency;
	private MessageStyleMapper $message_style_mapper;
	private MessagesEligibility $messages_eligibility;
	private string $merchant_country;

	/**
	 * Card brand icons ({type, title, url}); empty when "Show logos" is off.
	 *
	 * @var array<int, array{type:string, title:string, url:string}>
	 */
	private array $credit_card_icons;

	/**
	 * The same object $placements holds, kept under its own type because
	 * display_name() exists only on the Apple subclass.
	 */
	private ApplePayConfig $apple_pay_config;

	private FastlaneConfig $fastlane_config;
	private CardFieldStyles $card_field_styles;

	/**
	 * Every method this module places, in the order their rows are printed.
	 *
	 * @var MethodPlacement[]
	 */
	private array $placements;

	/**
	 * Memoizes is_card_button_row(), asked once per request to print the row and
	 * once to build the script data.
	 *
	 * @var bool|null
	 */
	private ?bool $is_card_button_row = null;

	/**
	 * Memoizes available_gateways(), which every placement asks twice.
	 *
	 * @var array<string, WC_Payment_Gateway>|null
	 */
	private ?array $available_gateways = null;

	/**
	 * Memoizes should_load_on_current_page(), asked by every surface that
	 * stands down for v6.
	 *
	 * @var bool|null
	 */
	private ?bool $should_load = null;

	/**
	 * Memoizes has_paylater_block(), which the messaging gates ask repeatedly.
	 *
	 * @var bool|null
	 */
	private ?bool $has_paylater_block = null;

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
		FreeTrialSubscriptionHelper $free_trial_helper,
		callable $get_subscriptions_mode,
		string $three_d_secure_contingency,
		array $credit_card_icons,
		MessageStyleMapper $message_style_mapper,
		MessagesEligibility $messages_eligibility,
		string $merchant_country,
		GooglePayConfig $google_pay_config,
		ApplePayConfig $apple_pay_config,
		FastlaneConfig $fastlane_config,
		CardFieldStyles $card_field_styles
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
		$this->free_trial_helper           = $free_trial_helper;
		$this->get_subscriptions_mode      = $get_subscriptions_mode;
		$this->three_d_secure_contingency  = $three_d_secure_contingency;
		$this->credit_card_icons           = $credit_card_icons;
		$this->message_style_mapper        = $message_style_mapper;
		$this->messages_eligibility        = $messages_eligibility;
		$this->merchant_country            = $merchant_country;
		$this->apple_pay_config            = $apple_pay_config;
		$this->fastlane_config             = $fastlane_config;
		$this->card_field_styles           = $card_field_styles;

		$this->placements = array(
			new MethodPlacement(
				'google_pay',
				GooglePayGateway::ID,
				self::GOOGLE_PAY_WRAPPER_ID,
				'https://pay.google.com/gp/p/js/pay.js',
				$google_pay_config,
				static function ( string $context ) use ( $google_pay_config ): array {
					return $google_pay_config->styles( $context );
				}
			),
			new MethodPlacement(
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

	/**
	 * Registers, localizes and enqueues the classic bootstrap script.
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

		$asset = $this->asset_getter->get_asset_data( 'boot.js', $this->version );

		wp_register_script(
			'wc-ppcp-sdk-v6-boot',
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'wc-ppcp-sdk-v6-boot',
			'wc_ppcp_sdk_v6',
			$this->script_data()
		);

		wp_enqueue_script( 'wc-ppcp-sdk-v6-boot' );

		// Lays out the express buttons inside the wrappers render_wrapper()
		// prints. Classic pages only; block pages return at the top of this
		// method and style their containers from the block bundle.
		wp_enqueue_style(
			'wc-ppcp-sdk-v6-gateway',
			$this->asset_getter->get_asset_url( 'gateway.css' ),
			array(),
			$this->asset_getter->get_asset_data( 'gateway.css', $this->version )['version']
		);
	}

	/**
	 * Determines which button locations should render on the current page.
	 *
	 * Expects Context::init_context() to have run, so that is_cart() and
	 * is_checkout() resolve on classic-shortcode block pages.
	 *
	 * @return array<string, bool> Location => enabled (product, cart, checkout, pay-now, mini-cart).
	 */
	public function determine_render_places(): array {
		// Native PayPal Subscriptions defer to v5 (see should_load_on_current_page);
		// print no v6 wrappers so the classic page hands off cleanly. These render
		// hooks key on the smart-button locations rather than that method, so they
		// need this guard explicitly. Every location is returned false (rather than
		// an empty array) to keep the array shape callers index into.
		if ( $this->is_native_paypal_subscription_page() ) {
			return array(
				'product'   => false,
				'cart'      => false,
				'checkout'  => false,
				'pay-now'   => false,
				'mini-cart' => false,
			);
		}

		$needs_payment = $this->cart_needs_payment();

		// pay-now is driven by the existing WC order rather than the cart, so
		// the zero-total guard does not apply to it.
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
	 * Keeps buttons off $0 orders (a full-value coupon, a free trial), where no
	 * payment method should be offered at all.
	 */
	private function cart_needs_payment(): bool {
		$cart = WC()->cart;
		if ( ! $cart ) {
			return true;
		}

		return $cart->needs_payment();
	}

	/**
	 * Renders the shared express-button wrapper.
	 */
	public function render_wrapper(): void {
		echo '<div class="ppc-button-wrapper"><div id="' . esc_attr( self::WRAPPER_ID ) . '"></div></div>';
	}

	/**
	 * Renders a payment method's own button container, hidden until eligible.
	 *
	 * On classic checkout these methods are payment-method rows rather than
	 * express buttons, so each needs a container by the place-order area instead
	 * of the shared express wrapper.
	 *
	 * The row starts hidden and gatewayPlacement.js reveals it once the browser
	 * confirms the buyer can pay: eligibility is only knowable client-side, and a
	 * row whose button never rendered would be a dead end.
	 */
	private function render_gateway_wrapper( string $gateway_id, string $wrapper_id ): void {
		?>
		<style data-hide-gateway='<?php echo esc_attr( $gateway_id ); ?>'>
			.wc_payment_method.payment_method_<?php echo esc_attr( $gateway_id ); ?> {
				display: none;
			}
		</style>
		<div id="<?php echo esc_attr( $wrapper_id ); ?>"></div>
		<?php
	}

	/**
	 * Renders a gateway row for every method that has one on this page.
	 */
	public function render_gateway_wrappers(): void {
		foreach ( $this->placements as $placement ) {
			if ( $this->is_method_gateway( $placement ) ) {
				$this->render_gateway_wrapper( $placement->gateway_id, $placement->wrapper_id );
			}
		}
	}

	/**
	 * Renders the BCDC gateway row, when it belongs on this page.
	 */
	public function render_card_button_wrapper(): void {
		if ( $this->is_card_button_row() ) {
			$this->render_gateway_wrapper( CardButtonGateway::ID, self::CARD_BUTTON_WRAPPER_ID );
		}
	}

	/**
	 * Renders the mini-cart button wrapper.
	 */
	public function render_mini_cart_wrapper(): void {
		echo '<p class="woocommerce-mini-cart__buttons buttons">';
		echo '<span id="' . esc_attr( self::MINI_CART_WRAPPER_ID ) . '"></span>';
		echo '</p>';
	}

	/**
	 * Whether a method renders as its own payment-method row.
	 *
	 * True only where there is a list to join and the gateway is available there.
	 *
	 * Only the gateway walk is memoized, never a refusal from the context check,
	 * so a call made before the context resolves cannot poison the answer.
	 */
	private function is_method_gateway( MethodPlacement $placement ): bool {
		if ( null !== $placement->is_gateway ) {
			return $placement->is_gateway;
		}

		if ( ! in_array( $this->get_page_context(), self::CONTEXTS_WITH_GATEWAY_ROWS, true ) || $this->is_block_context() ) {
			return false;
		}

		$placement->is_gateway = isset( $this->available_gateways()[ $placement->gateway_id ] );

		return $placement->is_gateway;
	}

	/**
	 * The gateways WooCommerce offers for the current cart, keyed by id.
	 *
	 * @return array<string, WC_Payment_Gateway>
	 */
	private function available_gateways(): array {
		if ( null !== $this->available_gateways ) {
			return $this->available_gateways;
		}

		if ( ! function_exists( 'WC' ) ) {
			return array();
		}

		$gateways = WC()->payment_gateways();

		// Not memoized, so an early caller cannot pin an empty list.
		if ( ! $gateways ) {
			return array();
		}

		$this->available_gateways = $gateways->get_available_payment_gateways();

		return $this->available_gateways;
	}

	/**
	 * The script data every placement has, before its own keys are added.
	 *
	 * @param MethodPlacement $placement       The placement to describe.
	 * @param string          $page_context The current context, empty off a button page.
	 * @return array<string, mixed>
	 */
	private function placement_script_data( MethodPlacement $placement, string $page_context ): array {
		// Styled per context, so `enabled` follows from whether any context on
		// this page wants the method at all.
		$styles = array();
		if ( $page_context && $placement->config->should_render( $page_context ) ) {
			$styles[ $page_context ] = $placement->styles( $page_context );
		}
		if ( $placement->config->should_render( 'mini-cart' ) ) {
			$styles['mini-cart'] = $placement->styles( 'mini-cart' );
		}

		// supported_features: this method's own gateway, never PayPal's. A
		// borrowed vaulting list would offer the method on a subscription cart
		// it cannot pay for.
		return array(
			'enabled'            => ! empty( $styles ),
			'sdk_url'            => $placement->sdk_url,
			'styles'             => $styles,
			'supported_features' => $this->gateway_supports( $placement->gateway_id ),
			'gateway'            => $this->gateway_row( $placement ),
		);
	}

	/**
	 * The payment-method row this method occupies, or null for an express button
	 * rendered outside any payment-method list.
	 *
	 * @return array{id: string, wrapper: string}|null
	 */
	private function gateway_row( MethodPlacement $placement ): ?array {
		if ( ! $this->is_method_gateway( $placement ) ) {
			return null;
		}

		return array(
			'id'      => $placement->gateway_id,
			'wrapper' => '#' . $placement->wrapper_id,
		);
	}

	/**
	 * The gateway's own supports list, or `array( 'products' )` when the gateway
	 * is unavailable. The narrowest list hides the method rather than offering
	 * it on a cart it cannot pay for.
	 *
	 * @return string[]
	 */
	private function gateway_supports( string $gateway_id ): array {
		$gateway = $this->available_gateways()[ $gateway_id ] ?? null;

		if ( ! $gateway instanceof WC_Payment_Gateway ) {
			return array( 'products' );
		}

		return array_values( (array) $gateway->supports );
	}

	/**
	 * Whether the v6 SDK loads on the current page.
	 *
	 * Also scopes the v5 suppression, since both SDKs claim window.paypal: v5 is
	 * disabled on exactly the pages this returns true for. See extensions.php.
	 *
	 * Each card surface gets its own OR'd condition rather than folding into the
	 * location check, because a card gateway is a regular WC payment method that
	 * can be selectable at checkout with the smart button disabled there. Pay
	 * Later messaging is another such condition: it can claim a classic page
	 * where nothing else here would.
	 */
	public function should_load_on_current_page(): bool {
		if ( null !== $this->should_load ) {
			return $this->should_load;
		}

		$should_load = $this->resolve_should_load();

		// Memoized only after Context::init_context() ran on `wp`; before that
		// is_cart()/is_checkout() have not resolved.
		if ( did_action( 'wp' ) ) {
			$this->should_load = $should_load;
		}

		return $should_load;
	}

	/**
	 * The uncached answer for should_load_on_current_page().
	 */
	private function resolve_should_load(): bool {
		// Native PayPal Subscriptions (subscriptions_api mode) have no v6 path: v6
		// can only carry a subscription by vaulting, which that mode disables. Hand
		// the whole page back to the v5 stack, which creates the subscription via
		// actions.subscription.create. Checked before every other gate so it also
		// overrides the sitewide mini-cart fallback below.
		if ( $this->is_native_paypal_subscription_page() ) {
			return false;
		}

		$page_location = $this->get_page_context();
		if ( $page_location && $this->settings_status->is_smart_button_enabled_for_location( $page_location ) ) {
			return true;
		}

		if ( $this->is_card_fields_enabled( $page_location ) ) {
			return true;
		}

		// Settings-only, never is_card_button_row(): this runs early enough that
		// resolving WC_Payment_Gateways would re-enter
		// woocommerce_available_payment_gateways, which resolves DisableGateways
		// from the container currently building this service.
		if ( $this->is_card_button_enabled( $page_location ) ) {
			return true;
		}

		if ( $this->should_load_messages() ) {
			return true;
		}

		if ( $page_location && $this->any_placement_renders( $page_location ) ) {
			return true;
		}

		if ( $this->is_fastlane_enabled( $page_location ) ) {
			return true;
		}

		// Sitewide, because the mini-cart can appear on any page as either the
		// classic "Cart" widget or the block Mini-Cart, and is_active_widget()
		// only detects the classic one. boot.js renders into the mini-cart
		// wrapper only where that wrapper exists.
		return $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' )
			|| $this->any_placement_renders( 'mini-cart' );
	}

	/**
	 * Whether the v6 Advanced Card Fields should render on the given page.
	 *
	 * Gates both the JS `card_fields.enabled` flag and the v5 card block's
	 * suppression, so a page never ends up with neither card option.
	 *
	 * @param string|null $location Page context to test; defaults to the current page.
	 */
	public function is_card_fields_enabled( ?string $location = null ): bool {
		$location = $location ?? $this->get_page_context();

		return in_array( $location, array( 'checkout', 'checkout-block', 'pay-now' ), true )
			&& $this->card_payments_configuration->is_acdc_enabled();
	}

	/**
	 * Whether BCDC is configured for this kind of page, not whether the row
	 * prints here, which is is_card_button_row().
	 *
	 * Narrower than its ACDC counterpart: BCDC has no block checkout support.
	 *
	 * @param string|null $location Page context to test; defaults to the current page.
	 */
	public function is_card_button_enabled( ?string $location = null ): bool {
		$location = $location ?? $this->get_page_context();

		return in_array( $location, array( 'checkout', 'pay-now' ), true )
			&& $this->card_payments_configuration->is_bcdc_enabled();
	}

	/**
	 * Whether the BCDC button renders as its own payment-method row here.
	 *
	 * Asks the gateway list rather than re-deriving its policy, which already
	 * covers the checkout button location being off, ACDC outside Mexico,
	 * free-trial carts and zero-total carts.
	 */
	private function is_card_button_row(): bool {
		if ( null !== $this->is_card_button_row ) {
			return $this->is_card_button_row;
		}

		if ( ! $this->is_card_button_enabled() || $this->is_block_context() ) {
			return false;
		}

		// No row for subscription carts: the v6 guest component has no
		// equivalent of the SDK URL vault param v5 uses here, so the button
		// would take a payment that can never renew. Not a dead end: without a
		// button "Place order" stays visible, and CardButtonGateway falls back
		// to PayPal's hosted card checkout. order_pay_contains_subscription()
		// covers pay-for-order, where the cart is empty.
		if ( $this->subscription_helper->cart_contains_subscription()
			|| $this->subscription_helper->order_pay_contains_subscription() ) {
			return false;
		}

		// Memoized only from here on, for the reason is_method_gateway() gives.
		$this->is_card_button_row = isset( $this->available_gateways()[ CardButtonGateway::ID ] );

		return $this->is_card_button_row;
	}

	/**
	 * Whether the current page involves a native PayPal Subscription that the v5
	 * stack must handle: subscriptions_api mode with a subscription in the current
	 * context. v6 has no native-subscription flow (it can only carry a subscription
	 * by vaulting, which this mode disables), so it defers the page to v5.
	 */
	private function is_native_paypal_subscription_page(): bool {
		if ( SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS !== ( $this->get_subscriptions_mode )() ) {
			return false;
		}

		return $this->subscription_helper->current_product_is_subscription()
			|| $this->subscription_helper->cart_contains_subscription()
			|| $this->subscription_helper->order_pay_contains_subscription();
	}

	/**
	 * Whether Fastlane runs on the given page under the v6 SDK.
	 *
	 * This module does not render Fastlane itself: the ppcp-axo modules keep
	 * their UI and only take the SDK object from here, so this gates the
	 * `fastlane` component request and the v5 Fastlane block method staying
	 * registered.
	 *
	 * @param string|null $location Page context to test; defaults to the current page.
	 */
	public function is_fastlane_enabled( ?string $location = null ): bool {
		$location = $location ?? $this->get_page_context();

		if ( ! $location ) {
			return false;
		}

		return $this->fastlane_config->should_render( $location );
	}

	/**
	 * Whether any method renders in the given context.
	 */
	private function any_placement_renders( string $context ): bool {
		foreach ( $this->placements as $placement ) {
			if ( $placement->config->should_render( $context ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether Pay Later messaging alone should pull the v6 SDK onto this page.
	 *
	 * Two exclusions:
	 *
	 * - Block contexts, where claiming the page for messaging alone may
	 *   leave nothing rendering it.
	 * - Pages this module will not actually paint. messages_render_hook()
	 *   returns null for shop, home and the mini-cart fallback context, which
	 *   stay with v5.
	 */
	private function should_load_messages(): bool {
		if ( is_admin() || $this->is_block_context() ) {
			return false;
		}

		if ( ! $this->messages_enabled() ) {
			return false;
		}

		// The Pay Later block prints its own `.ppcp-messages` placeholder, so it
		// needs no hook.
		return null !== $this->messages_render_hook() || $this->has_paylater_block();
	}

	/**
	 * Whether Pay Later messaging is enabled and eligible on this page.
	 */
	public function messages_enabled(): bool {
		if ( is_admin() ) {
			return false;
		}

		return $this->messages_eligibility->is_enabled_for_location(
			$this->messages_settings_location()
		);
	}

	/**
	 * Maps the current page onto a Pay Later messaging settings location.
	 *
	 * The single normalization point for both the style and the eligibility
	 * lookup. SettingsStatus::normalize_location() cannot be relied on here:
	 * it is button-oriented and maps 'checkout-block' to
	 * 'checkout-block-express', a location the messaging settings never
	 * contain, so messaging would silently never enable on the block
	 * checkout.
	 *
	 * Falls back to 'custom_placement' where a Pay Later block sits. Empty for
	 * shop, home and mini-cart, which this module does not serve.
	 */
	private function messages_settings_location(): string {
		switch ( $this->context->location() ) {
			case 'product':
				return 'product';
			case 'cart':
			case 'cart-block':
				return 'cart';
			case 'checkout':
			case 'checkout-block':
			case 'pay-now':
				return 'checkout';
			default:
				return $this->has_paylater_block() ? 'custom_placement' : '';
		}
	}

	/**
	 * Whether an enabled Pay Later block sits on the page being rendered.
	 *
	 * Mirrors the v5 SmartButton's `$has_paylater_block`, which is what carries
	 * messaging onto pages that are not themselves messaging locations.
	 */
	private function has_paylater_block(): bool {
		if ( null !== $this->has_paylater_block ) {
			return $this->has_paylater_block;
		}

		// has_block() reads $post, which only a queried singular request sets —
		// not archives, REST, cron or webhooks, and before `wp` it holds whatever
		// was left over. Left unmemoized: "not resolved yet" is not "no block".
		if ( ! did_action( 'wp' ) || ! ( $GLOBALS['post'] ?? null ) instanceof WP_Post ) {
			return false;
		}

		$this->has_paylater_block = PayLaterBlockModule::is_block_enabled( $this->settings_status )
			&& has_block( 'woocommerce-paypal-payments/paylater-messages' );

		return $this->has_paylater_block;
	}

	/**
	 * The v6 page-type attribute value for the current page.
	 */
	private function messages_page_type(): string {
		switch ( $this->messages_settings_location() ) {
			case 'product':
				return 'product-details';
			case 'cart':
				return 'cart';
			case 'custom_placement':
				// Unclassifiable page, so the neutral 'home' type. A block naming
				// its own placement overrides this per placeholder (pageTypeFor()).
				return 'home';
			default:
				return 'checkout';
		}
	}

	/**
	 * The amount the Pay Later message prices.
	 *
	 * Product-first, matching v5's message_values(): on a product page the
	 * message must price the product the buyer is looking at, even when the
	 * cart already holds other items. This is why transaction_amount() (which
	 * is cart-first, because it feeds button eligibility) cannot be reused.
	 */
	private function messages_amount(): string {
		if ( 'pay-now' === $this->get_page_context() ) {
			$order = $this->order_pay();

			return $order ? number_format( (float) $order->get_total(), 2, '.', '' ) : '';
		}

		// Scoped to the product location rather than probing wc_get_product()
		// unconditionally the way v5 does: on a cart or checkout page a stray
		// global $product, left behind by a theme loop or another plugin,
		// would otherwise make the message price that product instead of the
		// cart. The location check also covers the [product_page] shortcut,
		// where is_product() is false but the context is still 'product'.
		if ( 'product' === $this->messages_settings_location() ) {
			$product = wc_get_product();
			if ( $product instanceof \WC_Product ) {
				return number_format( (float) wc_get_price_including_tax( $product ), 2, '.', '' );
			}
		}

		$cart = WC()->cart;
		if ( $cart ) {
			return number_format( (float) $cart->get_total( 'edit' ), 2, '.', '' );
		}

		return '';
	}

	/**
	 * The action hook the message wrapper renders on, or null when this
	 * module does not place messages on the current page.
	 *
	 * Reuses the v5 filter names so a merchant override relocates both
	 * stacks.
	 *
	 * @return array{name: string, priority: int}|null
	 */
	public function messages_render_hook(): ?array {
		switch ( $this->context->location() ) {
			case 'checkout':
				return $this->message_hook( 'checkout', 'woocommerce_review_order_before_payment', 10 );
			case 'cart':
				/**
				 * The action name that the PayPal buttons use for rendering next to the cart's Proceed to Checkout button.
				 */
				$cart_hook = (string) apply_filters(
					'woocommerce_paypal_payments_proceed_to_checkout_button_renderer_hook',
					'woocommerce_proceed_to_checkout'
				);

				return $this->message_hook( 'cart', $cart_hook, 19 );
			case 'product':
				/**
				 * The action name that the PayPal buttons use for rendering on the single product page.
				 */
				$product_hook = (string) apply_filters(
					'woocommerce_paypal_payments_single_product_renderer_hook',
					'woocommerce_single_product_summary'
				);

				return $this->message_hook( 'product', $product_hook, 30 );
			case 'pay-now':
				return $this->message_hook( 'pay-now', self::PAY_ORDER_MESSAGE_HOOK, 10 );
			default:
				return null;
		}
	}

	/**
	 * Applies the per-location message hook and priority filters.
	 *
	 * @param string $location         The page location.
	 * @param string $default_hook     The default action name.
	 * @param int    $default_priority The default priority.
	 * @return array{name: string, priority: int}
	 */
	private function message_hook( string $location, string $default_hook, int $default_priority ): array {
		$location_hook = 'pay-now' === $location ? 'pay_order' : $location;

		/**
		 * The filter returning the action name that will be used for rendering Pay Later messages.
		 */
		$name = (string) apply_filters(
			"woocommerce_paypal_payments_{$location_hook}_messages_renderer_hook",
			$default_hook
		);

		/**
		 * The filter returning the action priority that will be used for rendering Pay Later messages.
		 */
		$priority = (int) apply_filters(
			"woocommerce_paypal_payments_{$location_hook}_messages_renderer_priority",
			$default_priority
		);

		return array(
			'name'     => $name,
			'priority' => $priority,
		);
	}

	/**
	 * Prints the Pay Later message placeholder.
	 *
	 * Emits the same .ppcp-messages wrapper v5 emits rather than the
	 * <paypal-message> element itself, so one JS mount path serves both these
	 * wrappers and the Pay Later message blocks, and so theme and plugin CSS
	 * keyed on the class keeps working.
	 */
	public function render_message_wrapper(): void {
		$location      = $this->context->location();
		$location_hook = 'pay-now' === $location ? 'pay_order' : $location;

		/**
		 * A hook executed before rendering of the PCP Pay Later messages wrapper.
		 */
		do_action( "ppcp_before_{$location_hook}_message_wrapper" );

		echo '<div class="ppcp-messages"></div>';

		/**
		 * A hook executed after rendering of the PCP Pay Later messages wrapper.
		 */
		do_action( "ppcp_after_{$location_hook}_message_wrapper" );
	}

	/**
	 * The button styles for one context, carrying the height every button in
	 * the express stack shares.
	 *
	 * @param string $context The page context.
	 * @return array{colorClass: string, borderRadius: string, height: string}
	 */
	private function button_styles( string $context ): array {
		return array_merge(
			$this->style_mapper->styles_for_context( $context ),
			array( 'height' => self::PAYMENT_BUTTON_HEIGHT )
		);
	}

	/**
	 * Whether the Pay Later button belongs in a location.
	 *
	 * Mirrors SmartButton::is_pay_later_button_enabled_for_location(). v5 hid
	 * the button through the SDK URL's disable-funding, which has no v6
	 * equivalent, so the decision travels to the renderer instead.
	 *
	 * @param string $location The button location.
	 * @return bool Whether the button may render.
	 */
	private function is_pay_later_button_enabled( string $location ): bool {
		return $this->is_pay_later_filter_enabled( $location )
			&& $this->settings_status->is_pay_later_button_enabled_for_location( $location );
	}

	/**
	 * Whether the filters v5 fires allow Pay Later in a location.
	 *
	 * @param string $location The button location.
	 * @return bool Whether the filters allow the button.
	 */
	private function is_pay_later_filter_enabled( string $location ): bool {
		if ( 'product' === $location ) {
			/**
			 * Allows to decide if the button should be disabled for a given product.
			 */
			return ! apply_filters(
				'woocommerce_paypal_payments_product_buttons_paylater_disabled',
				false,
				$this->pay_later_product_context()
			);
		}

		/**
		 * Allows to decide if the button should be disabled on a given context.
		 */
		return ! apply_filters(
			'woocommerce_paypal_payments_buttons_paylater_disabled',
			false,
			$location
		);
	}

	/**
	 * The context data the product filter receives, as v5 assembles it.
	 *
	 * @return array{product?: \WC_Product, order_total?: float}
	 */
	private function pay_later_product_context(): array {
		$product = wc_get_product();
		if ( ! $product ) {
			return array();
		}

		return array(
			'product'     => $product,
			'order_total' => (float) $product->get_price( 'raw' ),
		);
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

		$button_styles    = array();
		$pay_later_button = array();
		if ( $page_context ) {
			$button_styles[ $page_context ]    = $this->button_styles( $page_context );
			$pay_later_button[ $page_context ] = $this->is_pay_later_button_enabled( $page_context );
		}
		if ( $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' ) ) {
			$button_styles['mini-cart']    = $this->button_styles( 'mini-cart' );
			$pay_later_button['mini-cart'] = $this->is_pay_later_button_enabled( 'mini-cart' );
		}

		// Fastlane replaces the standalone ACDC card row when it renders (guest,
		// non-subscription checkout), matching the v5 advanced-card block guard.
		// Scoped to this JS flag only: is_card_fields_enabled() still gates the v5
		// card block's suppression, so a page keeps exactly one card option.
		$card_fields_enabled = $this->is_card_fields_enabled()
			&& ! $this->is_fastlane_enabled();

		$messages_settings_location = $this->messages_settings_location();

		/*
		 * - final_review: drives the post-approval fork; see V6ExpressComponent.approve().
		 * - is_free_trial_cart, cart_needs_vaulting: a zero-total subscription
		 *   cart is vaulted instead of purchased; see utils/freeTrial.js.
		 */
		$data = array(
			'sdk_url'             => $base_url . '/web-sdk/v6/core',
			'page_context'        => $page_context,
			'currency'            => get_woocommerce_currency(),
			'amount'              => $this->transaction_amount(),
			'buyer_country'       => $buyer_country,
			'merchant_country'    => $this->merchant_country,
			'locale'              => str_replace( '_', '-', get_locale() ),
			'vaulting_enabled'    => $this->vaulting_enabled,
			'final_review'        => $this->final_review_enabled,
			'is_free_trial_cart'  => $this->free_trial_helper->is_free_trial_cart(),
			'cart_needs_vaulting' => $this->free_trial_helper->cart_requires_vaulting(),
			'has_subscriptions'   => $this->subscription_helper->cart_contains_subscription(),
			/**
			 * 3DS/SCA contingency for the card save (setup-token) flow used on a
			 * free-trial card checkout. Filtered like the add-payment-method page.
			 *
			 * @param string $three_d_secure_contingency The default 3D Secure enum value.
			 */
			'verification_method' => (string) apply_filters(
				'woocommerce_paypal_payments_three_d_secure_contingency',
				$this->three_d_secure_contingency
			),
			'user'                => array(
				'is_logged' => is_user_logged_in(),
			),
			'ajax'                => array(
				'client_token'                   => array(
					'endpoint' => \WC_AJAX::get_endpoint( ClientTokenEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ClientTokenEndpoint::nonce() ),
				),
				'change_cart'                    => array(
					'endpoint' => \WC_AJAX::get_endpoint( ChangeCartEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ChangeCartEndpoint::nonce() ),
				),
				'simulate_cart'                  => array(
					'endpoint' => \WC_AJAX::get_endpoint( SimulateCartEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( SimulateCartEndpoint::nonce() ),
				),
				'wallet_shipping'                => array(
					'endpoint' => \WC_AJAX::get_endpoint( CartQuoteEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( CartQuoteEndpoint::nonce() ),
				),
				'create_order'                   => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreateOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreateOrderEndpoint::nonce() ),
				),
				'approve_order'                  => array(
					'endpoint' => \WC_AJAX::get_endpoint( ApproveOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ApproveOrderEndpoint::nonce() ),
				),
				'get_order'                      => array(
					'endpoint' => \WC_AJAX::get_endpoint( GetOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( GetOrderEndpoint::nonce() ),
				),
				'update_shipping'                => array(
					'endpoint' => \WC_AJAX::get_endpoint( UpdateShippingEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( UpdateShippingEndpoint::nonce() ),
				),
				// Vault v3 "save without purchase" endpoints, used by the
				// free-trial checkout flow (see is_free_trial_cart). Registered as
				// wc_ajax actions by ppcp-save-payment-methods when vaulting is on.
				'create_setup_token'             => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreateSetupToken::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreateSetupToken::nonce() ),
				),
				'create_payment_token'           => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreatePaymentToken::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreatePaymentToken::nonce() ),
				),
				'create_payment_token_for_guest' => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreatePaymentTokenForGuest::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreatePaymentTokenForGuest::nonce() ),
				),
				'frontend_log'                   => array(
					'endpoint' => \WC_AJAX::get_endpoint( FrontendLogEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( FrontendLogEndpoint::nonce() ),
				),
				'wc_store_api'                   => array(
					'cart'                 => $store_api_base,
					'select_shipping_rate' => $store_api_base . '/select-shipping-rate',
					'update_customer'      => $store_api_base . '/update-customer',
					'nonce'                => wp_create_nonce( 'wc_store_api' ),
				),
			),
			'urls'                => array(
				'checkout' => wc_get_checkout_url(),
			),
			'labels'              => array(
				'generic_error'          => __(
					'Something went wrong. Please try again or choose another payment source.',
					'woocommerce-paypal-payments'
				),
				// One string for every onWarn the SDK raises: its own codes are
				// internal and untranslated, so they must not reach the buyer.
				'card_declined'          => __(
					'The card could not be charged. Please check the details or try a different card.',
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
			'shipping'            => array(
				'in_context' => $shipping_contexts,
				'countries'  => $this->shipping_countries( $shipping_contexts ),
			),
			'button_styles'       => $button_styles,
			'pay_later_button'    => $pay_later_button,
			'button_height'       => self::PAYMENT_BUTTON_HEIGHT,
			'wrapper'             => '#' . self::WRAPPER_ID,
			'mini_cart_wrapper'   => '#' . self::MINI_CART_WRAPPER_ID,
			'card_fields'         => array(
				'enabled'             => $card_fields_enabled,
				'payment_method'      => CreditCardGateway::ID,
				'funding_source'      => 'card',
				// Label, name-field flag and logos for the block's own card
				// method; card_icons is empty when "Show logos" is off.
				'title'               => $this->card_payments_configuration->gateway_title(),
				'name_field'          => 'yes' === $this->card_payments_configuration->show_name_on_card(),
				// Card "save during purchase". The block checkout gates WC Blocks'
				// native save option on this; classic reads WC's own tokenization
				// checkbox. A subscription force-saves, since its card must be
				// vaulted to renew.
				'is_vaulting_enabled' => $this->card_vaulting_enabled,
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
				'styles'              => $this->card_field_styles->overrides(),
			),
			'card_button'         => array(
				'enabled'        => $this->is_card_button_row(),
				'payment_method' => CardButtonGateway::ID,
				'funding_source' => 'card',
				'wrapper'        => '#' . self::CARD_BUTTON_WRAPPER_ID,
				// No colour: the element is black-only. Width is ours to set
				// because it ships a fixed 225px, where v5 spanned the column.
				'styles'         => array(
					'borderRadius' => $this->style_mapper->styles_for_context( $page_context ?: 'checkout' )['borderRadius'],
					'height'       => self::PAYMENT_BUTTON_HEIGHT,
					'width'        => '100%',
				),
			),
			// Enablement only. The ppcp-axo modules own every other Fastlane
			// setting and localize it as wc_ppcp_axo; this flag tells sdkLoader
			// to request the component their connection then reads off the
			// shared SDK instance.
			'fastlane'            => array(
				'enabled'        => $this->is_fastlane_enabled( $page_context ),
				// The id as a literal, not AxoGateway::ID: ppcp-axo is behind its
				// own feature flag, and SdkV6Module names it the same way.
				'payment_method' => 'ppcp-axo-gateway',
			),
			'messages'            => array(
				'enabled'   => $this->messages_enabled(),
				'wrapper'   => '.ppcp-messages',
				'is_hidden' => $this->messages_eligibility->is_hidden( $page_context ),
				'amount'    => $this->messages_amount(),
				'page_type' => $this->messages_page_type(),
				'style'     => $this->message_style_mapper->styles_for_location( $messages_settings_location ),
			),
		);

		foreach ( $this->placements as $placement ) {
			$data[ $placement->config_key ] = $this->placement_script_data( $placement, $page_context );
		}

		// The keys only one wallet has; everything above is the shared shape.
		$data['google_pay']['environment'] = $this->environment->is_sandbox() ? 'TEST' : 'PRODUCTION';
		// Labels the sheet total and identifies the merchant during validation.
		$data['apple_pay']['display_name'] = $this->apple_pay_config->display_name();
		// Where the frontend reports merchant validation, keeping the admin
		// "domain not validated" notice accurate. The Apple Pay module owns this
		// action and dictates its nonce.
		$data['apple_pay']['validation'] = array(
			'endpoint' => admin_url( 'admin-ajax.php' ),
			'action'   => PropertiesDictionary::VALIDATE,
			'nonce'    => wp_create_nonce( PropertiesDictionary::NONCE_ACTION ),
		);

		// The pay-for-order page builds the PayPal order from an existing WC
		// order rather than the cart, so its identifiers must reach the
		// create-order endpoint's from_wc_order branch.
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
	 */
	private function order_pay_key(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		return is_string( $key ) ? $key : '';
	}

	/**
	 * The WC order for the current pay-for-order page, validated against the
	 * URL order key, or null.
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

		// Stops a crafted URL from reading another customer's order total.
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
			// Carried in the payload rather than a window global, so the gateway
			// is told which source approved the order.
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
	 * The amount eligibility is checked against, which moves Pay Later
	 * thresholds: the cart total, or the product price while the cart is empty.
	 *
	 * @return string A decimal string, or empty when unknown.
	 */
	private function transaction_amount(): string {
		// The pay-for-order page has no cart; its order carries the total.
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
	 * The page context for the current WC page, or empty off a supported page.
	 *
	 * Resolves through the shared Context helper, which handles
	 * classic-shortcode block pages, then narrows to the contexts this module
	 * supports. The block editor stays out of scope.
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
	 */
	public function is_block_context(): bool {
		return in_array(
			$this->get_page_context(),
			array( 'cart-block', 'checkout-block' ),
			true
		);
	}
}
