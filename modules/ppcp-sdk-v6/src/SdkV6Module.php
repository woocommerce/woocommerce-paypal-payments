<?php
/**
 * The SDK v6 module.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\SdkV6\Blocks\V6PaymentMethod;
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

		// Register the v6 express buttons with the WC Blocks pipeline. v5's
		// PayPalPaymentMethod stays registered (it provides the ppcp-gateway
		// type and processing); on v6-owned block pages its script_data is
		// empty so it registers no express buttons, and v6 supplies them.
		//
		// Extends the v5 handoff (see extensions.php) to the block wallet
		// methods. On v6-owned block pages the Google Pay and Apple Pay block
		// boots read v5's now-empty PayPal config and throw during React
		// render, which tears down the whole checkout block (v6 buttons
		// included) instead of failing quietly. The registration action fires
		// on init (priority 5), before is_checkout()/is_cart() resolve, so the
		// page context is unknown here; capture the registry and defer the
		// suppression to wp_enqueue_scripts, where the context is known and
		// the block scripts are not yet enqueued. The wallets migrate under
		// their own story (PCP-5782).
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( PaymentMethodRegistry $payment_method_registry ) use ( $c ): void {
				$payment_method_registry->register( $c->get( 'sdk-v6.blocks.payment-method' ) );

				add_action(
					'wp_enqueue_scripts',
					function () use ( $c, $payment_method_registry ): void {
						$manager = $c->get( 'sdk-v6.manager' );
						assert( $manager instanceof SdkV6Manager );

						if ( ! $manager->should_load_on_current_page() || ! $manager->is_block_context() ) {
							return;
						}

						foreach ( array( 'ppcp-googlepay', 'ppcp-applepay' ) as $wallet_method ) {
							if ( $payment_method_registry->is_registered( $wallet_method ) ) {
								$payment_method_registry->unregister( $wallet_method );
							}
						}
					},
					5
				);
			}
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
