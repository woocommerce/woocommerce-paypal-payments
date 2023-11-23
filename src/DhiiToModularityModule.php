<?php
/**
 * The wrapper module.
 *
 * @package WooCommerce\PayPalCommerce
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class WrapperModule
 */
class DhiiToModularityModule implements ServiceModule, ExtendingModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * The Dhii Modules.
	 *
	 * @var array|ModuleInterface[]
	 */
	private $modules;

	/**
	 * The services.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * The extensions.
	 *
	 * @var array
	 */
	private $extensions = array();

	/**
	 * The services
	 *
	 * @var bool
	 */
	private $is_initialized = false;

	/**
	 * @param array|ModuleInterface[] $modules
	 */
	public function __construct( array $modules ) {
		$this->modules = $modules;
	}

	private function setup(): void {
		if ( $this->is_initialized ) {
			return;
		}

		$this->services = array();
		$this->extensions = array();

		foreach ( $this->modules as $module ) {
			$service_provider = $module->setup();

			$this->services = array_merge(
				$this->services,
				$service_provider->getFactories()
			);

			foreach ( $service_provider->getExtensions() as $key => $extension ) {
				if ( ! isset( $this->extensions[ $key ] ) ) {
					$this->extensions[ $key ] = array();
				}
				$this->extensions[ $key ][] = $extension;
			}
		}

		$this->is_initialized = true;
	}

	/**
	 * Returns the services.
	 *
	 * @return array|callable[]
	 */
	public function services(): array {
		$this->setup();
		return $this->services;
	}

	/**
	 * Returns the extensions.
	 *
	 * @return array|callable[]
	 */
	public function extensions(): array {
		$this->setup();

		$map = array_map( function ( $extension_group ) {
			return function ( $previous, ContainerInterface $container ) use ( $extension_group ) {
				$value = $previous;
				foreach ( $extension_group as $extension ) {
					$value = $extension( $container, $value );
				}
				return $value;
			};
		}, $this->extensions );

		return $map;
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $container ): bool {
		foreach ( $this->modules as $module ) {
			$module->run( $container );
		}
		return true;
	}

}
