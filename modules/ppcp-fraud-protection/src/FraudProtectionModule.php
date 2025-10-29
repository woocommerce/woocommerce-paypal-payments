<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

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
				$integration = $container->get( 'fraud-protection.recaptcha.integration' );
				assert( $integration instanceof RecaptchaIntegration );

				$integrations[] = $integration;
				return $integrations;
			}
		);
	}
}
