<?php
/**
 * The Axo module.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Assets\AxoManager;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingOptionsRenderer;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AxoModule
 */
class AxoModule implements ModuleInterface {
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

		add_filter(
			'woocommerce_payment_gateways',
			function ( $methods ) use ( $c ): array {
				$gateway = $c->get( 'axo.gateway' );

				// Add the gateway in admin area.
				if ( is_admin() ) {
					$methods[] = $gateway;
					return $methods;
				}

				// Check if the module is applicable, correct country, currency, ... etc.
				if ( ! $c->get( 'axo.eligible' ) ) {
					return $methods;
				}

				// TODO: check product status eligibility.

				if ( is_user_logged_in() ) {
					return $methods;
				}

				$methods[] = $gateway;
				return $methods;
			},
			1,
			9
		);

		add_filter(
			'ppcp_onboarding_dcc_table_rows',
			function ( $rows, $renderer ): array {
				if ( $renderer instanceof  OnboardingOptionsRenderer ) {
					$rows[] = $renderer->render_table_row(
						__( 'Fastlane by PayPal', 'woocommerce-paypal-payments' ),
						__( 'Yes', 'woocommerce-paypal-payments' ),
						__( 'Help accelerate guest checkout with PayPal\'s autofill solution.', 'woocommerce-paypal-payments' )
					);
				}
				return $rows;
			},
			10,
			2
		);

		add_action(
			'init',
			static function () use ( $c ) {

				// Check if the module is applicable, correct country, currency, ... etc.
				if ( ! $c->get( 'axo.eligible' ) ) {
					return;
				}

				$manager = $c->get( 'axo.manager' );
				assert( $manager instanceof AxoManager );

				// Enqueue frontend scripts.
				add_action(
					'wp_enqueue_scripts',
					static function () use ( $c, $manager ) {
						$smart_button = $c->get( 'button.smart-button' );
						assert( $smart_button instanceof SmartButtonInterface );

						if ( $smart_button->should_load_ppcp_script() ) {
							$manager->enqueue();
						}
					}
				);

				// Render submit button.
				add_action(
					$manager->checkout_button_renderer_hook(),
					static function () use ( $c, $manager ) {
						$manager->render_checkout_button();
					}
				);

				/**
				 * Param types removed to avoid third-party issues.
				 *
				 * @psalm-suppress MissingClosureParamType
				 */
				add_filter(
					'woocommerce_paypal_payments_sdk_components_hook',
					function( $components ) {
						$components[] = 'fastlane';
						return $components;
					}
				);

			},
			1
		);

	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
