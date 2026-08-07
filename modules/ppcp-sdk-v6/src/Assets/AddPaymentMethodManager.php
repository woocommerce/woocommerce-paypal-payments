<?php
/**
 * Manages the SDK v6 assets for the My Account › Add Payment Method page.
 *
 * Renders the PayPal "save for later" (vault) button with the v6 Web SDK,
 * replacing the v5 paypal.Buttons({ createVaultSetupToken }) flow. The card
 * fields on this page remain on the v5 stack for now, so this manager only
 * loads the PayPal save button; the v5 script is left to render the card
 * fields (it loads under its own data-namespace and does not claim the
 * window.paypal global used by the v6 SDK).
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
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

class AddPaymentMethodManager {

	public const WRAPPER_ID = 'ppc-button-ppcp-gateway-save-payment-method';

	private AssetGetter $asset_getter;
	private string $version;
	private Environment $environment;
	private Context $context;
	private bool $vaulting_enabled;

	public function __construct(
		AssetGetter $asset_getter,
		string $version,
		Environment $environment,
		Context $context,
		bool $vaulting_enabled
	) {
		$this->asset_getter     = $asset_getter;
		$this->version          = $version;
		$this->environment      = $environment;
		$this->context          = $context;
		$this->vaulting_enabled = $vaulting_enabled;
	}

	/**
	 * Enqueues the add-payment-method bootstrap script.
	 *
	 * @return void
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

		// The v5 add-payment-method script (kept for the deferred card fields)
		// toggles the ppcp-hidden class on #place_order to hide the native
		// submit button while PayPal is selected, but the rule that hides it
		// lives in the v5 smart-button stylesheet, which is suppressed on this
		// page (see extensions.php). Ship just that one rule so the PayPal
		// button is the only submit control while PayPal is the chosen method.
		wp_register_style( 'wc-ppcp-sdk-v6-add-payment-method', false, array(), $this->version );
		wp_enqueue_style( 'wc-ppcp-sdk-v6-add-payment-method' );
		wp_add_inline_style(
			'wc-ppcp-sdk-v6-add-payment-method',
			'#place_order.ppcp-hidden{display:none !important;}'
		);
	}

	/**
	 * Whether the v6 save button loads on the current page.
	 *
	 * Mirrors the v5 add-payment-method gating: logged-in users on the
	 * Add Payment Method page while wallet vaulting is enabled.
	 *
	 * @return bool
	 */
	public function should_load_on_current_page(): bool {
		return is_user_logged_in()
			&& $this->vaulting_enabled
			&& $this->context->is_add_payment_method_page();
	}

	/**
	 * The configuration data for the add-payment-method bootstrap script.
	 *
	 * @return array
	 */
	private function script_data(): array {
		$base_url = $this->environment->is_sandbox()
			? 'https://www.sandbox.paypal.com'
			: 'https://www.paypal.com';

		return array(
			'sdk_url'              => $base_url . '/web-sdk/v6/core',
			'currency'             => get_woocommerce_currency(),
			'locale'               => str_replace( '_', '-', get_locale() ),
			'payment_methods_page' => wc_get_account_endpoint_url( 'payment-methods' ),
			'button'               => array(
				'wrapper'     => '#' . self::WRAPPER_ID,
				'color_class' => 'paypal-gold',
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
