<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
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
		$this->load_assets( $c );
		$env = $c->get( 'onboarding.environment' );
		assert( $env instanceof Environment );
		$is_sandobx = $env->current_environment_is( Environment::SANDBOX );
		$this->load_domain_association_file( $is_sandobx );
		$this->render_buttons( $c );

		$apple_payment_method->bootstrap_ajax_request();
		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			static function() use ( $c ) {
				$apple_status_cache = $c->get( 'apple.status-cache' );
				assert( $apple_status_cache instanceof Cache );

				$apple_status_cache->delete( AppleProductStatus::APPLE_STATUS_CACHE_KEY );

				$settings = $c->get( 'wcgateway.settings' );
				$settings->set( 'products_apple_enabled', false );
				$settings->persist();

				// Update caches.
				$apple_status = $c->get( 'applepay.apple-product-status' );
				assert( $apple_status instanceof AppleProductStatus );
				$apple_status->apple_is_active();
			}
		);

		add_action(
			'woocommerce_paypal_payments_on_listening_request',
			static function() use ( $c ) {
				$apple_status = $c->get( 'applepay.apple-product-status' );
				if ( $apple_status->has( AppleProductStatus::APPLE_STATUS_CACHE_KEY ) ) {
					$apple_status->delete( AppleProductStatus::APPLE_STATUS_CACHE_KEY );
				}
			}
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}

	/**
	 * Loads the validation file.
	 *
	 * @param boolean $is_sandbox The environment for this merchant.
	 */
	protected function load_domain_association_file( $is_sandbox ): void {
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
	public function load_assets( ContainerInterface $c ): void {
		add_action(
			'wp',
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
					'wc-ppcp-applepay',
					$c->get( 'applepay.script_url' ),
					array( 'wc-ppcp-applepay-sdk' ),
					$c->get( 'ppcp.asset-version' ),
					true
				);
				wp_register_style(
					'wc-ppcp-applepay',
					$c->get( 'applepay.style_url' ),
					array(),
					$c->get( 'ppcp.asset-version' )
				);
				wp_enqueue_style( 'wc-ppcp-applepay' );
				wp_enqueue_script( 'wc-ppcp-applepay' );
				$data = $c->get( 'applepay.data_to_scripts' )->apple_pay_script_data();
				wp_localize_script(
					'wc-ppcp-applepay',
					'wc_ppcp_applepay',
					$data
				);
			}
		);
	}

	/**
	 * ApplePay button markup
	 */
	protected function apple_pay_direct_button(): void {
		?>
		<div class="ppc-button-wrapper">
			<div id="applepay-container">
				<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Apple Pay buttons in the enabled places.
	 *
	 * @param ContainerInterface $c The container.
	 * @return void
	 */
	public function render_buttons( ContainerInterface $c ): void {
		$button_enabled_product = $c->get( 'applepay.setting_button_enabled_product' );
		$button_enabled_cart    = $c->get( 'applepay.setting_button_enabled_cart' );
		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			function( $components ) {
				$components[] = 'applepay';
				return $components;
			}
		);
		if ( $button_enabled_product ) {
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_applepay_render_hook_product', 'woocommerce_after_add_to_cart_form' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_after_add_to_cart_form';
			add_action(
				$render_placeholder,
				function () {
					$this->apple_pay_direct_button();
				}
			);
		}
		if ( $button_enabled_cart ) {
			$render_placeholder = apply_filters( 'woocommerce_paypal_payments_applepay_render_hook_cart', 'woocommerce_cart_totals_after_order_total' );
			$render_placeholder = is_string( $render_placeholder ) ? $render_placeholder : 'woocommerce_cart_totals_after_order_total';
			add_action(
				$render_placeholder,
				function () {
					$this->apple_pay_direct_button();
				}
			);
		}
	}
}
