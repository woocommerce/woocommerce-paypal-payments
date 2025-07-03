<?php
/**
 * The Pay Later WooCommerce Blocks module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayLaterWCBlocksModule
 */
class PayLaterWCBlocksModule implements ServiceModule, ExtendingModule, ExecutableModule {
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
	 * Returns whether the block module should be loaded.
	 *
	 * @return bool true if the module should be loaded, otherwise false.
	 */
	public static function is_module_loading_required(): bool {
		return apply_filters(
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'woocommerce.feature-flags.woocommerce_paypal_payments.paylater_wc_blocks_enabled',
			getenv( 'PCP_PAYLATER_WC_BLOCKS' ) !== '0'
		);
	}

	/**
	 * Returns whether the block is enabled.
	 *
	 * @param SettingsStatus $settings_status The Settings status helper.
	 * @param string         $location The location to check.
	 * @return bool true if the block is enabled, otherwise false.
	 */
	public static function is_block_enabled( SettingsStatus $settings_status, string $location ): bool {
		return self::is_module_loading_required() && $settings_status->is_pay_later_messaging_enabled_for_location( $location );
	}

	/**
	 * Returns whether the placement is enabled.
	 *
	 * @param SettingsStatus $settings_status The Settings status helper.
	 * @param string         $location The location to check.
	 * @return bool true if the placement is enabled, otherwise false.
	 */
	public static function is_placement_enabled( SettingsStatus $settings_status, string $location ) : bool {
		return self::is_block_enabled( $settings_status, $location );
	}

