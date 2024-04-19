<?php
/**
 * The Axo module.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
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
		$module = $this;

		add_filter(
			'woocommerce_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $methods ) use ( $c ): array {
				if ( ! is_array( $methods ) ) {
					return $methods;
				}

				$gateway = $c->get( 'axo.gateway' );

				// Check if the module is applicable, correct country, currency, ... etc.
				if ( ! $c->get( 'axo.eligible' ) ) {
					return $methods;
				}

				// Add the gateway in admin area.
				if ( is_admin() ) {
					$methods[] = $gateway;
					return $methods;
				}

				if ( is_user_logged_in() ) {
					return $methods;
				}

				$methods[] = $gateway;
				return $methods;
			},
			1,
			9
		);

		add_action(
			'init',
			static function () use ( $c, $module ) {

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

				add_action(
					'wp_head',
					function () {
						// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
						echo '<script async src="https://www.paypalobjects.com/insights/v1/paypal-insights.sandbox.min.js"></script>';
					}
				);

				add_filter(
					'woocommerce_paypal_payments_localized_script_data',
					function( array $localized_script_data ) use ( $c, $module ) {
						$api = $c->get( 'api.sdk-client-token' );
						assert( $api instanceof SdkClientToken );

						$logger = $c->get( 'woocommerce.logger.woocommerce' );
						assert( $logger instanceof LoggerInterface );

						return $module->add_sdk_client_token_to_script_data( $api, $logger, $localized_script_data );
					}
				);

				add_filter(
					'ppcp_onboarding_dcc_table_rows',
					/**
					 * Param types removed to avoid third-party issues.
					 *
					 * @psalm-suppress MissingClosureParamType
					 */
					function ( $rows, $renderer ): array {
						if ( ! is_array( $rows ) ) {
							return $rows;
						}

						if ( $renderer instanceof OnboardingOptionsRenderer ) {
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

			},
			1
		);

	}

	/**
	 * Adds id token to localized script data.
	 *
	 * @param SdkClientToken  $api User id token api.
	 * @param LoggerInterface $logger The logger.
	 * @param array           $localized_script_data The localized script data.
	 * @return array
	 */
	private function add_sdk_client_token_to_script_data(
		SdkClientToken $api,
		LoggerInterface $logger,
		array $localized_script_data
	): array {
		try {
			$target_customer_id = '';
			if ( is_user_logged_in() ) {
				$target_customer_id = get_user_meta( get_current_user_id(), '_ppcp_target_customer_id', true );
				if ( ! $target_customer_id ) {
					$target_customer_id = get_user_meta( get_current_user_id(), 'ppcp_customer_id', true );
				}
			}

			$sdk_client_token             = $api->sdk_client_token( $target_customer_id );
			$localized_script_data['axo'] = array(
				'sdk_client_token' => $sdk_client_token,
			);

		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$logger->error( $error );
		}

		return $localized_script_data;
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
