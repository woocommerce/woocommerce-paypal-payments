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
use WC_Countries;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Button\Helper\ContextTrait;
use WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

/**
 * Class Button
 */
class Button implements ButtonInterface {

	use ContextTrait;

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
	 * The Subscription Helper.
	 *
	 * @var SubscriptionHelper
	 */
	private $subscription_helper;

	/**
	 * SmartButton constructor.
	 *
	 * @param string             $module_url The URL to the module.
	 * @param string             $sdk_url The URL to the SDK.
	 * @param string             $version The assets version.
	 * @param SessionHandler     $session_handler The Session handler.
	 * @param SubscriptionHelper $subscription_helper The subscription helper.
	 * @param Settings           $settings The Settings.
	 * @param Environment        $environment The environment object.
	 * @param SettingsStatus     $settings_status The Settings status helper.
	 * @param LoggerInterface    $logger The logger.
	 */
	public function __construct(
		string $module_url,
		string $sdk_url,
		string $version,
		SessionHandler $session_handler,
		SubscriptionHelper $subscription_helper,
		Settings $settings,
		Environment $environment,
		SettingsStatus $settings_status,
		LoggerInterface $logger
	) {

		$this->module_url          = $module_url;
		$this->sdk_url             = $sdk_url;
		$this->version             = $version;
		$this->session_handler     = $session_handler;
		$this->subscription_helper = $subscription_helper;
		$this->settings            = $settings;
		$this->environment         = $environment;
		$this->settings_status     = $settings_status;
		$this->logger              = $logger;
	}

	/**
	 * Initializes the button.
	 */
	public function initialize(): void {
		add_filter( 'ppcp_onboarding_options', array( $this, 'add_onboarding_options' ), 10, 1 );
		add_filter( 'ppcp_partner_referrals_option', array( $this, 'filter_partner_referrals_option' ), 10, 1 );
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
		if ( ! apply_filters( 'woocommerce_paypal_payments_google_pay_onboarding_option', false ) ) {
			return $options;
		}

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
			. '<li><label><input type="checkbox" id="ppcp-onboarding-google" ' . $checked . ' data-onboarding-option="ppcp-onboarding-google"> '
			. __( 'Onboard with GooglePay', 'woocommerce-paypal-payments' )
			. '</label></li>';
	}