	/**
	 * Returns whether the under cart totals placement is enabled.
	 *
	 * @return bool true if the under cart totals placement is enabled, otherwise false.
	 */
	public function is_under_cart_totals_placement_enabled() : bool {
		return apply_filters(
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'woocommerce.feature-flags.woocommerce_paypal_payments.paylater_wc_blocks_cart_under_totals_enabled',
			true
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): bool {
		$messages_apply = $c->get( 'button.helper.messages-apply' );
		assert( $messages_apply instanceof MessagesApply );

		if ( ! $messages_apply->for_country() ) {
			return true;
		}

		add_action(
			'init',
			function () use ( $c ): void {
				$settings = $c->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );
				$config_factory = $c->get( 'paylater-configurator.factory.config' );
				assert( $config_factory instanceof ConfigFactory );

				$script_handle = 'ppcp-cart-paylater-block';

				wp_register_script(
					$script_handle,
					$c->get( 'paylater-wc-blocks.url' ) . 'assets/js/cart-paylater-block.js',
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				wp_localize_script(
					$script_handle,
					'PcpCartPayLaterBlock',
					array(
						'ajax'                        => array(
							'cart_script_params' => array(
								'endpoint' => \WC_AJAX::get_endpoint( CartScriptParamsEndpoint::ENDPOINT ),
							),
						),
						'config'                      => $config_factory->from_settings( $settings ),
						'settingsUrl'                 => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' ),
						'vaultingEnabled'             => $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' ),
						'placementEnabled'            => self::is_placement_enabled( $c->get( 'wcgateway.settings.status' ), 'cart' ),
						'payLaterSettingsUrl'         => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-pay-later' ),
						'underTotalsPlacementEnabled' => self::is_under_cart_totals_placement_enabled(),
					)
				);

				$script_handle = 'ppcp-checkout-paylater-block';

				wp_register_script(
					$script_handle,
					$c->get( 'paylater-wc-blocks.url' ) . 'assets/js/checkout-paylater-block.js',
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				wp_localize_script(
					$script_handle,
					'PcpCheckoutPayLaterBlock',
					array(
						'ajax'                => array(
							'cart_script_params' => array(
								'endpoint' => \WC_AJAX::get_endpoint( CartScriptParamsEndpoint::ENDPOINT ),
							),
						),
						'config'              => $config_factory->from_settings( $settings ),
						'settingsUrl'         => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' ),
						'vaultingEnabled'     => $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' ),
						'placementEnabled'    => self::is_placement_enabled( $c->get( 'wcgateway.settings.status' ), 'checkout' ),
						'payLaterSettingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-pay-later' ),
					)
				);
			},
			20
		);

		/**
		 * Registers slugs as block categories with WordPress.
		 */
		add_action(
			'block_categories_all',
			function ( array $categories ): array {
				return array_merge(
					$categories,
					array(
						array(
							'slug'  => 'woocommerce-paypal-payments',
							'title' => __( 'PayPal Blocks', 'woocommerce-paypal-payments' ),
						),
					)
				);
			},
			10,
			2
		);

		add_action(
			'init',
			function () use ( $c ): void {
				if ( ! function_exists( 'register_block_type' ) ) {
					return;
				}

				/**
				 * Cannot return false for this path.
				 *
				 * @psalm-suppress PossiblyFalseArgument
				 */
				register_block_type(
					dirname( realpath( __FILE__ ), 2 ) . '/resources/js/CartPayLaterMessagesBlock',
					array(
						'render_callback' => function ( array $attributes ) use ( $c ) {
							return PayLaterWCBlocksUtils::render_paylater_block(
								$attributes['blockId'] ?? 'woocommerce-paypal-payments/cart-paylater-messages',
								$attributes['ppcpId'] ?? 'ppcp-cart-paylater-messages',
								'cart',
								$c
							);
						},
					)
				);

				/**
				 * Cannot return false for this path.
				 *
				 * @psalm-suppress PossiblyFalseArgument
				 */
				register_block_type(
					dirname( realpath( __FILE__ ), 2 ) . '/resources/js/CheckoutPayLaterMessagesBlock',
					array(
						'render_callback' => function ( array $attributes ) use ( $c ) {
							return PayLaterWCBlocksUtils::render_paylater_block(
								$attributes['blockId'] ?? 'woocommerce-paypal-payments/checkout-paylater-messages',
								$attributes['ppcpId'] ?? 'ppcp-checkout-paylater-messages',
								'checkout',
								$c
							);
						},
					)
				);
			}
		);

		// This is a fallback for the default Cart block that haven't been saved with the inserted Pay Later messaging block.
		add_filter(
			'render_block_woocommerce/cart-totals-block',
			function ( string $block_content ) use ( $c ) {
				if ( false === strpos( $block_content, 'woocommerce-paypal-payments/cart-paylater-messages' ) ) {
					return PayLaterWCBlocksUtils::render_and_insert_paylater_block(
						$block_content,
						'woocommerce-paypal-payments/cart-paylater-messages',
						'ppcp-cart-paylater-messages',
						'cart',
						$c,
						self::is_under_cart_totals_placement_enabled()
					);
				}
				return $block_content;
			},
			10,
			1
		);

		// This is a fallback for the default Checkout block that haven't been saved with the inserted Checkout - Pay Later messaging block.
		add_filter(
			'render_block_woocommerce/checkout-totals-block',
			function ( string $block_content ) use ( $c ) {
				if ( false === strpos( $block_content, 'woocommerce-paypal-payments/checkout-paylater-messages' ) ) {
					return PayLaterWCBlocksUtils::render_and_insert_paylater_block(
						$block_content,
						'woocommerce-paypal-payments/checkout-paylater-messages',
						'ppcp-checkout-paylater-messages',
						'checkout',
						$c
					);
				}
				return $block_content;
			},
			10,
			1
		);

		// Since there's no regular way we can place the Pay Later messaging block under the cart totals block, we need a custom script.
		if ( self::is_under_cart_totals_placement_enabled() ) {
			add_action(
				'enqueue_block_editor_assets',
				function () use ( $c ): void {
					$handle = 'ppcp-checkout-paylater-block-editor-inserter';
					$path   = $c->get( 'paylater-wc-blocks.url' ) . 'assets/js/cart-paylater-block-inserter.js';

					wp_register_script(
						$handle,
						$path,
						array( 'wp-blocks', 'wp-data', 'wp-element' ),
						$c->get( 'ppcp.asset-version' ),
						true
					);

					wp_enqueue_script( $handle );
				}
			);
		}
		return true;
	}
}
