<?php
/**
 * The onboarding module.
 *
 * @package Inpsyde\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class OnboardingModule
 */
class OnboardingModule implements ModuleInterface {

	/**
	 * Sets up the module.
	 *
	 * @return ServiceProviderInterface
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * Runs the module.
	 *
	 * @param ContainerInterface $container The container.
	 */
	public function run( ContainerInterface $container ) {

		$asset_loader = $container->get( 'onboarding.assets' );
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
			static function ( $field, $key, $config ) use ( $container ) {
				if ( 'ppcp_onboarding' !== $config['type'] ) {
					return $field;
				}
				$renderer = $container->get( 'onboarding.render' );

				/**
				 * The OnboardingRenderer.
				 *
				 * @var OnboardingRenderer $renderer
				 */
				ob_start();
				$renderer->render();
				$content = ob_get_contents();
				ob_end_clean();
				return $content;
			},
			10,
			3
		);

		add_action(
			'wc_ajax_' . LoginSellerEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'onboarding.endpoint.login-seller' );

				/**
				 * The ChangeCartEndpoint.
				 *
				 * @var ChangeCartEndpoint $endpoint
				 */
				$endpoint->handleRequest();
			}
		);
	}
}
