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

		// Lets the shipping-update endpoint validate order ownership in
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

		// While an approved order sits in the session the buyer must not be able
		// to pay by another means. v5's equivalent callback reads its suppressed
		// script_data and never reports continuation on v6 pages.
		if ( function_exists( 'woocommerce_store_api_register_payment_requirements' ) ) {
			woocommerce_store_api_register_payment_requirements(
				array(
					'data_callback' => static function () use ( $c ): array {
						$manager = $c->get( 'sdk-v6.manager' );
						assert( $manager instanceof SdkV6Manager );

						return $manager->is_continuation()
							? array( 'ppcp_continuation' )
							: array();
					},
				)
			);
		}

		// v5's PayPalPaymentMethod stays registered (it provides the
		// ppcp-gateway type and processing); on v6-owned block pages its
		// script_data is empty so it registers no express buttons.
		//
		// Extends the v5 handoff (see extensions.php) to the other v5 PayPal
		// block methods, which misbehave against v5's now-empty config: the
		// Google Pay / Apple Pay boots throw during React render, tearing down
		// the whole checkout block, and the Fastlane (AXO) field restoration
		// can clobber the express submission. The wallets and card fields
		// migrate under their own stories.
		//
		// The registration action fires on init (priority 5), before
		// is_checkout()/is_cart() resolve, so the page context is unknown here;
		// capture the registry and defer the suppression to wp_enqueue_scripts.
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

						// PayPal-owned block methods only; never third-party or
						// core gateways.
						$v5_methods = array(
							'ppcp-googlepay',
							'ppcp-applepay',
							'ppcp-axo-gateway',
						);

						// Suppress the v5 card block only when v6 renders its own
						// card method in its place, so cards stay payable when v6
						// does not.
						if ( $manager->is_card_fields_enabled() ) {
							$v5_methods[] = 'ppcp-credit-card-gateway';
						}

						// v6 renders the order review under this name too, and
						// registerPaymentMethod is a silent last-one-wins
						// assignment, so leaving both registered would make the
						// review surface depend on script order. Outside
						// continuation v5's place-order method is left alone: it
						// never loads the JS SDK, so it still works.
						if ( $manager->is_continuation() ) {
							$v5_methods[] = 'ppcp-gateway';
						}
						foreach ( $v5_methods as $method ) {
							if ( $payment_method_registry->is_registered( $method ) ) {
								$payment_method_registry->unregister( $method );
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

		if ( $places['pay-now'] ) {
			/**
			 * The action name that the PayPal buttons use for rendering on the pay-for-order page.
			 * Shared with the v5 SmartButton so a single override relocates both stacks.
			 */
			$hook = (string) apply_filters(
				'woocommerce_paypal_payments_pay_order_renderer_hook',
				'woocommerce_pay_order_after_submit'
			);
			add_action( $hook, static fn() => $manager->render_wrapper(), 20 );
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
