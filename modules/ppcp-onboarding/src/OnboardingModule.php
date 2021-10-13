<?php
/**
 * The onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class OnboardingModule
 */
class OnboardingModule implements ModuleInterface {

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

		$asset_loader = $c->get( 'onboarding.assets' );
		/**
		 * The OnboardingAssets.
		 *
		 * @var OnboardingAssets $asset_loader
		 */
		add_action(
			'admin_enqueue_scripts',
			array(
				$asset_loader,
				'register',
			)
		);
		add_action(
			'woocommerce_settings_checkout',
			array(
				$asset_loader,
				'enqueue',
			)
		);

		add_filter(
			'woocommerce_form_field',
			static function ( $field, $key, $config ) use ( $c ) {
				if ( 'ppcp_onboarding' !== $config['type'] ) {
					return $field;
				}
				$renderer      = $c->get( 'onboarding.render' );
				$is_production = 'production' === $config['env'];

				/**
				 * The OnboardingRenderer.
				 *
				 * @var OnboardingRenderer $renderer
				 */
				ob_start();
				$renderer->render( $is_production );
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
			},
			10,
			3
		);

		add_action(
			'wc_ajax_' . LoginSellerEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'onboarding.endpoint.login-seller' );

				/**
				 * The ChangeCartEndpoint.
				 *
				 * @var ChangeCartEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		// Initialize REST routes at the appropriate time.
		$rest_controller = $c->get( 'onboarding.rest' );
		add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
