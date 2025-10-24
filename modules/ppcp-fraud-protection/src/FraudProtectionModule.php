<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

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
		$this->init_settings( $container );

		return true;
	}

	protected function init_settings( ContainerInterface $container ): void {
		add_filter(
			'woocommerce_get_sections_advanced',
			/**
			 * @param $sections array
			 * @returns array
			 * @psalm-suppress MissingClosureParamType
			 * @psalm-suppress MissingClosureReturnType
			 */
			function ( $sections ) use ( $container ) {
				$sections[ $container->get( 'fraud-protection.settings.section.id' ) ] = $container->get( 'fraud-protection.settings.section.title' );
				return $sections;
			}
		);

		add_filter(
			'woocommerce_get_settings_advanced',
			/**
			 * @param $settings array
			 * @param $current_section string
			 * @returns array
			 * @psalm-suppress MissingClosureParamType
			 * @psalm-suppress MissingClosureReturnType
			 */
			function ( $settings, $current_section ) use ( $container ) {
				if ( $current_section !== $container->get( 'fraud-protection.settings.section.id' ) ) {
					return $settings;
				}

				return $container->get( 'fraud-protection.settings.fields' );
			},
			10,
			2
		);
	}
}
