<?php
/**
 * Register and configure assets provided by this module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;

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
	 * Assets constructor.
	 *
	 * @param string             $module_url The url of this module.
	 * @param string             $version                            The assets version.
	 * @param SubscriptionHelper $subscription_helper The subscription helper.
	 * @param string             $client_id The PayPal SDK client ID.
	 * @param string             $currency 3-letter currency code of the shop.
	 * @param string             $country 2-letter country code of the shop.
	 * @param bool               $is_pay_later_button_enabled Whether Pay Later button is enabled either for checkout, cart or product page.
	 * @param array              $disabled_sources The list of disabled funding sources.
	 * @param array              $all_funding_sources The list of all existing funding sources.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SubscriptionHelper $subscription_helper,
		string $client_id,
		string $currency,
		string $country,
		bool $is_pay_later_button_enabled,
		array $disabled_sources,
		array $all_funding_sources
	) {
		$this->module_url                  = $module_url;
		$this->version                     = $version;
		$this->subscription_helper         = $subscription_helper;
		$this->client_id                   = $client_id;
		$this->currency                    = $currency;
		$this->country                     = $country;
		$this->is_pay_later_button_enabled = $is_pay_later_button_enabled;
		$this->disabled_sources            = $disabled_sources;
		$this->all_funding_sources         = $all_funding_sources;
	}

	/**
	 * Register assets provided by this module.
	 */
	public function register_assets() {
		add_action(
			'admin_enqueue_scripts',
			function() {
				if ( ! is_admin() || wp_doing_ajax() ) {
					return;
				}

				if ( ! $this->is_paypal_payment_method_page() ) {
					return;
				}

				$this->register_admin_assets();
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
		if ( $screen->id !== 'woocommerce_page_wc-settings' ) {
			return false;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab     = wc_clean( wp_unslash( $_GET['tab'] ?? '' ) );
		$section = wc_clean( wp_unslash( $_GET['section'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'checkout' === $tab && 'ppcp-gateway' === $section;
	}

	/**
	 * Register assets for admin pages.
	 */
	private function register_admin_assets(): void {
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
			array(
				'is_subscriptions_plugin_active' => $this->subscription_helper->plugin_is_active(),
				'client_id'                      => $this->client_id,
				'currency'                       => $this->currency,
				'country'                        => $this->country,
				'integration_date'               => PAYPAL_INTEGRATION_DATE,
				'is_pay_later_button_enabled'    => $this->is_pay_later_button_enabled,
				'disabled_sources'               => $this->disabled_sources,
				'all_funding_sources'            => $this->all_funding_sources,
			)
		);
	}
}
