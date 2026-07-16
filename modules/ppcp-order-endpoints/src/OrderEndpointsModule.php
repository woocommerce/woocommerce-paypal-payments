<?php
/**
 * The order endpoints module.
 *
 * Home of the WC-AJAX order endpoints shared by the v5 and v6 SDK frontends
 * (ppc-create-order, ppc-approve-order, ppc-change-cart, ppc-update-shipping).
 *
 * @package WooCommerce\PayPalCommerce\OrderEndpoints
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class OrderEndpointsModule
 */
class OrderEndpointsModule implements ServiceModule, ExecutableModule {
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
	public function run( ContainerInterface $c ): bool {
		return true;
	}
}
