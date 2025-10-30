<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\Recaptcha;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\RecaptchaIntegration;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

class FraudProtectionModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * {@inheritDoc}
	 */
	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $container ): bool {
		$this->init_recaptcha( $container );

		return true;
	}

	protected function init_recaptcha( ContainerInterface $container ): void {
		add_filter(
			'woocommerce_integrations',
			/**
			 * @param array $integrations
			 * @returns array
			 * @psalm-suppress MissingClosureParamType
			 * @psalm-suppress MissingClosureReturnType
			 */
			static function ( $integrations ) use ( $container ) {
				// WC always creates a new instance here.
				$integrations[] = RecaptchaIntegration::class;
				return $integrations;
			}
		);

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $container ): void {
				$recaptcha = $container->get( 'fraud-protection.recaptcha' );
				assert( $recaptcha instanceof Recaptcha );

				$recaptcha->enqueue_scripts();
			}
		);

		add_action(
			'woocommerce_paypal_payments_create_order_request_started',
			static function ( array $data ) use ( $container ): void {
				$recaptcha = $container->get( 'fraud-protection.recaptcha' );
				assert( $recaptcha instanceof Recaptcha );

				$recaptcha->intercept_paypal_ajax( $data );
			}
		);

		foreach ( array( 'woocommerce_checkout_process', 'woocommerce_before_pay_action' ) as $hook ) {
			add_action(
				$hook,
				static function () use ( $container ): void {
					$recaptcha = $container->get( 'fraud-protection.recaptcha' );
					assert( $recaptcha instanceof Recaptcha );

					$recaptcha->validate_classic_checkout();
				}
			);
		}
	}
}
