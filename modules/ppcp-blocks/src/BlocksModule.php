<?php
/**
 * The blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

/**
 * Class BlocksModule
 */
class BlocksModule implements ServiceModule, ExtendingModule, ExecutableModule {
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
		if (
			! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' )
			|| ! function_exists( 'woocommerce_store_api_register_payment_requirements' )
		) {
			add_action(
				'admin_notices',
				function () {
					printf(
						'<div class="notice notice-error"><p>%1$s</p></div>',
						wp_kses_post(
							__(
								'PayPal checkout block initialization failed, possibly old WooCommerce version or disabled WooCommerce Blocks plugin.',
								'woocommerce-paypal-payments'
							)
						)
					);
				}
			);

			return true;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) use ( $c ): void {
				$payment_method_registry->register( $c->get( 'blocks.method' ) );

				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				$payment_method_registry->register( $c->get( 'blocks.advanced-card-method' ) );
			}
		);

		woocommerce_store_api_register_payment_requirements(
			array(
				'data_callback' => function() use ( $c ): array {
					$smart_button = $c->get( 'button.smart-button' );
					assert( $smart_button instanceof SmartButtonInterface );

					if ( isset( $smart_button->script_data()['continuation'] ) ) {
						return array( 'ppcp_continuation' );
					}

					return array();
				},
			)
		);

		add_action(
			'wc_ajax_' . UpdateShippingEndpoint::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'blocks.endpoint.update-shipping' );
				assert( $endpoint instanceof UpdateShippingEndpoint );

				$endpoint->handle_request();
			}
		);

		// Enqueue frontend scripts.
		add_action(
			'wp_enqueue_scripts',
			static function () use ( $c ) {
				if ( ! has_block( 'woocommerce/checkout' ) && ! has_block( 'woocommerce/cart' ) ) {
					return;
				}

				$module_url    = $c->get( 'blocks.url' );
				$asset_version = $c->get( 'ppcp.asset-version' );

				wp_register_style(
					'wc-ppcp-blocks',
					untrailingslashit( $module_url ) . '/assets/css/gateway.css',
					array(),
					$asset_version
				);
				wp_enqueue_style( 'wc-ppcp-blocks' );
			}
		);

		// Enqueue editor styles.
		add_action(
			'enqueue_block_editor_assets',
			static function () use ( $c ) {
				$module_url    = $c->get( 'blocks.url' );
				$asset_version = $c->get( 'ppcp.asset-version' );

				wp_register_style(
					'wc-ppcp-blocks-editor',
					untrailingslashit( $module_url ) . '/assets/css/gateway-editor.css',
					array(),
					$asset_version
				);
				wp_enqueue_style( 'wc-ppcp-blocks-editor' );
			}
		);

		add_filter(
			'woocommerce_paypal_payments_sdk_components_hook',
			function( array $components, string $context ) {
				if ( str_ends_with( $context, '-block' ) ) {
					$components[] = 'buttons';
				}

				return $components;
			},
			10,
			2
		);
		return true;
	}
}
