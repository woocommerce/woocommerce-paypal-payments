<?php
/**
 * Registers and configures the necessary Javascript for the button, credit messaging and DCC fields.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Assets;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class SmartButton
 */
class GooglepayButton implements ButtonInterface {

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The URL to the SDK.
	 *
	 * @var string
	 */
	private $sdk_url;

	/**
	 * The assets version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The environment object.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * The Settings status helper.
	 *
	 * @var SettingsStatus
	 */
	private $settings_status;

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * SmartButton constructor.
	 *
	 * @param string          $module_url The URL to the module.
	 * @param string          $sdk_url The URL to the SDK.
	 * @param string          $version The assets version.
	 * @param SessionHandler  $session_handler The Session handler.
	 * @param Settings        $settings The Settings.
	 * @param Environment     $environment The environment object.
	 * @param SettingsStatus  $settings_status The Settings status helper.
	 * @param string          $currency 3-letter currency code of the shop.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $module_url,
		string $sdk_url,
		string $version,
		SessionHandler $session_handler,
		Settings $settings,
		Environment $environment,
		SettingsStatus $settings_status,
		string $currency,
		LoggerInterface $logger
	) {

		$this->module_url      = $module_url;
		$this->sdk_url         = $sdk_url;
		$this->version         = $version;
		$this->session_handler = $session_handler;
		$this->settings        = $settings;
		$this->environment     = $environment;
		$this->settings_status = $settings_status;
		$this->currency        = $currency;
		$this->logger          = $logger;
	}

	/**
	 * Registers the necessary action hooks to render the HTML depending on the settings.
	 *
	 * @return bool
	 */
	public function render_buttons(): bool {
		$is_googlepay_button_enabled = $this->settings->has( 'googlepay_button_enabled' ) ? $this->settings->get( 'googlepay_button_enabled' ) : false;

		$button_enabled_product  = $is_googlepay_button_enabled && $this->settings_status->is_smart_button_enabled_for_location( 'product' );
		$button_enabled_cart     = $is_googlepay_button_enabled && $this->settings_status->is_smart_button_enabled_for_location( 'cart' );
		$button_enabled_checkout = $is_googlepay_button_enabled;

		/**
		 * Param types removed to avoid third-party issues.
		 *
		 * @psalm-suppress MissingClosureParamType
		 */
		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			function( $components ) {
				$components[] = 'googlepay';
				return $components;
			}
		);

		if ( $button_enabled_product ) {
			$default_hook_name  = 'woocommerce_paypal_payments_single_product_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_single_product_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					$this->googlepay_button();
				},
				32
			);
		}

		if ( $button_enabled_cart ) {
			$default_hook_name  = 'woocommerce_paypal_payments_cart_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_cart_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					if ( ! is_cart() /* TODO : check other conditions */ ) {
						return;
					}

					$this->googlepay_button();
				},
				21
			);
		}

		if ( $button_enabled_checkout ) {
			$default_hook_name  = 'woocommerce_paypal_payments_checkout_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_checkout_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					$this->googlepay_button();
				},
				21
			);
		}

		return true;
	}

	/**
	 * GooglePay button markup
	 */
	private function googlepay_button(): void {
		?>
		<div class="ppc-button-wrapper">
			<div id="ppc-button-googlepay-container">
				<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Whether any of the scripts should be loaded.
	 */
	public function should_load_script(): bool {
		return true; // TODO.
	}

	/**
	 * Enqueues scripts/styles.
	 */
	public function enqueue(): void {
		wp_register_script(
			'wc-ppcp-googlepay',
			untrailingslashit( $this->module_url ) . '/assets/js/boot.js',
			array(),
			$this->version,
			true
		);
		wp_enqueue_script( 'wc-ppcp-googlepay' );

		wp_register_style(
			'wc-ppcp-googlepay',
			untrailingslashit( $this->module_url ) . '/assets/css/styles.css',
			array(),
			$this->version
		);
		wp_enqueue_style( 'wc-ppcp-googlepay' );

		wp_localize_script(
			'wc-ppcp-googlepay',
			'wc_ppcp_googlepay',
			$this->script_data()
		);
	}

	/**
	 * The configuration for the smart buttons.
	 *
	 * @return array
	 */
	public function script_data(): array {
		return array(
			'sdk_url' => $this->sdk_url,
			'button'  => array(
				'wrapper' => '#ppc-button-googlepay-container',
			),
		);
	}

}
