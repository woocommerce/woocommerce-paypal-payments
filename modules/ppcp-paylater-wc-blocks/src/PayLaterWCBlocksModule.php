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
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayLaterWCBlocksModule
 */
class PayLaterWCBlocksModule implements ModuleInterface {
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
		$messages_apply = $c->get( 'button.helper.messages-apply' );
		assert( $messages_apply instanceof MessagesApply );

		if ( ! $messages_apply->for_country() ) {
			return;
		}

		$settings = $c->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		add_action(
			'woocommerce_blocks_loaded',
			function() use ( $c ): void {
				add_action(
					'woocommerce_blocks_checkout_block_registration',
					function( $integration_registry ) use ( $c ): void {
						$integration_registry->register(
							new PayLaterWCBlocksIntegration(
								$c->get( 'paylater-wc-blocks.url' ),
								$c->get( 'ppcp.asset-version' )
							)
						);
					}
				);
			}
		);

		add_action(
			'init',
			function () use ( $c, $settings ): void {
				$config_factory = $c->get( 'paylater-configurator.factory.config' );
				assert( $config_factory instanceof ConfigFactory );

				$script_handle = 'ppcp-cart-paylater-messages-block';

				wp_register_script(
					$script_handle,
					$c->get( 'paylater-wc-blocks.url' ) . '/assets/js/paylater-block.js',
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				wp_localize_script(
					$script_handle,
					'PcpCartPayLaterBlock',
					array(
						'ajax'                => array(
							'cart_script_params' => array(
								'endpoint' => \WC_AJAX::get_endpoint( CartScriptParamsEndpoint::ENDPOINT ),
							),
						),
						'config'              => $config_factory->from_settings( $settings ),
						'settingsUrl'         => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' ),
						'vaultingEnabled'     => $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' ),
						'placementEnabled'    => self::is_placement_enabled( $c->get( 'wcgateway.settings.status' ), 'cart' ),
						'payLaterSettingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-pay-later' ),
					)
				);

				$script_handle = 'ppcp-checkout-paylater-messages-block';

				wp_register_script(
					$script_handle,
					$c->get( 'paylater-wc-blocks.url' ) . '/assets/js/paylater-block.js',
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

				/**
				 * Cannot return false for this path.
				 *
				 * @psalm-suppress PossiblyFalseArgument
				 */
				register_block_type( dirname( realpath( __FILE__ ), 2 ) );
			},
			20
		);

		/**
		 * Registers slugs as block categories with WordPress.
		 */
		add_action(
			'block_categories_all',
			function( $categories ): array {
				return array_merge(
					$categories,
					array(
						array(
							'slug'  => 'ppcp-cart-paylater-messages-block',
							'title' => __( 'PayPal Cart Pay Later Messages Blocks', 'woocommerce-paypal-payments' ),
						),
						array(
							'slug'  => 'ppcp-checkout-paylater-messages-block',
							'title' => __( 'PayPal Checkout Pay Later Messages Blocks', 'woocommerce-paypal-payments' ),
						),
					)
				);
			},
			10,
			2
		);

		/**
		 * Cannot return false for this path.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		register_block_type(
			dirname( realpath( __FILE__ ), 2 ) . '/resources/js/CartPayLaterMessagesBlock',
			array(
				'render_callback' => function ( $attributes ) use ( $c ) {
					$renderer = $c->get( 'paylater-wc-blocks.renderer' );
					ob_start();
                    // phpcs:ignore -- No need to escape it, the PayLaterWCBlocksRenderer class is responsible for escaping.
					echo $renderer->render(
                        // phpcs:ignore
						$attributes,
						'cart',
                        // phpcs:ignore
						$c
					);
					return ob_get_clean();
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
				'render_callback' => function ( $attributes ) use ( $c ) {
					$renderer = $c->get( 'paylater-wc-blocks.renderer' );
					ob_start();
                    // phpcs:ignore -- No need to escape it, the PayLaterWCBlocksRenderer class is responsible for escaping.
					echo $renderer->render(
                        // phpcs:ignore
						$attributes,
						'checkout',
                        // phpcs:ignore
						$c
					);
					return ob_get_clean();
				},
			)
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return void
	 */
	public function getKey() {
	}
}
