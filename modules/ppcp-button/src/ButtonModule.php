<?php
/**
 * The button module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ReturnUrlFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveSubscriptionEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\SaveCheckoutFormEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\SimulateCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ValidateCheckoutEndpoint;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\GetOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\StartPayPalVaultingEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class ButtonModule
 */
class ButtonModule implements ServiceModule, ExtendingModule, ExecutableModule {
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

		$this->register_appswitch_crossbrowser_handler( $c );

		return true;
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

		add_action(
			'wc_ajax_' . GetOrderEndpoint::ENDPOINT,
			static function () use ( $container ) {
				$endpoint = $container->get( 'button.endpoint.get-order' );
				assert( $endpoint instanceof GetOrderEndpoint );
				$endpoint->handle_request();
			}
		);
	}

	private function register_appswitch_crossbrowser_handler( ContainerInterface $container ): void {
		if ( ! $container->get( 'wcgateway.appswitch-enabled' ) ) {
			return;
		}

		// After returning from cross-browser AppSwitch (started in non-default browser, then redirected to the default one)
		// we need to retrieve the saved cart and PayPal order, create a WC order and redirect to Pay for order.
		add_action(
			'wp',
			static function () use ( $container ) {
				// phpcs:ignore WordPress.Security.NonceVerification
				if ( ! isset( $_GET[ ReturnUrlFactory::PCP_QUERY_ARG ] ) ) {
					return;
				}

				if ( is_checkout_pay_page() ) {
					return;
				}

				// phpcs:ignore WordPress.Security.NonceVerification
				if ( ! isset( $_GET[ CreateOrderEndpoint::RETURN_URL_CART_QUERY_ARG ] ) ) {
					return;
				}

				// phpcs:ignore WordPress.Security.NonceVerification
				$cart_key = wc_clean( wp_unslash( $_GET[ CreateOrderEndpoint::RETURN_URL_CART_QUERY_ARG ] ) );
				if ( ! is_string( $cart_key ) ) {
					return;
				}

				$card_data_storage = $container->get( 'button.session.storage.card-data.transient' );
				assert( $card_data_storage instanceof CartDataTransientStorage );

				$cart_data = $card_data_storage->get( $cart_key );
				if ( ! $cart_data ) {
					return;
				}

				// Delete the data to avoid accidentally triggering it again, duplicating orders etc.
				$card_data_storage->remove( $cart_data );

				if ( ! WC()->cart ) {
					return;
				}
				// The current cart is the same, so we don't need to do anything (probably not cross-browser).
				if ( WC()->cart->get_cart_hash() === $cart_data->cart_hash() ) {
					return;
				}

				$paypal_order_id = $cart_data->paypal_order_id();
				if ( empty( $paypal_order_id ) ) {
					return;
				}

				$order_endpoint = $container->get( 'api.endpoint.order' );
				assert( $order_endpoint instanceof OrderEndpoint );

				$paypal_order = $order_endpoint->order( $paypal_order_id );

				$wc_order_creator = $container->get( 'button.helper.wc-order-creator' );
				assert( $wc_order_creator instanceof WooCommerceOrderCreator );

				$wc_order = $wc_order_creator->create_from_paypal_order( $paypal_order, $cart_data );

				// Redirect via JS because we need to keep the # parameters which are not accessible on the server side.
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo "<script>location.href = '" . $wc_order->get_checkout_payment_url() . "' + location.hash;</script>";
			}
		);
	}
}
