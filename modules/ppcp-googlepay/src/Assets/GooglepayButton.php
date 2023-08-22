<?php
/**
 * Registers and configures the necessary Javascript for the button, credit messaging and DCC fields.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Assets;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
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
		string $currency,
		LoggerInterface $logger
	) {

		$this->module_url      = $module_url;
		$this->sdk_url         = $sdk_url;
		$this->version         = $version;
		$this->session_handler = $session_handler;
		$this->settings        = $settings;
		$this->environment     = $environment;
		$this->currency        = $currency;
		$this->logger          = $logger;
	}

	/**
	 * Registers the necessary action hooks to render the HTML depending on the settings.
	 *
	 * @return bool
	 */
	public function render_buttons(): bool {
		$button_enabled_product = $this->settings->has( 'googlepay_button_enabled_product' ) ? $this->settings->get( 'googlepay_button_enabled_product' ) : false;
		$button_enabled_cart    = $this->settings->has( 'googlepay_button_enabled_cart' ) ? $this->settings->get( 'googlepay_button_enabled_cart' ) : false;

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
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_render_hook_product', 'woocommerce_single_product_summary' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_single_product_summary';
			add_action(
				$render_placeholder,
				function () {
					$this->googlepay_button();
				},
				32
			);
		}

		if ( $button_enabled_cart ) {
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_render_hook_cart', 'woocommerce_proceed_to_checkout' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_proceed_to_checkout';
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
		return true;
	}

	/**
	 * Enqueues scripts/styles.
	 */
	public function enqueue(): void {
		wp_register_script(
			'wc-ppcp-googlepay-sdk',
			$this->sdk_url,
			array(),
			$this->version,
			true
		);
		wp_enqueue_script( 'wc-ppcp-googlepay-sdk' );

		wp_register_script(
			'wc-ppcp-googlepay',
			untrailingslashit( $this->module_url ) . '/assets/js/boot.js',
			array( 'wc-ppcp-googlepay-sdk' ),
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
			'button' => array(
				'wrapper' => '#ppc-button-googlepay-container',
			),
		);
	}

}
