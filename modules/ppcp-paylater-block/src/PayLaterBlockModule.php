<?php
/**
 * The Pay Later block module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterBlock
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterBlock;

use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayLaterBlockModule
 */
class PayLaterBlockModule implements ModuleInterface {
	/**
	 * Returns whether the block module should be loaded.
	 */
	public static function is_module_loading_required(): bool {
		return apply_filters(
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'woocommerce.feature-flags.woocommerce_paypal_payments.paylater_block_enabled',
			getenv( 'PCP_PAYLATER_BLOCK' ) !== '0'
		);
	}

	/**
	 * Returns whether the block is enabled.
	 *
	 * @param SettingsStatus $settings_status The Settings status helper.
	 * @return bool true if the block is enabled, otherwise false.
	 */
	public static function is_block_enabled( SettingsStatus $settings_status ): bool {
		return self::is_module_loading_required() && $settings_status->is_pay_later_messaging_enabled_for_location( 'custom_placement' );
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
			'init',
			function () use ( $c, $settings ): void {
				$script_handle = 'ppcp-paylater-block';
				wp_register_script(
					$script_handle,
					$c->get( 'paylater-block.url' ) . '/assets/js/paylater-block.js',
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);
				wp_localize_script(
					$script_handle,
					'PcpPayLaterBlock',
					array(
						'ajax'                => array(
							'cart_script_params' => array(
								'endpoint' => \WC_AJAX::get_endpoint( CartScriptParamsEndpoint::ENDPOINT ),
							),
						),
						'settingsUrl'         => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' ),
						'vaultingEnabled'     => $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' ),
						'placementEnabled'    => self::is_block_enabled( $c->get( 'wcgateway.settings.status' ) ),
						'payLaterSettingsUrl' => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-pay-later' ),
					)
				);

				/**
				 * Cannot return false for this path.
				 *
				 * @psalm-suppress PossiblyFalseArgument
				 */
				register_block_type(
					dirname( realpath( __FILE__ ), 2 ),
					array(
						'render_callback' => function ( array $attributes ) use ( $c ) {
							$renderer = $c->get( 'paylater-block.renderer' );
							ob_start();
							// phpcs:ignore -- No need to escape it, the PayLaterBlockRenderer class is responsible for escaping.
							echo $renderer->render(
								// phpcs:ignore
								$attributes,
								// phpcs:ignore
								$c
							);
							return ob_get_clean();
						},
					)
				);
			},
			20
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
