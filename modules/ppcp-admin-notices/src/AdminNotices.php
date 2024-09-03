<?php
/**
 * The admin notice module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices;

use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Endpoint\MuteMessageEndpoint;
use WooCommerce\PayPalCommerce\AdminNotices\Renderer\RendererInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\PersistentMessage;

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
		$renderer = $c->get( 'admin-notices.renderer' );
		assert( $renderer instanceof RendererInterface );

		add_action(
			'admin_notices',
			function() use ( $renderer ) {
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

		/**
		 * Since admin notices are rendered after the initial `admin_enqueue_scripts`
		 * action fires, we use the `admin_footer` hook to enqueue the optional assets
		 * for admin-notices in the page footer.
		 */
		add_action(
			'admin_footer',
			static function () use ( $renderer ) {
				$renderer->enqueue_admin();
			}
		);

		add_action(
			'wp_ajax_' . MuteMessageEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'admin-notices.mute-message-endpoint' );
				assert( $endpoint instanceof MuteMessageEndpoint );

				$endpoint->handle_request();
			}
		);

		add_action(
			'woocommerce_paypal_payments_uninstall',
			static function () {
				PersistentMessage::clear_all();
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
