<?php
/**
 * Registers and configures the necessary Javascript for the button, credit messaging and DCC fields.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Assets;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class Button
 */
class Button implements ButtonInterface {

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
	 * Initializes the button.
	 */
	public function initialize(): void {
		add_filter( 'ppcp_onboarding_options', array( $this, 'add_onboarding_options' ), 10, 1 );
		add_filter( 'ppcp_partner_referrals_data', array( $this, 'add_partner_referrals_data' ), 10, 1 );
	}

	/**
	 * Adds the GooglePay onboarding option.
	 *
	 * @param string $options The options.
	 * @return string
	 *
	 * @psalm-suppress MissingClosureParamType
	 */
	public function add_onboarding_options( $options ): string {
		$checked = '';
		try {
			$onboard_with_google = $this->settings->get( 'ppcp-onboarding-google' );
			if ( $onboard_with_google === '1' ) {
				$checked = 'checked';
			}
		} catch ( NotFoundException $exception ) {
			$checked = '';
		}

		return $options
			. '<li><label><input type="checkbox" id="ppcp-onboarding-google" ' . $checked . '> '
			. __( 'Onboard with GooglePay', 'woocommerce-paypal-payments' )
			. '</label></li>';
	}

	/**
	 * Adds to partner referrals data.
	 *
	 * @param array $data The referrals data.
	 * @return array
	 */
	public function add_partner_referrals_data( array $data ): array {
		try {
			$onboard_with_google = $this->settings->get( 'ppcp-onboarding-google' );
			if ( ! wc_string_to_bool( $onboard_with_google ) ) {
				return $data;
			}
		} catch ( NotFoundException $exception ) {
			return $data;
		}

		if ( ! in_array( 'PAYMENT_METHODS', $data['products'], true ) ) {
			if ( in_array( 'PPCP', $data['products'], true ) ) {
				$data['products'][] = 'PAYMENT_METHODS';
			} elseif ( in_array( 'EXPRESS_CHECKOUT', $data['products'], true ) ) { // A bit sketchy, maybe replace on the EXPRESS_CHECKOUT index.
				$data['products'][0] = 'PAYMENT_METHODS';
			}
		}

		$data['capabilities'][] = 'GOOGLE_PAY';

		$nonce = $data['operations'][0]['api_integration_preference']['rest_api_integration']['first_party_details']['seller_nonce'];

		$data['operations'][] = array(
			'operation'                  => 'API_INTEGRATION',
			'api_integration_preference' => array(
				'rest_api_integration' => array(
					'integration_method'  => 'PAYPAL',
					'integration_type'    => 'THIRD_PARTY',
					'third_party_details' => array(
						'features'     => array(
							'PAYMENT',
							'REFUND',
						),
						'seller_nonce' => $nonce,
					),
				),
			),
		);

		return $data;
	}

	/**
	 * Returns if Google Pay button is enabled
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		try {
			return $this->settings->has( 'googlepay_button_enabled' ) && $this->settings->get( 'googlepay_button_enabled' );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Registers the necessary action hooks to render the HTML depending on the settings.
	 *
	 * @return bool
	 *
	 * @psalm-suppress RedundantCondition
	 */
	public function render(): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$button_enabled_product  = $this->settings_status->is_smart_button_enabled_for_location( 'product' );
		$button_enabled_cart     = $this->settings_status->is_smart_button_enabled_for_location( 'cart' );
		$button_enabled_checkout = true;
		$button_enabled_payorder = true;
		$button_enabled_minicart = $this->settings_status->is_smart_button_enabled_for_location( 'mini-cart' );

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

		if ( $button_enabled_payorder ) {
			$default_hook_name  = 'woocommerce_paypal_payments_payorder_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_payorder_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					$this->googlepay_button();
				},
				21
			);
		}

		if ( $button_enabled_minicart ) {
			$default_hook_name  = 'woocommerce_paypal_payments_minicart_button_render';
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_googlepay_minicart_button_render_hook', $default_hook_name );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : $default_hook_name;
			add_action(
				$render_placeholder,
				function () {
					echo '<span id="ppc-button-googlepay-container-minicart" class="ppcp-button-googlepay ppcp-button-minicart"></span>';
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
		<div id="ppc-button-googlepay-container" class="ppcp-button-googlepay">
			<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Enqueues scripts/styles.
	 */
	public function enqueue(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

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
			'environment' => $this->environment->current_environment_is( Environment::SANDBOX ) ? 'TEST' : 'PRODUCTION',
			'sdk_url'     => $this->sdk_url,
			'button'      => array(
				'wrapper'           => '#ppc-button-googlepay-container',
				'style'             => $this->button_styles_for_context( 'cart' ), // For now use cart. Pass the context if necessary.
				'mini_cart_wrapper' => '#ppc-button-googlepay-container-minicart',
				'mini_cart_style'   => $this->button_styles_for_context( 'mini-cart' ),
			),
		);
	}

	/**
	 * Determines the style for a given indicator in a given context.
	 *
	 * @param string $context The context.
	 *
	 * @return array
	 */
	private function button_styles_for_context( string $context ): array {
		// Use the cart/checkout styles for blocks.
		$context = str_replace( '-block', '', $context );

		$values = array(
			'color'    => 'black',
			'type'     => 'pay',
			'language' => 'en',
		);

		foreach ( $values as $style => $value ) {
			if ( $this->settings->has( 'googlepay_button_' . $context . '_' . $style ) ) {
				$values[ $style ] = $this->settings->get( 'googlepay_button_' . $context . '_' . $style );
			} elseif ( $this->settings->has( 'googlepay_button_' . $style ) ) {
				$values[ $style ] = $this->settings->get( 'googlepay_button_' . $style );
			}
		}

		return $values;
	}

}
