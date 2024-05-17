<?php
/**
 * Register and configure assets provided by this module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingAgreementsEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\RefreshFeatureStatusEndpoint;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class SettingsPageAssets
 */
class SettingsPageAssets {

	/**
	 * The URL of this module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The assets version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * The subscription helper.
	 *
	 * @var SubscriptionHelper
	 */
	protected $subscription_helper;

	/**
	 * The PayPal SDK client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * The environment object.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * Whether Pay Later button is enabled either for checkout, cart or product page.
	 *
	 * @var bool
	 */
	protected $is_pay_later_button_enabled;

	/**
	 * The list of disabled funding sources.
	 *
	 * @var array
	 */
	protected $disabled_sources;

	/**
	 * The list of all existing funding sources.
	 *
	 * @var array
	 */
	protected $all_funding_sources;

	/**
	 * Whether it's a settings page of this plugin.
	 *
	 * @var bool
	 */
	private $is_settings_page;

	/**
	 * Whether the ACDC gateway is enabled.
	 *
	 * @var bool
	 */
	private $is_acdc_enabled;

	/**
	 * Billing Agreements endpoint.
	 *
	 * @var BillingAgreementsEndpoint
	 */
	private $billing_agreements_endpoint;

	/**
	 * Assets constructor.
	 *
	 * @param string                    $module_url The url of this module.
	 * @param string                    $version                            The assets version.
	 * @param SubscriptionHelper        $subscription_helper The subscription helper.
	 * @param string                    $client_id The PayPal SDK client ID.
	 * @param string                    $currency 3-letter currency code of the shop.
	 * @param string                    $country 2-letter country code of the shop.
	 * @param Environment               $environment The environment object.
	 * @param bool                      $is_pay_later_button_enabled Whether Pay Later button is enabled either for checkout, cart or product page.
	 * @param array                     $disabled_sources The list of disabled funding sources.
	 * @param array                     $all_funding_sources The list of all existing funding sources.
	 * @param bool                      $is_settings_page Whether it's a settings page of this plugin.
	 * @param bool                      $is_acdc_enabled Whether the ACDC gateway is enabled.
	 * @param BillingAgreementsEndpoint $billing_agreements_endpoint Billing Agreements endpoint.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SubscriptionHelper $subscription_helper,
		string $client_id,
		string $currency,
		string $country,
		Environment $environment,
		bool $is_pay_later_button_enabled,
		array $disabled_sources,
		array $all_funding_sources,
		bool $is_settings_page,
		bool $is_acdc_enabled,
		BillingAgreementsEndpoint $billing_agreements_endpoint
	) {
		$this->module_url                  = $module_url;
		$this->version                     = $version;
		$this->subscription_helper         = $subscription_helper;
		$this->client_id                   = $client_id;
		$this->currency                    = $currency;
		$this->country                     = $country;
		$this->environment                 = $environment;
		$this->is_pay_later_button_enabled = $is_pay_later_button_enabled;
		$this->disabled_sources            = $disabled_sources;
		$this->all_funding_sources         = $all_funding_sources;
		$this->is_settings_page            = $is_settings_page;
		$this->is_acdc_enabled             = $is_acdc_enabled;
		$this->billing_agreements_endpoint = $billing_agreements_endpoint;
	}

	/**
	 * Register assets provided by this module.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		add_action(
			'admin_enqueue_scripts',
			function() {
				if ( ! is_admin() || wp_doing_ajax() ) {
					return;
				}

				if ( $this->is_settings_page ) {
					$this->register_admin_assets();
				}

				if ( $this->is_paypal_payment_method_page() ) {
					$this->register_paypal_admin_assets();
				}
			}
		);

	}

	/**
	 * Check whether the current page is PayPal payment method settings.
	 *
	 * @return bool
	 */
	private function is_paypal_payment_method_page(): bool {

		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'woocommerce_page_wc-settings' ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab     = wc_clean( wp_unslash( $_GET['tab'] ?? '' ) );
		$section = wc_clean( wp_unslash( $_GET['section'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'checkout' === $tab && in_array( $section, array( PayPalGateway::ID, CardButtonGateway::ID ), true );
	}

	/**
	 * Register assets for PayPal admin pages.
	 */
	private function register_paypal_admin_assets(): void {
		wp_enqueue_style(
			'ppcp-gateway-settings',
			trailingslashit( $this->module_url ) . 'assets/css/gateway-settings.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'ppcp-gateway-settings',
			trailingslashit( $this->module_url ) . 'assets/js/gateway-settings.js',
			array(),
			$this->version,
			true
		);

		/**
		 * Psalm cannot find it for some reason.
		 *
		 * @psalm-suppress UndefinedConstant
		 */
		wp_localize_script(
			'ppcp-gateway-settings',
			'PayPalCommerceGatewaySettings',
			apply_filters(
				'woocommerce_paypal_payments_admin_gateway_settings',
				array(
					'is_subscriptions_plugin_active' => $this->subscription_helper->plugin_is_active(),
					'client_id'                      => $this->client_id,
					'currency'                       => $this->currency,
					'country'                        => $this->country,
					'environment'                    => $this->environment->current_environment(),
					'integration_date'               => PAYPAL_INTEGRATION_DATE,
					'is_pay_later_button_enabled'    => $this->is_pay_later_button_enabled,
					'is_acdc_enabled'                => $this->is_acdc_enabled,
					'disabled_sources'               => $this->disabled_sources,
					'all_funding_sources'            => $this->all_funding_sources,
					'components'                     => array( 'buttons', 'funding-eligibility', 'messages' ),
					'ajax'                           => array(
						'refresh_feature_status' => array(
							'endpoint' => \WC_AJAX::get_endpoint( RefreshFeatureStatusEndpoint::ENDPOINT ),
							'nonce'    => wp_create_nonce( RefreshFeatureStatusEndpoint::nonce() ),
							'button'   => '.ppcp-refresh-feature-status',
							'messages' => array(
								'waiting' => __( 'Checking features...', 'woocommerce-paypal-payments' ),
								'success' => __( 'Feature status refreshed.', 'woocommerce-paypal-payments' ),
							),
						),
					),
					'reference_transaction_enabled'  => $this->billing_agreements_endpoint->reference_transaction_enabled(),
					'vaulting_must_enable_advanced_wallet_message' => sprintf(
						// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
						esc_html__( 'Your PayPal account must be eligible to %1$ssave PayPal and Venmo payment methods%2$s to enable PayPal Vaulting.', 'woocommerce-paypal-payments' ),
						'<a href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection#field-credentials_feature_onboarding_heading">',
						'</a>'
					),
				)
			)
		);
	}

	/**
	 * Register assets for PayPal admin pages.
	 */
	private function register_admin_assets(): void {
		wp_enqueue_style(
			'ppcp-admin-common',
			trailingslashit( $this->module_url ) . 'assets/css/common.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'ppcp-admin-common',
			trailingslashit( $this->module_url ) . 'assets/js/common.js',
			array(),
			$this->version,
			true
		);
	}

}