	/**
	 * Filters a partner referrals option.
	 *
	 * @param array $option The option data.
	 * @return array
	 */
	public function filter_partner_referrals_option( array $option ): array {
		if ( $option['valid'] ) {
			return $option;
		}
		if ( $option['field'] === 'ppcp-onboarding-google' ) {
			$option['valid'] = true;
			$option['value'] = ( $option['value'] ? '1' : '' );
		}
		return $option;
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

		if (
			$this->subscription_helper->plugin_is_active()
			&& ! $this->subscription_helper->accept_manual_renewals()
		) {
			if ( is_product() && $this->subscription_helper->current_product_is_subscription() ) {
				return false;
			}
			if ( $this->subscription_helper->order_pay_contains_subscription() ) {
				return false;
			}
			if ( $this->subscription_helper->cart_contains_subscription() ) {
				return false;
			}
		}

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
					$this->hide_gateway_until_eligible();
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
					$this->hide_gateway_until_eligible();
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
					echo '<span id="ppc-button-googlepay-container-minicart" class="ppcp-button-apm ppcp-button-googlepay ppcp-button-minicart"></span>';
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
		<div id="ppc-button-googlepay-container" class="ppcp-button-apm ppcp-button-googlepay">
			<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Outputs an inline CSS style that hides the Google Pay gateway (on Classic Checkout).
	 * The style is removed by `PaymentButton.js` once the eligibility of the payment method
	 * is confirmed.
	 *
	 * @return void
	 */
	protected function hide_gateway_until_eligible() : void {
		?>
		<style data-hide-gateway='<?php echo esc_attr( GooglePayGateway::ID ); ?>'>
			.wc_payment_method.payment_method_ppcp-googlepay {
				display: none;
			}
		</style>
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

		$this->enqueue_styles();

		wp_localize_script(
			'wc-ppcp-googlepay',
			'wc_ppcp_googlepay',
			$this->script_data()
		);
	}

	/**
	 * Enqueues styles.
	 */
	public function enqueue_styles(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_register_style(
			'wc-ppcp-googlepay',
			untrailingslashit( $this->module_url ) . '/assets/css/styles.css',
			array(),
			$this->version
		);
		wp_enqueue_style( 'wc-ppcp-googlepay' );
	}

	/**
	 * Enqueues scripts/styles for admin.
	 */
	public function enqueue_admin(): void {
		wp_register_style(
			'wc-ppcp-googlepay-admin',
			untrailingslashit( $this->module_url ) . '/assets/css/styles.css',
			array(),
			$this->version
		);
		wp_enqueue_style( 'wc-ppcp-googlepay-admin' );

		wp_register_script(
			'wc-ppcp-googlepay-admin',
			untrailingslashit( $this->module_url ) . '/assets/js/boot-admin.js',
			array(),
			$this->version,
			true
		);
		wp_enqueue_script( 'wc-ppcp-googlepay-admin' );

		wp_localize_script(
			'wc-ppcp-googlepay-admin',
			'wc_ppcp_googlepay_admin',
			$this->script_data()
		);
	}

	/**
	 * The configuration for the smart buttons.
	 *
	 * @return array
	 */
	public function script_data(): array {
		$use_shipping_form = $this->settings->has( 'googlepay_button_shipping_enabled' ) && $this->settings->get( 'googlepay_button_shipping_enabled' );

		// On the product page, only show the shipping form for physical products.
		$context = $this->context();
		if ( $use_shipping_form && 'product' === $context ) {
			$product = wc_get_product();

			if ( ! $product || $product->is_downloadable() || $product->is_virtual() ) {
				$use_shipping_form = false;
			}
		}

		$shipping = array(
			'enabled'    => $use_shipping_form,
			'configured' => wc_shipping_enabled() && wc_get_shipping_method_count( false, true ) > 0,
		);

		if ( $shipping['enabled'] ) {
			$shipping['countries'] = array_keys( $this->wc_countries()->get_shipping_countries() );
		}

		$is_enabled = $this->settings->has( 'googlepay_button_enabled' ) && $this->settings->get( 'googlepay_button_enabled' );

		$available_gateways    = WC()->payment_gateways->get_available_payment_gateways();
		$is_wc_gateway_enabled = isset( $available_gateways[ GooglePayGateway::ID ] );

		return array(
			'environment'           => $this->environment->current_environment_is( Environment::SANDBOX ) ? 'TEST' : 'PRODUCTION',
			'is_debug'              => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'is_enabled'            => $is_enabled,
			'is_wc_gateway_enabled' => $is_wc_gateway_enabled,
			'sdk_url'               => $this->sdk_url,
			'button'                => array(
				'wrapper'           => '#ppc-button-googlepay-container',
				'style'             => $this->button_styles_for_context( 'cart' ), // For now use cart. Pass the context if necessary.
				'mini_cart_wrapper' => '#ppc-button-googlepay-container-minicart',
				'mini_cart_style'   => $this->button_styles_for_context( 'mini-cart' ),
			),
			'shipping'              => $shipping,
			'ajax'                  => array(
				'update_payment_data' => array(
					'endpoint' => \WC_AJAX::get_endpoint( UpdatePaymentDataEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( UpdatePaymentDataEndpoint::nonce() ),
				),
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

	/**
	 * Returns a WC_Countries instance to check shipping
	 *
	 * @return WC_Countries
	 */
	private function wc_countries(): WC_Countries {
		return new WC_Countries();
	}
}
