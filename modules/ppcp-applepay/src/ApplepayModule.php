<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class ApplepayModule
 */
class ApplepayModule implements ModuleInterface {
	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): void {
		$apple_payment_method = $c->get( 'applepay.payment_method' );
		// add onboarding and referrals hooks.
		$apple_payment_method->initialize();
		if ( ! $c->get( 'applepay.enabled' ) ) {
			return;
		}
		if ( ! $c->get( 'applepay.server_supported' ) ) {
			add_action(
				'admin_notices',
				static function () {
					?>
					<div class="notice notice-error">
						<p>
							<?php
							echo wp_kses_post(
								__( 'Apple Pay is not supported on this server. Please contact your hosting provider to enable it.', 'woocommerce-paypal-payments' )
							);
							?>
						</p>
					</div>
					<?php
				}
			);

			return;
		}
		if ( ! $c->get( 'applepay.merchant_validated' ) ) {
			add_action(
				'admin_notices',
				static function () {
					?>
					<div class="notice notice-error">
						<p>
							<?php
							echo wp_kses_post(
								__( 'Apple Pay Validation Error. Please check the requirements.', 'woocommerce-paypal-payments' )
							);
							?>
						</p>
					</div>
					<?php
				}
			);
		}
		$this->loadAssets( $c );
		$env = $c->get( 'onboarding.environment' );
		/**
		 * The environment.
		 *
		 * @var Environment $env
		 */
		$is_sandobx = $env->current_environment_is( Environment::SANDBOX );
		$this->domain_association_file( $is_sandobx );
		$this->renderButtons( $c );

		$apple_payment_method->bootstrapAjaxRequest();
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}

	/**
	 * Loads the assets.
	 *
	 * @param boolean $is_sandbox The environment for this merchant.
	 */
	protected function domain_association_file( $is_sandbox ): void {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$request_uri = (string) filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL );
		if ( strpos( $request_uri, '.well-known/apple-developer-merchantid-domain-association' ) !== false ) {
			$validation_string = $is_sandbox ? 'apple-developer-merchantid-domain-association-sandbox' : 'apple-developer-merchantid-domain-association';
			$validation_file   = file_get_contents( __DIR__ . '/../assets/validation_files/' . $validation_string );
			nocache_headers();
			header( 'Content-Type: text/plain', true, 200 );
			echo $validation_file;// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}
	}

	/**
	 * Registers and enqueues the assets.
	 *
	 * @param ContainerInterface $c The container.
	 * @return void
	 */
	public function loadAssets( ContainerInterface $c ): void {
		add_action(
			'init',
			function () use ( $c ) {
				wp_register_script(
					'wc-ppcp-applepay-sdk',
					$c->get( 'applepay.sdk_script_url' ),
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);
				wp_enqueue_script( 'wc-ppcp-applepay-sdk' );
				wp_register_script(
					'wc-ppcp-paypal-sdk',
					$c->get( 'applepay.paypal_sdk_url' ),
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);
				wp_enqueue_script( 'wc-ppcp-paypal-sdk' );
				wp_register_script(
					'wc-ppcp-applepay',
					$c->get( 'applepay.script_url' ),
					array( 'wc-ppcp-applepay-sdk', 'wc-ppcp-paypal-sdk' ),
					$c->get( 'ppcp.asset-version' ),
					true
				);
				wp_enqueue_script( 'wc-ppcp-applepay' );
				wp_localize_script(
					'wc-ppcp-applepay',
					'wc_ppcp_applepay',
					array()
				);
			}
		);
	}

	/**
	 * ApplePay button markup
	 */
	protected function applePayDirectButton(): void {
		?>
		<div class="ppc-button-wrapper">
			<div id="woocommerce_paypal_payments_applepayDirect-button">
				<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Apple Pay buttons.
	 *
	 * @param ContainerInterface $c The container.
	 * @return void
	 */
	public function renderButtons( ContainerInterface $c ): void {
		$button_enabled_product = $c->get( 'applepay.setting_button_enabled_product' );
		$button_enabled_cart    = $c->get( 'applepay.setting_button_enabled_cart' );
		if ( $button_enabled_product ) {
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_applepay_render_hook_product', 'woocommerce_after_add_to_cart_form' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_after_add_to_cart_form';
			add_action(
				$render_placeholder,
				function () {
					$this->applePayDirectButton();
				}
			);
		}
		if ( $button_enabled_cart ) {
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_applepay_render_hook_cart', 'woocommerce_cart_totals_after_order_total' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_cart_totals_after_order_total';
			add_action(
				$render_placeholder,
				function () {
					$this->applePayDirectButton();
				}
			);
		}
	}
}
