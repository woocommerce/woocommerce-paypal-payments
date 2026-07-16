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

use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\UpdateShippingEndpoint;
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
		add_action(
			'wc_ajax_' . ChangeCartEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'order-endpoints.endpoint.change-cart' );
				/**
				 * The Change Cart Endpoint.
				 *
				 * @var ChangeCartEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . ApproveOrderEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'order-endpoints.endpoint.approve-order' );
				/**
				 * The Approve Order Endpoint.
				 *
				 * @var ApproveOrderEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . CreateOrderEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'order-endpoints.endpoint.create-order' );
				/**
				 * The Create Order Endpoint.
				 *
				 * @var CreateOrderEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . UpdateShippingEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'order-endpoints.endpoint.update-shipping' );
				assert( $endpoint instanceof UpdateShippingEndpoint );

				$endpoint->handle_request();
			}
		);

		return true;
	}
}
