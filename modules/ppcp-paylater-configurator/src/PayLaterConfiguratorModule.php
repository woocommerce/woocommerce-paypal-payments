<?php
/**
 * The Pay Later configurator module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class PayLaterConfiguratorModule
 */
class PayLaterConfiguratorModule implements ModuleInterface {
	/**
	 * Returns whether the module should be loaded.
	 */
	public static function is_enabled(): bool {
		return apply_filters(
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			'woocommerce.feature-flags.woocommerce_paypal_payments.paylater_configurator_enabled',
			getenv( 'PCP_PAYLATER_CONFIGURATOR' ) !== '0'
		);
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

		$settings = $c->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		$vault_enabled = $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' );

		if ( $vault_enabled || ! $messages_apply->for_country() ) {
			return;
		}

		add_action(
			'wc_ajax_' . SaveConfig::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'paylater-configurator.endpoint.save-config' );
				assert( $endpoint instanceof SaveConfig );
				$endpoint->handle_request();
			}
		);

		$current_page_id = $c->get( 'wcgateway.current-ppcp-settings-page-id' );

		if ( $current_page_id !== Settings::PAY_LATER_TAB_ID ) {
			return;
		}

		add_action(
			'init',
			static function () use ( $c, $settings ) {
				wp_enqueue_script(
					'ppcp-paylater-configurator-lib',
					'https://www.paypalobjects.com/merchant-library/merchant-configurator.js',
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				wp_enqueue_script(
					'ppcp-paylater-configurator',
					$c->get( 'paylater-configurator.url' ) . '/assets/js/paylater-configurator.js',
					array(),
					$c->get( 'ppcp.asset-version' ),
					true
				);

				wp_enqueue_style(
					'ppcp-paylater-configurator-style',
					$c->get( 'paylater-configurator.url' ) . '/assets/css/paylater-configurator.css',
					array(),
					$c->get( 'ppcp.asset-version' )
				);

				$config_factory = $c->get( 'paylater-configurator.factory.config' );
				assert( $config_factory instanceof ConfigFactory );

				wp_localize_script(
					'ppcp-paylater-configurator',
					'PcpPayLaterConfigurator',
					array(
						'ajax'                   => array(
							'save_config' => array(
								'endpoint' => \WC_AJAX::get_endpoint( SaveConfig::ENDPOINT ),
								'nonce'    => wp_create_nonce( SaveConfig::nonce() ),
							),
						),
						'config'                 => $config_factory->from_settings( $settings ),
						'merchantClientId'       => $settings->get( 'client_id' ),
						'partnerClientId'        => $c->get( 'api.partner_merchant_id' ),
						'publishButtonClassName' => 'ppcp-paylater-configurator-publishButton',
						'headerClassName'        => 'ppcp-paylater-configurator-header',
						'subheaderClassName'     => 'ppcp-paylater-configurator-subheader',
					)
				);
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
