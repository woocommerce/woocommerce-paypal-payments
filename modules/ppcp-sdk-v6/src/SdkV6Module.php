<?php
/**
 * The SDK v6 module.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\SdkV6\Assets\AddPaymentMethodManager;
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

	public function services(): array {
		return require __DIR__ . '/../services.php';
	}

	public function extensions(): array {
		return require __DIR__ . '/../extensions.php';
	}

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

				$add_payment_method_manager = $c->get( 'sdk-v6.add-payment-method-manager' );
				assert( $add_payment_method_manager instanceof AddPaymentMethodManager );

				$add_payment_method_manager->enqueue();
			}
		);

		// The v6 SDK renders the PayPal "save for later" button on the Add
		// Payment Method page, so the v5 add-payment-method script must not
		// also render it into the same container. The v5 card fields stay on
		// v5 for now, so the v5 script keeps loading — only its PayPal button
		// is suppressed. See the migration note in extensions.php.
		add_filter(
			'woocommerce_paypal_payments_add_payment_method_localized_script_data',
			static function ( array $data ) use ( $c ): array {
				$add_payment_method_manager = $c->get( 'sdk-v6.add-payment-method-manager' );
				assert( $add_payment_method_manager instanceof AddPaymentMethodManager );

				if ( $add_payment_method_manager->should_load_on_current_page() ) {
					$data['skip_paypal_button'] = true;
				}

				return $data;
			}
		);

		add_action(
			'wp',
			function () use ( $c ) {
				if ( is_admin() ) {
					return;
				}

				$manager = $c->get( 'sdk-v6.manager' );
				assert( $manager instanceof SdkV6Manager );

				$this->register_render_hooks( $manager );
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

	/**
	 * Registers the render hooks that output the button wrapper elements.
	 *
	 * Uses the same theme hooks as the v5 SmartButton so v6 buttons appear
	 * in the same locations.
	 *
	 * @param SdkV6Manager $manager The SDK v6 manager.
	 * @return void
	 */
	private function register_render_hooks( SdkV6Manager $manager ): void {
		$places = $manager->determine_render_places();

		if ( $places['product'] ) {
			/**
			 * The action name that the PayPal buttons use for rendering on the single product page.
			 * Shared with the v5 SmartButton so a single override relocates both stacks.
			 */
			$hook = (string) apply_filters(
				'woocommerce_paypal_payments_single_product_renderer_hook',
				'woocommerce_single_product_summary'
			);
			add_action( $hook, static fn() => $manager->render_wrapper(), 31 );
		}

		if ( $places['cart'] ) {
			/**
			 * The action name that the PayPal buttons use for rendering next to the cart's Proceed to Checkout button.
			 * Shared with the v5 SmartButton so a single override relocates both stacks.
			 */
			$hook = (string) apply_filters(
				'woocommerce_paypal_payments_proceed_to_checkout_button_renderer_hook',
				'woocommerce_proceed_to_checkout'
			);
			add_action(
				$hook,
				static function () use ( $manager ): void {
					if ( ! is_cart() ) {
						return;
					}
					$manager->render_wrapper();
				},
				20
			);
		}

		if ( $places['checkout'] ) {
			/**
			 * The action name that the PayPal buttons use for rendering on the checkout page.
			 * Shared with the v5 SmartButton so a single override relocates both stacks.
			 */
			$hook = (string) apply_filters(
				'woocommerce_paypal_payments_checkout_button_renderer_hook',
				'woocommerce_review_order_after_payment'
			);
			add_action( $hook, static fn() => $manager->render_wrapper() );
		}

		if ( $places['mini-cart'] ) {
			/**
			 * The action name that the PayPal buttons use for rendering in the mini-cart widget.
			 * Shared with the v5 SmartButton so a single override relocates both stacks.
			 */
			$hook = (string) apply_filters(
				'woocommerce_paypal_payments_mini_cart_button_renderer_hook',
				'woocommerce_widget_shopping_cart_after_buttons'
			);
			add_action( $hook, static fn() => $manager->render_mini_cart_wrapper(), 30 );
		}
	}
}
