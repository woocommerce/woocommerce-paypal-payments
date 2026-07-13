<?php
/**
 * The SDK v6 module.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class SdkV6Module
 */
class SdkV6Module implements ServiceModule, ExtendingModule, ExecutableModule {
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
			'wc_ajax_' . ClientTokenEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'sdk-v6.endpoint.client-token' );
				assert( $endpoint instanceof ClientTokenEndpoint );

				$endpoint->handle_request();
			}
		);

		add_action(
			'wp_enqueue_scripts',
			static function () use ( $c ) {
				$manager = $c->get( 'sdk-v6.manager' );
				assert( $manager instanceof SdkV6Manager );

				$manager->enqueue();
			}
		);

		add_action(
			'wp',
			static function () use ( $c ) {
				if ( is_admin() ) {
					return;
				}

				$manager = $c->get( 'sdk-v6.manager' );
				assert( $manager instanceof SdkV6Manager );

				$manager->register_render_hooks();
			}
		);

		// Store the created PayPal order in the WC session for v6 requests,
		// so the shipping-update endpoint can validate order ownership in
		// non-checkout contexts (v5 only stores it for checkout).
		add_action(
			'woocommerce_paypal_payments_create_order_endpoint_order_created',
			static function ( Order $order, array $data ) use ( $c ) {
				if ( empty( $data['save_order_in_session'] ) ) {
					return;
				}

				$session_handler = $c->get( 'session.handler' );
				assert( $session_handler instanceof SessionHandler );

				$session_handler->replace_order( $order );
			},
			10,
			2
		);

		return true;
	}
}
