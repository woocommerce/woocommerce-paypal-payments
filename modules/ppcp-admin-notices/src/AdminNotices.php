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
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class AdminNotices
 */
class AdminNotices implements ServiceModule, ExtendingModule, ExecutableModule {
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
	public function extensions(): array {
		return require __DIR__ . '/../extensions.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): bool {
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

		return true;
	}
}
