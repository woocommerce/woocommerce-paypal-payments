<?php
/**
 * The button module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button;

use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveSubscriptionEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\SaveCheckoutFormEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\SimulateCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ValidateCheckoutEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\StartPayPalVaultingEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class ButtonModule
 */
class ButtonModule implements ModuleInterface {


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
			'wp',
			static function () use ( $c ) {
				if ( is_admin() ) {
					return;
				}
				$smart_button = $c->get( 'button.smart-button' );
				/**
				 * The Smart Button.
				 *
				 * @var SmartButtonInterface $smart_button
				 */
				$smart_button->render_wrapper();
			}
		);
		add_action(
			'wp_enqueue_scripts',
			static function () use ( $c ) {
				$smart_button = $c->get( 'button.smart-button' );
				assert( $smart_button instanceof SmartButtonInterface );

				if ( $smart_button->should_load_ppcp_script() ) {
					$smart_button->enqueue();
				}
			}
		);

		add_filter(
			'woocommerce_create_order',
			static function ( $value ) use ( $c ) {
				$early_order_handler = $c->get( 'button.helper.early-order-handler' );
				if ( ! is_null( $value ) ) {
					$value = (int) $value;
				}
				/**
				 * The Early Order Handler
				 *
				 * @var EarlyOrderHandler $early_order_handler
				 */
				return $early_order_handler->determine_wc_order_id( $value );
			}
		);

		$this->register_ajax_endpoints( $c );
	}

	/**
	 * Registers the Ajax Endpoints.
	 *
	 * @param ContainerInterface $container The Container.
	 */
	private function register_ajax_endpoints( ContainerInterface $container ): void {
		add_action(
			'wc_ajax_' . DataClientIdEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.data-client-id' );
				/**
				 * The Data Client ID Endpoint.
				 *
				 * @var DataClientIdEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);
		add_action(
			'wc_ajax_' . StartPayPalVaultingEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.vault-paypal' );
				assert( $endpoint instanceof StartPayPalVaultingEndpoint );

				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . SimulateCartEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.simulate-cart' );
				/**
				 * The Simulate Cart Endpoint.
				 *
				 * @var SimulateCartEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . ChangeCartEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.change-cart' );
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
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.approve-order' );
				/**
				 * The Approve Order Endpoint.
				 *
				 * @var ApproveOrderEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . ApproveSubscriptionEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.approve-subscription' );
				assert( $endpoint instanceof ApproveSubscriptionEndpoint );

				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . CreateOrderEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.create-order' );
				/**
				 * The Create Order Endpoint.
				 *
				 * @var CreateOrderEndpoint $endpoint
				 */
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . SaveCheckoutFormEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.save-checkout-form' );
				assert( $endpoint instanceof SaveCheckoutFormEndpoint );

				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . ValidateCheckoutEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.validate-checkout' );
				assert( $endpoint instanceof ValidateCheckoutEndpoint );
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . CartScriptParamsEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.cart-script-params' );
				assert( $endpoint instanceof CartScriptParamsEndpoint );
				$endpoint->handle_request();
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
