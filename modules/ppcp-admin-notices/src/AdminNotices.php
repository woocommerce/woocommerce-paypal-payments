<?php
/**
 * The admin notice module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AdminNotices
 */
class AdminNotices implements ModuleInterface {

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
		add_action(
			'admin_notices',
			function() use ( $c ) {
				$renderer = $c->get( 'admin-notices.renderer' );
				$renderer->render();
			}
		);

		add_action(
			Repository::NOTICES_FILTER,
			/**
			 * Adds persisted notices to the notices array.
			 *
			 * @param array $notices The notices.
			 * @return array
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $notices ) use ( $c ) {
				if ( ! is_array( $notices ) ) {
					return $notices;
				}

				$admin_notices = $c->get( 'admin-notices.repository' );
				assert( $admin_notices instanceof Repository );

				$persisted_notices = $admin_notices->get_persisted_and_clear();

				if ( $persisted_notices ) {
					$notices = array_merge( $notices, $persisted_notices );
				}

				return $notices;
			}
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
