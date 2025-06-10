<?php
/**
 * The Settings module.
 *
 * @package WooCommerce\PayPalCommerce\Settings
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings;

use WC_Payment_Gateway;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Applepay\Assets\AppleProductStatus;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
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
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Handler\ConnectionListener;
use WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\PathRepository;
use WooCommerce\PayPalCommerce\Settings\Service\GatewayRedirectService;
use WooCommerce\PayPalCommerce\Settings\Service\LoadingScreenService;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\Settings\Service\SettingsDataManager;
use WooCommerce\PayPalCommerce\Settings\DTO\ConfigurationFlagsDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\ProductChoicesEnum;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Axo\Helper\CompatibilityChecker;

/**
 * Class SettingsModule
 */
class SettingsModule implements ServiceModule, ExecutableModule {
	use ModuleClassNameIdTrait;

	/**
	 * Returns whether the old settings UI should be loaded.
	 */
	public static function should_use_the_old_ui() : bool {
		// New merchants should never see the #legacy-ui.
		$show_new_ux = '1' === get_option( 'woocommerce-ppcp-is-new-merchant' );

		if ( $show_new_ux ) {
			return false;
		}

		// Existing merchants can opt-in to see the new UI.
		$opt_out_choice = 'yes' === get_option( SwitchSettingsUiEndpoint::OPTION_NAME_SHOULD_USE_OLD_UI );

		return apply_filters(
			'woocommerce_paypal_payments_should_use_the_old_ui',
			$opt_out_choice
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

					wp_enqueue_script( 'ppcp-switch-settings-ui', '', array( 'wp-i18n' ), $script_asset_file['version'], false );
					wp_set_script_translations(
						'ppcp-switch-settings-ui',
						'woocommerce-paypal-payments',
					);
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

		/**
		 * This hook is fired when the plugin is updated.
		 */
		add_action(
			'woocommerce_paypal_payments_gateway_migrate_on_update',
			static fn() => ! get_option( SwitchSettingsUiEndpoint::OPTION_NAME_SHOULD_USE_OLD_UI )
				&& update_option( SwitchSettingsUiEndpoint::OPTION_NAME_SHOULD_USE_OLD_UI, 'yes' )
		);

		/**
		 * This hook is fired when the plugin is installed or updated.
		 */
		add_action(
			'woocommerce_paypal_payments_gateway_migrate',
			function () use ( $container ) {
				$path_repository = $container->get( 'settings.service.branded-experience.path-repository' );
				assert( $path_repository instanceof PathRepository );

				$partner_attribution = $container->get( 'api.helper.partner-attribution' );
				assert( $partner_attribution instanceof PartnerAttribution );

				$general_settings = $container->get( 'settings.data.general' );
				assert( $general_settings instanceof GeneralSettings );

				$path_repository->persist();
				$partner_attribution->initialize_bn_code( $general_settings->get_installation_path() );
			}
		);

		// Suppress WooCommerce Settings UI elements via CSS to improve the loading experience.
		$loading_screen_service = $container->get( 'settings.services.loading-screen-service' );
		assert( $loading_screen_service instanceof LoadingScreenService );
		$loading_screen_service->register();

		$this->apply_branded_only_limitations( $container );

		add_action(
			'admin_enqueue_scripts',
			function ( string $hook_suffix ) use ( $container ) : void {
				$script_data_handler = $container->get( 'settings.service.script-data-handler' );
				$script_data_handler->localize_scripts( $hook_suffix );
			}
		);

		add_action(
			'woocommerce_paypal_payments_gateway_admin_options_wrapper',
			function () use ( $container ) : void {
				global $hide_save_button;
				$hide_save_button = true;

				$this->initialize_branded_only( $container );

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
					'connect_manual'         => $container->get( 'settings.rest.authentication' ),
					'login_link'             => $container->get( 'settings.rest.login_link' ),
					'webhooks'               => $container->get( 'settings.rest.webhooks' ),
					'refresh_feature_status' => $container->get( 'settings.rest.refresh_feature_status' ),
					'payment'                => $container->get( 'settings.rest.payment' ),
					'settings'               => $container->get( 'settings.rest.settings' ),
					'styling'                => $container->get( 'settings.rest.styling' ),
					'todos'                  => $container->get( 'settings.rest.todos' ),
					'pay_later_messaging'    => $container->get( 'settings.rest.pay_later_messaging' ),
					'features'               => $container->get( 'settings.rest.features' ),
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
				$logger = $container->get( 'woocommerce.logger.woocommerce' );
				assert( $logger instanceof LoggerInterface );
				$logger->info( 'Merchant disconnected, reset onboarding' );

				// Reset onboarding profile.
				$onboarding_profile = $container->get( 'settings.data.onboarding' );
				assert( $onboarding_profile instanceof OnboardingProfile );

				$onboarding_profile->set_completed( false );
				$onboarding_profile->set_step( 0 );
				$onboarding_profile->set_gateways_synced( false );
				$onboarding_profile->set_gateways_refreshed( false );
				$onboarding_profile->save();

				// Reset dismissed and completed on click todos.
				$todos = $container->get( 'settings.data.todos' );
				assert( $todos instanceof TodosModel );
				$todos->reset_dismissed_todos();
				$todos->reset_completed_onclick_todos();
			}
		);

		add_action(
			'woocommerce_paypal_payments_authenticated_merchant',
			static function () use ( $container ) : void {
				$logger = $container->get( 'woocommerce.logger.woocommerce' );
				assert( $logger instanceof LoggerInterface );
				$logger->info( 'Merchant connected, complete onboarding and set defaults.' );

				$onboarding_profile = $container->get( 'settings.data.onboarding' );
				assert( $onboarding_profile instanceof OnboardingProfile );

				$onboarding_profile->set_completed( true );
				$onboarding_profile->save();

				// Try to apply a default configuration for the current store.
				$data_manager = $container->get( 'settings.service.data-manager' );
				assert( $data_manager instanceof SettingsDataManager );

				$general_settings = $container->get( 'settings.data.general' );
				assert( $general_settings instanceof GeneralSettings );

				$flags = new ConfigurationFlagsDTO();

				$flags->country_code       = $general_settings->get_merchant_country();
				$flags->is_business_seller = $general_settings->is_business_seller();
				$flags->use_card_payments  = $onboarding_profile->get_accept_card_payments();
				$flags->use_subscriptions  = in_array( ProductChoicesEnum::SUBSCRIPTIONS, $onboarding_profile->get_products(), true );

				$data_manager->set_defaults_for_new_merchant( $flags );
			}
		);

		add_filter(
			'woocommerce_paypal_payments_payment_methods',
			function ( array $payment_methods ) use ( $container ) : array {
				$all_payment_methods = $payment_methods;

				$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
				assert( $dcc_product_status instanceof DCCProductStatus );

				$googlepay_product_status = $container->get( 'googlepay.helpers.apm-product-status' );
				assert( $googlepay_product_status instanceof ApmProductStatus );

				$applepay_product_status = $container->get( 'applepay.apple-product-status' );
				assert( $applepay_product_status instanceof AppleProductStatus );

				$dcc_applies = $container->get( 'api.helpers.dccapplies' );
				assert( $dcc_applies instanceof DCCApplies );

				$general_settings = $container->get( 'settings.data.general' );
				assert( $general_settings instanceof GeneralSettings );

				$merchant_data    = $general_settings->get_merchant_data();
				$merchant_country = $merchant_data->merchant_country;

				// Unset BCDC if merchant is eligible for ACDC and country is eligible for card fields.
				$card_fields_eligible = $container->get( 'card-fields.eligible' );
				if ( 'MX' !== $container->get( 'api.shop.country' ) ) {
					if ( $dcc_product_status->is_active() && $card_fields_eligible ) {
						unset( $payment_methods[ CardButtonGateway::ID ] );
					} else {
						// For non-ACDC regions unset ACDC.
						unset( $payment_methods[ CreditCardGateway::ID ] );
					}
				}

				// Unset Venmo when store location is not United States.
				if ( $container->get( 'api.shop.country' ) !== 'US' ) {
					unset( $payment_methods['venmo'] );
				}

				// Unset if country/currency is not supported or merchant not eligible for Google Pay.
				if ( ! $container->get( 'googlepay.eligible' ) || ! $googlepay_product_status->is_active() ) {
					unset( $payment_methods['ppcp-googlepay'] );
				}

				// Unset if country/currency is not supported or merchant not eligible for Apple Pay.
				if ( ! $container->get( 'applepay.eligible' ) || ! $applepay_product_status->is_active() ) {
					unset( $payment_methods['ppcp-applepay'] );
				}

				// Unset Fastlane if country/currency is not supported or merchant is not eligible for BCDC.
				if ( ! $container->get( 'axo.eligible' ) || ! $dcc_product_status->is_active() ) {
					unset( $payment_methods['ppcp-axo-gateway'] );
				}

				// Unset OXXO if merchant country is not Mexico.
				if ( 'MX' !== $merchant_country ) {
					unset( $payment_methods[ OXXO::ID ] );
				}

				// Unset Pay Upon Invoice if merchant country is not Germany.
				if ( 'DE' !== $merchant_country ) {
					unset( $payment_methods[ PayUponInvoiceGateway::ID ] );
				}

				// Unset all APMs other than OXXO for Mexico.
				if ( 'MX' === $merchant_country ) {
					unset( $payment_methods[ BancontactGateway::ID ] );
					unset( $payment_methods[ BlikGateway::ID ] );
					unset( $payment_methods[ EPSGateway::ID ] );
					unset( $payment_methods[ IDealGateway::ID ] );
					unset( $payment_methods[ MyBankGateway::ID ] );
					unset( $payment_methods[ P24Gateway::ID ] );
					unset( $payment_methods[ TrustlyGateway::ID ] );
					unset( $payment_methods[ MultibancoGateway::ID ] );
				}

				return $payment_methods;
			}
		);

		add_filter(
			'woocommerce_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			function ( $methods ) use ( $container ) : array {
				$is_onboarded = $container->get( 'api.merchant_id' ) !== '';
				if ( ! is_array( $methods ) || ! $is_onboarded ) {
					return $methods;
				}

				$card_button_gateway = $container->get( 'wcgateway.card-button-gateway' );
				assert( $card_button_gateway instanceof CardButtonGateway );

				$googlepay_gateway = $container->get( 'googlepay.wc-gateway' );
				assert( $googlepay_gateway instanceof WC_Payment_Gateway );

				$applepay_gateway = $container->get( 'applepay.wc-gateway' );
				assert( $applepay_gateway instanceof WC_Payment_Gateway );

				$axo_gateway = $container->get( 'axo.gateway' );
				assert( $axo_gateway instanceof WC_Payment_Gateway );

				$methods[] = $card_button_gateway;
				$methods[] = $googlepay_gateway;
				$methods[] = $applepay_gateway;
				$methods[] = $axo_gateway;

				return $methods;
			},
			99
		);

		/**
		 * Filters the available payment gateways in the WooCommerce admin settings.
		 *
		 * Ensures that only enabled PayPal payment gateways are displayed.
		 *
		 * @hook woocommerce_admin_field_payment_gateways
		 * @priority 5 Allows modifying the registered gateways before they are displayed.
		 */
		add_action(
			'woocommerce_admin_field_payment_gateways',
			function () use ( $container ) : void {
				$all_gateway_ids  = $container->get( 'settings.config.all-gateway-ids' );
				$payment_gateways = WC()->payment_gateways->payment_gateways;

				foreach ( $payment_gateways as $index => $payment_gateway ) {
					$payment_gateway_id = $payment_gateway->id;

					if (
						! in_array( $payment_gateway_id, $all_gateway_ids, true )
						|| $payment_gateway_id === PayPalGateway::ID
						|| $this->is_gateway_enabled( $payment_gateway_id )
					) {
						continue;
					}

					unset( WC()->payment_gateways->payment_gateways[ $index ] );
				}
			},
			5
		);

		// Remove the Fastlane gateway if the customer is logged in, ensuring that we don't interfere with the Fastlane gateway status in the settings UI.
		add_filter(
			'woocommerce_available_payment_gateways',
			/**
			 * Param types removed to avoid third-party issues.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $methods ) use ( $container ) : array {
				if ( ! is_array( $methods ) ) {
					return $methods;
				}

				if ( is_user_logged_in() && ! is_admin() ) {
					foreach ( $methods as $key => $method ) {
						if ( $method instanceof WC_Payment_Gateway && $method->id === 'ppcp-axo-gateway' ) {
							unset( $methods[ $key ] );
							break;
						}
					}
				}

				return $methods;
			}
		);

		add_filter(
			'woocommerce_paypal_payments_gateway_title',
			function ( string $title, WC_Payment_Gateway $gateway ) {
				return $gateway->get_option( 'title', $title );
			},
			10,
			2
		);
		add_filter(
			'woocommerce_paypal_payments_gateway_description',
			function ( string $description, WC_Payment_Gateway $gateway ) {
				return $gateway->get_option( 'description', $description );
			},
			10,
			2
		);

		add_filter( 'woocommerce_paypal_payments_card_button_gateway_should_register_gateway', '__return_true' );

		add_filter(
			'woocommerce_paypal_payments_credit_card_gateway_form_fields',
			function ( array $form_fields ) {
				$form_fields['enabled'] = array(
					'title'       => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
					'type'        => 'checkbox',
					'desc_tip'    => true,
					'description' => __( 'Once enabled, the Credit Card option will show up in the checkout.', 'woocommerce-paypal-payments' ),
					'label'       => __( 'Enable Advanced Card Processing', 'woocommerce-paypal-payments' ),
					'default'     => 'no',
				);

				return $form_fields;
			}
		);
		add_filter( 'woocommerce_paypal_payments_credit_card_gateway_should_update_enabled', '__return_false' );

		add_filter(
			'woocommerce_paypal_payments_credit_card_gateway_title',
			function ( string $title, WC_Payment_Gateway $gateway ) {
				return $gateway->get_option( 'title', $title );
			},
			10,
			2
		);
		add_filter(
			'woocommerce_paypal_payments_credit_card_gateway_description',
			function ( string $description, WC_Payment_Gateway $gateway ) {
				return $gateway->get_option( 'description', $description );
			},
			10,
			2
		);

		if ( is_admin() ) {
			add_filter( 'woocommerce_paypal_payments_axo_gateway_should_update_enabled', '__return_false' );
			add_filter(
				'woocommerce_paypal_payments_axo_gateway_title',
				function ( string $title, WC_Payment_Gateway $gateway ) {
					return $gateway->get_option( 'title', $title );
				},
				10,
				2
			);
			add_filter(
				'woocommerce_paypal_payments_axo_gateway_description',
				function ( string $description, WC_Payment_Gateway $gateway ) {
					return $gateway->get_option( 'description', $description );
				},
				10,
				2
			);
		}

		// Enable Fastlane after onboarding if the store is compatible.
		add_action(
			'woocommerce_paypal_payments_toggle_payment_gateways',
			function ( PaymentSettings $payment_methods, ConfigurationFlagsDTO $flags ) use ( $container ) {
				if ( $flags->is_business_seller && $flags->use_card_payments ) {
					$compatibility_checker = $container->get( 'axo.helpers.compatibility-checker' );
					assert( $compatibility_checker instanceof CompatibilityChecker );

					if ( $compatibility_checker->is_fastlane_compatible() ) {
						$payment_methods->toggle_method_state( AxoGateway::ID, true );
					}
				}

				$general_settings = $container->get( 'settings.data.general' );
				assert( $general_settings instanceof GeneralSettings );

				$merchant_data    = $general_settings->get_merchant_data();
				$merchant_country = $merchant_data->merchant_country;

				// Disable all extended checkout card methods if the store is in Mexico.
				if ( 'MX' === $merchant_country ) {
					$payment_methods->toggle_method_state( CreditCardGateway::ID, false );
					$payment_methods->toggle_method_state( ApplePayGateway::ID, false );
					$payment_methods->toggle_method_state( GooglePayGateway::ID, false );
				}
			},
			10,
			2
		);

		// Enable APMs after onboarding if the country is compatible.
		add_action(
			'woocommerce_paypal_payments_toggle_payment_gateways_apms',
			function ( PaymentSettings $payment_methods, array $methods_apm ) use ( $container ) {

				$general_settings = $container->get( 'settings.data.general' );
				assert( $general_settings instanceof GeneralSettings );

				$merchant_data    = $general_settings->get_merchant_data();
				$merchant_country = $merchant_data->merchant_country;

				// Enable all APM methods.
				foreach ( $methods_apm as $method ) {
					// Skip PayUponInvoice if merchant is not in Germany.
					if ( PayUponInvoiceGateway::ID === $method['id'] && 'DE' !== $merchant_country ) {
						continue;
					}

					// For OXXO: enable ONLY if merchant is in Mexico.
					if ( OXXO::ID === $method['id'] ) {
						if ( 'MX' === $merchant_country ) {
							$payment_methods->toggle_method_state( $method['id'], true );
						}
						continue;
					}

					// For all other APMs: enable only if merchant is NOT in Mexico.
					if ( 'MX' !== $merchant_country ) {
						$payment_methods->toggle_method_state( $method['id'], true );
					}
				}
			},
			10,
			2
		);

		// Toggle payment gateways after onboarding based on flags.
		add_action(
			'woocommerce_paypal_payments_sync_gateways',
			static function () use ( $container ) {
				$settings_data_manager = $container->get( 'settings.service.data-manager' );
				assert( $settings_data_manager instanceof SettingsDataManager );
				$settings_data_manager->sync_gateway_settings();
			}
		);

		// Redirect payment method links in the WC Payment Gateway to the new UI Payment Methods tab.
		$gateway_redirect_service = $container->get( 'settings.service.gateway-redirect' );
		assert( $gateway_redirect_service instanceof GatewayRedirectService );
		$gateway_redirect_service->register();

		// Do not render Pay Later messaging if the "Save PayPal and Venmo" setting is enabled.
		add_filter(
			'woocommerce_paypal_payments_should_render_pay_later_messaging',
			static function() use ( $container ): bool {
				$settings_model = $container->get( 'settings.data.settings' );
				assert( $settings_model instanceof SettingsModel );

				return ! $settings_model->get_save_paypal_and_venmo();
			}
		);

		return true;
	}

	/**
	 * Checks the branded-only state and applies relevant site-wide feature limitations, if needed.
	 *
	 * @param ContainerInterface $container The DI container provider.
	 * @return void
	 */
	protected function apply_branded_only_limitations( ContainerInterface $container ) : void {
		$settings = $container->get( 'settings.data.general' );
		assert( $settings instanceof GeneralSettings );

		if ( ! $settings->own_brand_only() ) {
			return;
		}

		/**
		 * In branded-only mode, we completely disable all white label features.
		 */
		add_filter( 'woocommerce_paypal_payments_is_eligible_for_applepay', '__return_false' );
		add_filter( 'woocommerce_paypal_payments_is_eligible_for_googlepay', '__return_false' );
		add_filter( 'woocommerce_paypal_payments_is_eligible_for_axo', '__return_false' );
		add_filter( 'woocommerce_paypal_payments_is_eligible_for_save_payment_methods', '__return_false' );
		add_filter( 'woocommerce_paypal_payments_is_eligible_for_card_fields', '__return_false' );
	}

	/**
	 * Initializes the branded-only flags if they are not set.
	 *
	 * This method can be called multiple times:
	 * The flags are only initialized once but does not change afterward.
	 *
	 * Also, this check has no impact on performance for two reasons:
	 * 1. The GeneralSettings class is already initialized and will short-circuit
	 *    the check if the settings are already initialized.
	 * 2. The settings UI is a React app, this method only runs when the React app
	 *    is injected to the DOM, and not while the UI is used.
	 *
	 * @param ContainerInterface $container The DI container provider.
	 * @return void
	 */
	protected function initialize_branded_only( ContainerInterface $container ) : void {
		$path_repository = $container->get( 'settings.service.branded-experience.path-repository' );
		assert( $path_repository instanceof PathRepository );

		$partner_attribution = $container->get( 'api.helper.partner-attribution' );
		assert( $partner_attribution instanceof PartnerAttribution );

		$general_settings = $container->get( 'settings.data.general' );
		assert( $general_settings instanceof GeneralSettings );

		$path_repository->persist();

		$partner_attribution->initialize_bn_code( $general_settings->get_installation_path() );
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

	/**
	 * Checks if the payment gateway with the given name is enabled.
	 *
	 * @param string $gateway_name The gateway name.
	 * @return bool True if the payment gateway with the given name is enabled, otherwise false.
	 */
	protected function is_gateway_enabled( string $gateway_name ) : bool {
		$gateway_settings = get_option( "woocommerce_{$gateway_name}_settings", array() );
		$gateway_enabled  = $gateway_settings['enabled'] ?? false;

		return $gateway_enabled === 'yes';
	}
}
