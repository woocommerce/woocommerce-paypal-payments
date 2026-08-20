<?php
/**
 * Manages the SDK v6 assets for the My Account › Add Payment Method page.
 *
 * Renders the v6 "save for later" surfaces (PayPal wallet save button + card
 * save fields) that replace the v5 add-payment-method.js. v6 owns this page
 * fully when it loads: the v5 script and smart button are suppressed
 * elsewhere (see SavePaymentMethodsModule + extensions.php), so this bootstrap
 * also ships the small rule that hides the native submit button while the
 * PayPal method is selected.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreateSetupToken;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\CardFieldStyles;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

class AddPaymentMethodManager {

	public const WRAPPER_ID = 'ppc-button-ppcp-gateway-save-payment-method';

	// Same WC credit-card-form field IDs the classic/block card fields mount
	// into (see SdkV6Manager); rendered by the card gateway on this page too.
	private const CARD_FIELD_NAME_ID   = 'ppcp-credit-card-gateway-card-name';
	private const CARD_FIELD_NUMBER_ID = 'ppcp-credit-card-gateway-card-number';
	private const CARD_FIELD_EXPIRY_ID = 'ppcp-credit-card-gateway-card-expiry';
	private const CARD_FIELD_CVV_ID    = 'ppcp-credit-card-gateway-card-cvc';

	private AssetGetter $asset_getter;
	private string $version;
	private Environment $environment;
	private Context $context;
	private bool $paypal_vaulting_enabled;
	private bool $card_vaulting_enabled;
	private SettingsProvider $settings_provider;

	private CardFieldStyles $card_field_styles;

	public function __construct(
		AssetGetter $asset_getter,
		string $version,
		Environment $environment,
		Context $context,
		bool $paypal_vaulting_enabled,
		bool $card_vaulting_enabled,
		SettingsProvider $settings_provider,
		CardFieldStyles $card_field_styles
	) {
		$this->asset_getter            = $asset_getter;
		$this->version                 = $version;
		$this->environment             = $environment;
		$this->context                 = $context;
		$this->paypal_vaulting_enabled = $paypal_vaulting_enabled;
		$this->card_vaulting_enabled   = $card_vaulting_enabled;
		$this->settings_provider       = $settings_provider;
		$this->card_field_styles       = $card_field_styles;
	}

	/**
	 * Enqueues the add-payment-method bootstrap script.
	 */
	public function enqueue(): void {
		if ( ! $this->should_load_on_current_page() ) {
			return;
		}

		$script_url = $this->asset_getter->get_asset_url( 'boot-add-payment-method.js' );
		if ( ! $script_url ) {
			return;
		}

		wp_register_script(
			'wc-ppcp-sdk-v6-add-payment-method',
			$script_url,
			array(),
			$this->version,
			true
		);

		wp_localize_script(
			'wc-ppcp-sdk-v6-add-payment-method',
			'wc_ppcp_sdk_v6_save',
			$this->script_data()
		);

		wp_enqueue_script( 'wc-ppcp-sdk-v6-add-payment-method' );

		// v5's smart-button stylesheet (which carries this rule) is suppressed
		// on this page, so the v6 boot's method-visibility toggling of
		// #place_order needs the rule shipped here.
		wp_register_style( 'wc-ppcp-sdk-v6-add-payment-method', false, array(), $this->version );
		wp_enqueue_style( 'wc-ppcp-sdk-v6-add-payment-method' );
		wp_add_inline_style(
			'wc-ppcp-sdk-v6-add-payment-method',
			'#place_order.ppcp-hidden{display:none !important;}'
		);
	}

	/**
	 * Whether the v6 save surfaces load on the current page.
	 */
	public function should_load_on_current_page(): bool {
		return is_user_logged_in()
			&& ( $this->paypal_vaulting_enabled || $this->card_vaulting_enabled )
			&& $this->context->is_add_payment_method_page();
	}

	/**
	 * The configuration data for the add-payment-method bootstrap script.
	 */
	private function script_data(): array {
		$base_url = $this->environment->is_sandbox()
			? 'https://www.sandbox.paypal.com'
			: 'https://www.paypal.com';

		/**
		 * Filters the 3DS/SCA contingency used when creating the card setup
		 * token for the save-for-later flow.
		 *
		 * @param string $verification_method The default 3D Secure enum value.
		 */
		$verification_method = (string) apply_filters(
			'woocommerce_paypal_payments_three_d_secure_contingency',
			$this->settings_provider->three_d_secure_enum()
		);

		return array(
			'sdk_url'              => $base_url . '/web-sdk/v6/core',
			'currency'             => get_woocommerce_currency(),
			'locale'               => str_replace( '_', '-', get_locale() ),
			'verification_method'  => $verification_method,
			'payment_methods_page' => wc_get_account_endpoint_url( 'payment-methods' ),
			'button'               => array(
				'wrapper'     => '#' . self::WRAPPER_ID,
				'color_class' => 'paypal-gold',
			),
			'card_fields'          => array(
				'enabled'        => $this->card_vaulting_enabled,
				'payment_method' => CreditCardGateway::ID,
				'funding_source' => 'card',
				'fields'         => array(
					'name'   => '#' . self::CARD_FIELD_NAME_ID,
					'number' => '#' . self::CARD_FIELD_NUMBER_ID,
					'expiry' => '#' . self::CARD_FIELD_EXPIRY_ID,
					'cvv'    => '#' . self::CARD_FIELD_CVV_ID,
				),
				'styles'         => $this->card_field_styles->overrides(),
			),
			'ajax'                 => array(
				'client_token'         => array(
					'endpoint' => \WC_AJAX::get_endpoint( ClientTokenEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ClientTokenEndpoint::nonce() ),
				),
				'create_setup_token'   => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreateSetupToken::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreateSetupToken::nonce() ),
				),
				'create_payment_token' => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreatePaymentToken::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreatePaymentToken::nonce() ),
				),
			),
			'labels'               => array(
				'generic_error' => __(
					'Something went wrong. Please try again or choose another payment source.',
					'woocommerce-paypal-payments'
				),
			),
		);
	}
}
