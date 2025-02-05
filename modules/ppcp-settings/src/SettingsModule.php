<?php
/**
 * The Settings module.
 *
 * @package WooCommerce\PayPalCommerce\Settings
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Applepay\Assets\AppleProductStatus;
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmProductStatus;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Ajax\SwitchSettingsUiEndpoint;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Handler\ConnectionListener;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class SettingsModule
 */
class SettingsModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * Returns whether the old settings UI should be loaded.
	 */
	public static function should_use_the_old_ui() : bool {
		return apply_filters(
			'woocommerce_paypal_payments_should_use_the_old_ui',
			get_option( SwitchSettingsUiEndpoint::OPTION_NAME_SHOULD_USE_OLD_UI ) === 'yes'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function services() : array {
		return require __DIR__ . '/../services.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $container ) : bool {
		if ( self::should_use_the_old_ui() ) {
			add_filter(
				'woocommerce_paypal_payments_inside_settings_page_header',
				static fn() : string => sprintf(
					'<a href="#" class="button button-settings-switch-ui">%s</a>',
					esc_html__( 'Switch to new settings UI', 'woocommerce-paypal-payments' )
				)
			);

			add_action(
				'admin_enqueue_scripts',
				static function () use ( $container ) {
					$module_url = $container->get( 'settings.url' );

					/**
					 * Require resolves.
					 *
					 * @psalm-suppress UnresolvableInclude
					 */
					$script_asset_file = require dirname( realpath( __FILE__ ) ?: '', 2 ) . '/assets/switchSettingsUi.asset.php';

					wp_register_script(
						'ppcp-switch-settings-ui',
						untrailingslashit( $module_url ) . '/assets/switchSettingsUi.js',
						$script_asset_file['dependencies'],
						$script_asset_file['version'],
						true
					);

					wp_localize_script(
						'ppcp-switch-settings-ui',
						'ppcpSwitchSettingsUi',
						array(
							'endpoint' => \WC_AJAX::get_endpoint( SwitchSettingsUiEndpoint::ENDPOINT ),
							'nonce'    => wp_create_nonce( SwitchSettingsUiEndpoint::nonce() ),
						)
					);

					wp_enqueue_script( 'ppcp-switch-settings-ui' );
				}
			);

			$endpoint = $container->get( 'settings.ajax.switch_ui' ) ? $container->get( 'settings.ajax.switch_ui' ) : null;
			assert( $endpoint instanceof SwitchSettingsUiEndpoint );

			add_action(
				'wc_ajax_' . SwitchSettingsUiEndpoint::ENDPOINT,
				array(
					$endpoint,
					'handle_request',
				)
			);

			return true;
		}

		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			static fn () => ! get_option( SwitchSettingsUiEndpoint::OPTION_NAME_SHOULD_USE_OLD_UI )
				&& update_option( SwitchSettingsUiEndpoint::OPTION_NAME_SHOULD_USE_OLD_UI, 'yes' )
		);

		add_action(
			'admin_enqueue_scripts',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $hook_suffix ) use ( $container ) {
				if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
					return;
				}

				/**
				 * Require resolves.
				 *
				 * @psalm-suppress UnresolvableInclude
				 */
				$script_asset_file = require dirname( realpath( __FILE__ ) ?: '', 2 ) . '/assets/index.asset.php';

				$module_url = $container->get( 'settings.url' );

				wp_register_script(
					'ppcp-admin-settings',
					$module_url . '/assets/index.js',
					$script_asset_file['dependencies'],
					$script_asset_file['version'],
					true
				);

				wp_enqueue_script( 'ppcp-admin-settings' );

				/**
				 * Require resolves.
				 *
				 * @psalm-suppress UnresolvableInclude
				 */
				$style_asset_file = require dirname( realpath( __FILE__ ) ?: '', 2 ) . '/assets/style.asset.php';

				wp_register_style(
					'ppcp-admin-settings',
					$module_url . '/assets/style-style.css',
					$style_asset_file['dependencies'],
					$style_asset_file['version']
				);

				$settings = $container->get( 'wcgateway.settings' );
				assert( $settings instanceof Settings );

				wp_enqueue_style( 'ppcp-admin-settings' );

				wp_enqueue_style( 'ppcp-admin-settings-font', 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap', array(), $style_asset_file['version'] );

				$is_pay_later_configurator_available = $container->get( 'paylater-configurator.is-available' );

				$script_data = array(
					'assets'                          => array(
						'imagesUrl' => $module_url . '/images/',
					),
					'wcPaymentsTabUrl'                => admin_url( 'admin.php?page=wc-settings&tab=checkout' ),
					'debug'                           => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'isPayLaterConfiguratorAvailable' => $is_pay_later_configurator_available,
					'storeCountry'                    => $container->get( 'wcgateway.store-country' ),
				);

				if ( $is_pay_later_configurator_available ) {
					wp_enqueue_script(
						'ppcp-paylater-configurator-lib',
						'https://www.paypalobjects.com/merchant-library/merchant-configurator.js',
						array(),
						$script_asset_file['version'],
						true
					);

					$script_data['PcpPayLaterConfigurator'] = array(
						'config'           => array(),
						'merchantClientId' => $settings->get( 'client_id' ),
						'partnerClientId'  => $container->get( 'api.partner_merchant_id' ),
						'bnCode'           => PPCP_PAYPAL_BN_CODE,
					);
				}

				wp_localize_script(
					'ppcp-admin-settings',
					'ppcpSettings',
					$script_data
				);
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_admin_options_wrapper',
			function () : void {
				global $hide_save_button;
				$hide_save_button = true;

				$this->render_header();
				$this->render_content();
			}
		);

		add_action(
			'rest_api_init',
			static function () use ( $container ) : void {
				$endpoints = array(
					'onboarding'             => $container->get( 'settings.rest.onboarding' ),
					'common'                 => $container->get( 'settings.rest.common' ),
					'connect_manual'         => $container->get( 'settings.rest.connect_manual' ),
					'login_link'             => $container->get( 'settings.rest.login_link' ),
					'webhooks'               => $container->get( 'settings.rest.webhooks' ),
					'refresh_feature_status' => $container->get( 'settings.rest.refresh_feature_status' ),
					'payment'                => $container->get( 'settings.rest.payment' ),
					'settings'               => $container->get( 'settings.rest.settings' ),
					'styling'                => $container->get( 'settings.rest.styling' ),
					'todos'                  => $container->get( 'settings.rest.todos' ),
					'reset_dismissed_todos'  => $container->get( 'settings.rest.reset_dismissed_todos' ),
					'pay_later_messaging'    => $container->get( 'settings.rest.pay_later_messaging' ),
					'complete_onclick'       => $container->get( 'settings.rest.complete_onclick' ),
				);

				foreach ( $endpoints as $endpoint ) {
					assert( $endpoint instanceof RestEndpoint );
					$endpoint->register_routes();
				}
			}
		);

		add_action(
			'admin_init',
			static function () use ( $container ) : void {
				$connection_handler = $container->get( 'settings.handler.connection-listener' );
				assert( $connection_handler instanceof ConnectionListener );

				// @phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no nonce; sanitation done by the handler
				$connection_handler->process( get_current_user_id(), $_GET );
			}
		);

		add_action(
			'woocommerce_paypal_payments_merchant_disconnected',
			static function () use ( $container ) : void {
				$onboarding_profile = $container->get( 'settings.data.onboarding' );
				assert( $onboarding_profile instanceof OnboardingProfile );

				$onboarding_profile->set_completed( false );
				$onboarding_profile->set_step( 0 );
				$onboarding_profile->save();
			}
		);

		add_action(
			'woocommerce_paypal_payments_authenticated_merchant',
			static function () use ( $container ) : void {
				$onboarding_profile = $container->get( 'settings.data.onboarding' );
				assert( $onboarding_profile instanceof OnboardingProfile );

				$onboarding_profile->set_completed( true );
				$onboarding_profile->save();
			}
		);

		add_filter(
			'woocommerce_paypal_payments_payment_methods',
			function( array $payment_methods ) use ( $container ) : array {
				$all_payment_methods = $payment_methods;

				$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
				assert( $dcc_product_status instanceof DCCProductStatus );

				$googlepay_product_status = $container->get( 'googlepay.helpers.apm-product-status' );
				assert( $googlepay_product_status instanceof ApmProductStatus );

				$applepay_product_status = $container->get( 'applepay.apple-product-status' );
				assert( $applepay_product_status instanceof AppleProductStatus );

				$dcc_applies = $container->get( 'api.helpers.dccapplies' );
				assert( $dcc_applies instanceof DCCApplies );

				// Unset BCDC if merchant is eligible for ACDC.
				if ( $dcc_product_status->is_active() && ! $container->get( 'wcgateway.settings.allow_card_button_gateway' ) ) {
					unset( $payment_methods[ CardButtonGateway::ID ] );
				}

				// Unset Venmo when store location is not United States.
				if ( $container->get( 'api.shop.country' ) !== 'US' ) {
					unset( $payment_methods['venmo'] );
				}

				// Unset if not eligible for Google Pay.
				if ( ! $googlepay_product_status->is_active() ) {
					unset( $payment_methods['ppcp-googlepay'] );
				}

				// Unset if not eligible for Apple Pay.
				if ( ! $applepay_product_status->is_active() ) {
					unset( $payment_methods['ppcp-applepay'] );
				}

				// Unset Fastlane if store location is not United States or merchant is not eligible for ACDC.
				if ( $container->get( 'api.shop.country' ) !== 'US' || ! $dcc_product_status->is_active() ) {
					unset( $payment_methods['ppcp-axo-gateway'] );
				}

				// For non-ACDC regions unset ACDC, local APMs and set BCDC.
				if ( ! $dcc_applies ) {
					unset( $payment_methods[ CreditCardGateway::ID ] );
					unset( $payment_methods[ BancontactGateway::ID ] );
					unset( $payment_methods[ BlikGateway::ID ] );
					unset( $payment_methods[ EPSGateway::ID ] );
					unset( $payment_methods[ IDealGateway::ID ] );
					unset( $payment_methods[ MyBankGateway::ID ] );
					unset( $payment_methods[ P24Gateway::ID ] );
					unset( $payment_methods[ TrustlyGateway::ID ] );
					unset( $payment_methods[ MultibancoGateway::ID ] );
					unset( $payment_methods[ PayUponInvoiceGateway::ID ] );
					unset( $payment_methods[ OXXO::ID ] );

					$payment_methods[ CardButtonGateway::ID ] = $all_payment_methods[ CardButtonGateway::ID ];
				}

				return $payment_methods;
			}
		);

		return true;
	}

	/**
	 * Outputs the settings page header (title and back-link).
	 *
	 * @return void
	 */
	protected function render_header() : void {
		echo '<h2>' . esc_html__( 'PayPal', 'woocommerce-paypal-payments' );
		wc_back_link( __( 'Return to payments', 'woocommerce-paypal-payments' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
	}

	/**
	 * Renders the container for the React app.
	 *
	 * @return void
	 */
	protected function render_content() : void {
		echo '<div id="ppcp-settings-container"></div>';
	}
}
