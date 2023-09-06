<?php
/**
 * The Applepay module.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Applepay\Assets\AppleProductStatus;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
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
		$apple_payment_method = $c->get( 'applepay.button' );
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
					<div class="notice notice-error is-dismissible">
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
		$settings = $c->get( 'wcgateway.settings' );
		$merchant_validated = $settings->has( 'applepay_validated' ) ? $settings->get( 'applepay_validated' ) === true : false;
		if ( ! $merchant_validated ) {
			add_action(
				'admin_notices',
				static function () {
					?>
					<div class="notice notice-error is-dismissible">
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
		$this->handle_validation_file($c);
		$this->render_buttons( $c );
		assert( $apple_payment_method instanceof ButtonInterface );
		$apple_payment_method->bootstrap_ajax_request();
		/*add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c ): void {
				$payment_method_registry->register( $c->get( 'googlepay.blocks-payment-method' ) );
			}
		);*/

		$this->remove_status_cache($c);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}

	/**
	 * Loads the validation string.
	 *
	 * @param boolean $is_sandbox The environment for this merchant.
	 */
	protected function load_domain_association_file(bool $is_sandbox, $settings ): void {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$request_uri = (string) filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL );
		if ( strpos( $request_uri, '.well-known/apple-developer-merchantid-domain-association' ) !== false ) {
			$validation_string = $is_sandbox ? 'applepay_sandbox_validation_file' : 'applepay_live_validation_file';
			$validation_string   = $settings->has( $validation_string ) ? $settings->get( $validation_string ) : '';
			$validation_string = preg_replace( '/\s+/', '', $validation_string );
			$validation_string = $validation_string ? preg_replace( '/[^a-zA-Z0-9]/', '', $validation_string ) : '';
			nocache_headers();
			header( 'Content-Type: text/plain', true, 200 );
			echo $validation_string;// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			'wp_enqueue_scripts',
			function () use ( $c ) {
				$button = $c->get( 'applepay.button' );
				assert( $button instanceof ButtonInterface );

				if ( $button->should_load_script() ) {
					$button->enqueue();
				}
			}
		);
	}

	/**
	 * Renders the Apple Pay buttons in the enabled places.
	 *
	 * @param ContainerInterface $c The container.
	 * @return void
	 */
	public function render_buttons( ContainerInterface $c ): void {
		add_action(
			'wp',
			static function () use ( $c ) {
				if ( is_admin() ) {
					return;
				}
				$button = $c->get( 'applepay.button' );

				/**
				 * The Button.
				 *
				 * @var ButtonInterface $button
				 */
				$button->render();
			}
		);
	}

	/**
	 * @param ContainerInterface $c
	 * @return void
	 */
	public function remove_status_cache(ContainerInterface $c): void
	{
		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			static function () use ($c) {
				$apple_status_cache = $c->get('applepay.status-cache');
				assert($apple_status_cache instanceof Cache);

				$apple_status_cache->delete(AppleProductStatus::APPLE_STATUS_CACHE_KEY);

				$settings = $c->get('wcgateway.settings');
				$settings->set('products_apple_enabled', false);
				$settings->persist();

				// Update caches.
				$apple_status = $c->get('applepay.apple-product-status');
				assert($apple_status instanceof AppleProductStatus);
				$apple_status->apple_is_active();
			}
		);

		add_action(
			'woocommerce_paypal_payments_on_listening_request',
			static function () use ($c) {
				$apple_status = $c->get('applepay.status-cache');
				if ($apple_status->has(AppleProductStatus::APPLE_STATUS_CACHE_KEY)) {
					$apple_status->delete(AppleProductStatus::APPLE_STATUS_CACHE_KEY);
				}
			}
		);
	}

	/**
	 * @param ContainerInterface $c
	 * @return void
	 */
	public function handle_validation_file(ContainerInterface $c): void
	{
		$env = $c->get('onboarding.environment');
		assert($env instanceof Environment);
		$is_sandobx = $env->current_environment_is(Environment::SANDBOX);
		$settings = $c->get('wcgateway.settings');
		$this->load_domain_association_file($is_sandobx, $settings);
	}
}
