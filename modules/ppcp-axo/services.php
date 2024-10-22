<?php
/**
 * The Axo module services.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Assets\AxoManager;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Axo\Helper\ApmApplies;
use WooCommerce\PayPalCommerce\Axo\Helper\SettingsNoticeGenerator;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCGatewayConfiguration;

return array(

	// If AXO can be configured.
	'axo.eligible'                           => static function ( ContainerInterface $container ): bool {
		$apm_applies = $container->get( 'axo.helpers.apm-applies' );
		assert( $apm_applies instanceof ApmApplies );

		return $apm_applies->for_country_currency();
	},

	'axo.helpers.apm-applies'                => static function ( ContainerInterface $container ) : ApmApplies {
		return new ApmApplies(
			$container->get( 'axo.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency.getter' ),
			$container->get( 'api.shop.country' )
		);
	},

	'axo.helpers.settings-notice-generator'  => static function ( ContainerInterface $container ) : SettingsNoticeGenerator {
		return new SettingsNoticeGenerator( $container->get( 'axo.fastlane-incompatible-plugin-names' ) );
	},

	// If AXO is configured and onboarded.
	'axo.available'                          => static function ( ContainerInterface $container ): bool {
		return true;
	},

	'axo.url'                                => static function ( ContainerInterface $container ): string {
		$path = realpath( __FILE__ );
		if ( false === $path ) {
			return '';
		}
		return plugins_url(
			'/modules/ppcp-axo/',
			dirname( $path, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'axo.manager'                            => static function ( ContainerInterface $container ): AxoManager {
		return new AxoManager(
			$container->get( 'axo.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.settings.status' ),
			$container->get( 'api.shop.currency.getter' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'wcgateway.url' )
		);
	},

	'axo.gateway'                            => static function ( ContainerInterface $container ): AxoGateway {
		return new AxoGateway(
			$container->get( 'wcgateway.settings.render' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.configuration.dcc' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'wcgateway.credit-card-icons' ),
			$container->get( 'api.endpoint.order' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	/**
	 * The matrix which countries and currency combinations can be used for AXO.
	 */
	'axo.supported-country-currency-matrix'  => static function ( ContainerInterface $container ) : array {
		/**
		 * Returns which countries and currency combinations can be used for AXO.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_axo_supported_country_currency_matrix',
			array(
				'US' => array(
					'AUD',
					'CAD',
					'EUR',
					'GBP',
					'JPY',
					'USD',
				),
			)
		);
	},

	'axo.settings-conflict-notice'           => static function ( ContainerInterface $container ) : string {
		$settings_notice_generator = $container->get( 'axo.helpers.settings-notice-generator' );
		assert( $settings_notice_generator instanceof SettingsNoticeGenerator );

		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		return $settings_notice_generator->generate_settings_conflict_notice( $settings );
	},

	'axo.checkout-config-notice'             => static function ( ContainerInterface $container ) : string {
		$settings_notice_generator = $container->get( 'axo.helpers.settings-notice-generator' );
		assert( $settings_notice_generator instanceof SettingsNoticeGenerator );

		return $settings_notice_generator->generate_checkout_notice();
	},

	'axo.incompatible-plugins-notice'        => static function ( ContainerInterface $container ) : string {
		$settings_notice_generator = $container->get( 'axo.helpers.settings-notice-generator' );
		assert( $settings_notice_generator instanceof SettingsNoticeGenerator );

		return $settings_notice_generator->generate_incompatible_plugins_notice();
	},

	'axo.smart-button-location-notice'       => static function ( ContainerInterface $container ) : string {
		$dcc_configuration = $container->get( 'wcgateway.configuration.dcc' );
		assert( $dcc_configuration instanceof DCCGatewayConfiguration );

		if ( $dcc_configuration->use_fastlane() ) {
			$fastlane_settings_url = admin_url(
				sprintf(
					'admin.php?page=wc-settings&tab=checkout&section=%1$s&ppcp-tab=%2$s#field-axo_heading',
					PayPalGateway::ID,
					CreditCardGateway::ID
				)
			);

			$notice_content = sprintf(
			/* translators: %1$s: URL to the Checkout edit page. */
				__(
					'<span class="highlight">Important:</span> The <code>Cart</code> & <code>Classic Cart</code> <strong>Smart Button Locations</strong> cannot be disabled while <a href="%1$s">Fastlane</a> is active.',
					'woocommerce-paypal-payments'
				),
				esc_url( $fastlane_settings_url )
			);
		} else {
			return '';
		}

		return '<div class="ppcp-notice ppcp-notice-warning"><p>' . $notice_content . '</p></div>';
	},

	'axo.endpoint.frontend-logger'           => static function ( ContainerInterface $container ): FrontendLoggerEndpoint {
		return new FrontendLoggerEndpoint(
			$container->get( 'button.request-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	/**
	 * The list of Fastlane incompatible plugins.
	 *
	 * @returns array<array{name: string, is_active: bool}>
	 */
	'axo.fastlane-incompatible-plugins'      => static function () : array {
		/**
		 * Filters the list of Fastlane incompatible plugins.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_fastlane_incompatible_plugins',
			array(
				array(
					'name'      => 'Elementor',
					'is_active' => did_action( 'elementor/loaded' ),
				),
				array(
					'name'      => 'CheckoutWC',
					'is_active' => defined( 'CFW_NAME' ),
				),
				array(
					'name'      => 'Direct Checkout for WooCommerce',
					'is_active' => defined( 'QLWCDC_PLUGIN_NAME' ),
				),
				array(
					'name'      => 'Multi-Step Checkout for WooCommerce',
					'is_active' => class_exists( 'WPMultiStepCheckout' ),
				),
				array(
					'name'      => 'Fluid Checkout for WooCommerce',
					'is_active' => class_exists( 'FluidCheckout' ),
				),
				array(
					'name'      => 'MultiStep Checkout for WooCommerce',
					'is_active' => class_exists( 'THWMSCF_Multistep_Checkout' ),
				),
				array(
					'name'      => 'WooCommerce Subscriptions',
					'is_active' => class_exists( 'WC_Subscriptions' ),
				),
				array(
					'name'      => 'CartFlows',
					'is_active' => class_exists( 'Cartflows_Loader' ),
				),
				array(
					'name'      => 'FunnelKit Funnel Builder',
					'is_active' => class_exists( 'WFFN_Core' ),
				),
				array(
					'name'      => 'WooCommerce One Page Checkout',
					'is_active' => class_exists( 'PP_One_Page_Checkout' ),
				),
				array(
					'name'      => 'All Products for Woo Subscriptions',
					'is_active' => class_exists( 'WCS_ATT' ),
				),
			)
		);
	},

	'axo.fastlane-incompatible-plugin-names' => static function ( ContainerInterface $container ) : array {
		$incompatible_plugins = $container->get( 'axo.fastlane-incompatible-plugins' );

		$active_plugins_list = array_filter(
			$incompatible_plugins,
			function( array $plugin ): bool {
				return (bool) $plugin['is_active'];
			}
		);

		if ( empty( $active_plugins_list ) ) {
			return array();
		}

		return array_map(
			function ( array $plugin ): string {
				return "<li>{$plugin['name']}</li>";
			},
			$active_plugins_list
		);
	},
);
