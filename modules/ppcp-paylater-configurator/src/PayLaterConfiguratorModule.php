<?php
/**
 * The Pay Later configurator module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\GetConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;

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
	public function run( ContainerInterface $c ) : void {
		$is_available = $c->get( 'paylater-configurator.is-available' );

		if ( ! $is_available ) {
			return;
		}

		$current_page_id     = $c->get( 'wcgateway.current-ppcp-settings-page-id' );
		$is_wc_settings_page = $c->get( 'wcgateway.is-wc-settings-page' );
		$messaging_locations = $c->get( 'paylater-configurator.messaging-locations' );

		$this->add_paylater_update_notice( $messaging_locations, $is_wc_settings_page, $current_page_id );

		$settings = $c->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		add_action(
			'wc_ajax_' . SaveConfig::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'paylater-configurator.endpoint.save-config' );
				assert( $endpoint instanceof SaveConfig );
				$endpoint->handle_request();
			}
		);

		add_action(
			'wc_ajax_' . GetConfig::ENDPOINT,
			static function () use ( $c ) {
				$endpoint = $c->get( 'paylater-configurator.endpoint.get-config' );
				assert( $endpoint instanceof GetConfig );
				$endpoint->handle_request();
			}
		);

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

				$bn_code = PPCP_PAYPAL_BN_CODE;

				wp_localize_script(
					'ppcp-paylater-configurator',
					'PcpPayLaterConfigurator',
					array(
						'ajax'                   => array(
							'save_config' => array(
								'endpoint' => \WC_AJAX::get_endpoint( SaveConfig::ENDPOINT ),
								'nonce'    => wp_create_nonce( SaveConfig::nonce() ),
							),
							'get_config'  => array(
								'endpoint' => \WC_AJAX::get_endpoint( GetConfig::ENDPOINT ),
								'nonce'    => wp_create_nonce( GetConfig::nonce() ),
							),
						),
						'config'                 => $config_factory->from_settings( $settings ),
						'merchantClientId'       => $settings->get( 'client_id' ),
						'partnerClientId'        => $c->get( 'api.partner_merchant_id' ),
						'bnCode'                 => $bn_code,
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

	/**
	 * Conditionally registers a new admin notice to highlight the new Pay-Later UI.
	 *
	 * The notice appears on any PayPal-Settings page, except for the Pay-Later settings page,
	 * when no Pay-Later messaging is used yet.
	 *
	 * @param array  $message_locations PayLater messaging locations.
	 * @param bool   $is_settings_page  Whether the current page is a WC settings page.
	 * @param string $current_page_id   ID of current settings page tab.
	 *
	 * @return void
	 */
	private function add_paylater_update_notice( array $message_locations, bool $is_settings_page, string $current_page_id ) : void {
		if ( ! $is_settings_page || Settings::PAY_LATER_TAB_ID === $current_page_id ) {
			return;
		}

		// Don't display the notice when Pay-Later messaging is already used.
		if ( count( $message_locations ) ) {
			return;
		}

		add_filter(
			Repository::NOTICES_FILTER,
			/**
			 * Notify the user about the new Pay-Later UI.
			 *
			 * @param array $notices The notices.
			 * @return array
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $notices ) : array {
				$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-pay-later' );

				$message = sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag directing to the Pay-Later settings page.
					__(
						'<strong>NEW</strong>: Check out the recently revamped %1$sPayPal Pay Later messaging experience here%2$s. Get paid in full at checkout while giving your customers the flexibility to pay in installments over time. Pay Later messaging can be placed anywhere on your site to inform buyers about available installment offers. ',
						'woocommerce-paypal-payments'
					),
					'<a href="' . esc_url( $settings_url ) . '">',
					'</a>'
				);

				$notices[] = new Message(
					$message,
					'info',
					true,
					'ppcp-notice-wrapper'
				);

				return $notices;
			}
		);
	}
}
