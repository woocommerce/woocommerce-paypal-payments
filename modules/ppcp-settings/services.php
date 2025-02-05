<?php
/**
 * The Settings module services.
 *
 * @package WooCommerce\PayPalCommerce\Settings
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Settings\Ajax\SwitchSettingsUiEndpoint;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\TodosDefinition;
use WooCommerce\PayPalCommerce\Settings\Endpoint\AuthenticationRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CompleteOnClickEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\LoginLinkRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\OnboardingRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PayLaterMessagingEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PaymentRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RefreshFeatureStatusEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\ResetDismissedTodosEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\WebhookSettingsEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\StylingRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\TodosRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Handler\ConnectionListener;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\Settings\Service\ConnectionUrlGenerator;
use WooCommerce\PayPalCommerce\Settings\Service\OnboardingUrlManager;
use WooCommerce\PayPalCommerce\Settings\Service\TodosEligibilityService;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;

return array(
	'settings.url'                                => static function ( ContainerInterface $container ) : string {
		/**
		 * The path cannot be false.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-settings/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'settings.data.onboarding'                    => static function ( ContainerInterface $container ) : OnboardingProfile {
		$can_use_casual_selling = $container->get( 'settings.casual-selling.eligible' );
		$can_use_vaulting       = $container->has( 'save-payment-methods.eligible' ) && $container->get( 'save-payment-methods.eligible' );
		$can_use_card_payments  = $container->has( 'card-fields.eligible' ) && $container->get( 'card-fields.eligible' );
		$can_use_subscriptions  = $container->has( 'wc-subscriptions.helper' ) && $container->get( 'wc-subscriptions.helper' )
				->plugin_is_active();

		// Card payments are disabled for this plugin when WooPayments is active.
		// TODO: Move this condition to the card-fields.eligible service?
		if ( class_exists( '\WC_Payments' ) ) {
			$can_use_card_payments = false;
		}

		return new OnboardingProfile(
			$can_use_casual_selling,
			$can_use_vaulting,
			$can_use_card_payments,
			$can_use_subscriptions
		);
	},
	'settings.data.general'                       => static function ( ContainerInterface $container ) : GeneralSettings {
		return new GeneralSettings(
			$container->get( 'api.shop.country' ),
			$container->get( 'api.shop.currency.getter' )->get(),
			$container->get( 'wcgateway.is-send-only-country' )
		);
	},
	'settings.data.styling'                       => static function ( ContainerInterface $container ) : StylingSettings {
		return new StylingSettings(
			$container->get( 'settings.service.sanitizer' )
		);
	},
	'settings.data.payment'                       => static function ( ContainerInterface $container ) : PaymentSettings {
		return new PaymentSettings();
	},
	'settings.data.settings'                      => static function ( ContainerInterface $container ) : SettingsModel {
		return new SettingsModel(
			$container->get( 'settings.service.sanitizer' )
		);
	},
	/**
	 * Checks if valid merchant connection details are stored in the DB.
	 */
	'settings.flag.is-connected'                  => static function ( ContainerInterface $container ) : bool {
		$data = $container->get( 'settings.data.general' );
		assert( $data instanceof GeneralSettings );

		return $data->is_merchant_connected();
	},
	'settings.rest.onboarding'                    => static function ( ContainerInterface $container ) : OnboardingRestEndpoint {
		return new OnboardingRestEndpoint( $container->get( 'settings.data.onboarding' ) );
	},
	'settings.rest.common'                        => static function ( ContainerInterface $container ) : CommonRestEndpoint {
		return new CommonRestEndpoint( $container->get( 'settings.data.general' ) );
	},
	'settings.rest.payment'                       => static function ( ContainerInterface $container ) : PaymentRestEndpoint {
		return new PaymentRestEndpoint( $container->get( 'settings.data.payment' ) );
	},
	'settings.rest.styling'                       => static function ( ContainerInterface $container ) : StylingRestEndpoint {
		return new StylingRestEndpoint(
			$container->get( 'settings.data.styling' ),
			$container->get( 'settings.service.sanitizer' )
		);
	},
	'settings.rest.refresh_feature_status'        => static function ( ContainerInterface $container ) : RefreshFeatureStatusEndpoint {
		return new RefreshFeatureStatusEndpoint(
			$container->get( 'wcgateway.settings' ),
			new Cache( 'ppcp-timeout' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'settings.rest.connect_manual'                => static function ( ContainerInterface $container ) : AuthenticationRestEndpoint {
		return new AuthenticationRestEndpoint(
			$container->get( 'settings.service.authentication_manager' ),
		);
	},
	'settings.rest.login_link'                    => static function ( ContainerInterface $container ) : LoginLinkRestEndpoint {
		return new LoginLinkRestEndpoint(
			$container->get( 'settings.service.connection-url-generator' ),
		);
	},
	'settings.rest.webhooks'                      => static function ( ContainerInterface $container ) : WebhookSettingsEndpoint {
		return new WebhookSettingsEndpoint(
			$container->get( 'api.endpoint.webhook' ),
			$container->get( 'webhook.registrar' ),
			$container->get( 'webhook.status.simulation' )
		);
	},
	'settings.rest.pay_later_messaging'           => static function ( ContainerInterface $container ) : PayLaterMessagingEndpoint {
		return new PayLaterMessagingEndpoint(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'paylater-configurator.endpoint.save-config' )
		);
	},
	'settings.rest.settings'                      => static function ( ContainerInterface $container ) : SettingsRestEndpoint {
		return new SettingsRestEndpoint(
			$container->get( 'settings.data.settings' )
		);
	},
	'settings.casual-selling.supported-countries' => static function ( ContainerInterface $container ) : array {
		return array(
			'AR',
			'AU',
			'AT',
			'BE',
			'BR',
			'CA',
			'CL',
			'CN',
			'CY',
			'CZ',
			'DK',
			'EE',
			'FI',
			'FR',
			'GR',
			'HU',
			'ID',
			'IE',
			'IT',
			'JP',
			'LV',
			'LI',
			'LU',
			'MY',
			'MT',
			'NL',
			'NZ',
			'NO',
			'PH',
			'PL',
			'PT',
			'RO',
			'RU',
			'SM',
			'SA',
			'SG',
			'SK',
			'SI',
			'ZA',
			'KR',
			'ES',
			'SE',
			'TW',
			'GB',
			'US',
			'VN',
		);
	},
	'settings.casual-selling.eligible'            => static function ( ContainerInterface $container ) : bool {
		$country            = $container->get( 'api.shop.country' );
		$eligible_countries = $container->get( 'settings.casual-selling.supported-countries' );

		return in_array( $country, $eligible_countries, true );
	},
	'settings.handler.connection-listener'        => static function ( ContainerInterface $container ) : ConnectionListener {
		$page_id = $container->has( 'wcgateway.current-ppcp-settings-page-id' ) ? $container->get( 'wcgateway.current-ppcp-settings-page-id' ) : '';

		return new ConnectionListener(
			$page_id,
			$container->get( 'settings.service.onboarding-url-manager' ),
			$container->get( 'settings.service.authentication_manager' ),
			$container->get( 'http.redirector' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'settings.service.signup-link-cache'          => static function ( ContainerInterface $container ) : Cache {
		return new Cache( 'ppcp-paypal-signup-link' );
	},
	'settings.service.onboarding-url-manager'     => static function ( ContainerInterface $container ) : OnboardingUrlManager {
		return new OnboardingUrlManager(
			$container->get( 'settings.service.signup-link-cache' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'settings.service.connection-url-generator'   => static function ( ContainerInterface $container ) : ConnectionUrlGenerator {
		return new ConnectionUrlGenerator(
			$container->get( 'api.env.endpoint.partner-referrals' ),
			$container->get( 'api.repository.partner-referrals-data' ),
			$container->get( 'settings.service.onboarding-url-manager' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'settings.service.authentication_manager'     => static function ( ContainerInterface $container ) : AuthenticationManager {
		return new AuthenticationManager(
			$container->get( 'settings.data.general' ),
			$container->get( 'api.env.paypal-host' ),
			$container->get( 'api.env.endpoint.login-seller' ),
			$container->get( 'api.repository.partner-referrals-data' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
		);
	},
	'settings.service.sanitizer'                  => static function ( ContainerInterface $container ) : DataSanitizer {
		return new DataSanitizer();
	},
	'settings.ajax.switch_ui'                     => static function ( ContainerInterface $container ) : SwitchSettingsUiEndpoint {
		return new SwitchSettingsUiEndpoint(
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'button.request-data' ),
			$container->get( 'settings.data.onboarding' ),
			$container->get( 'api.merchant_id' ) !== ''
		);
	},
	'settings.rest.todos'                         => static function ( ContainerInterface $container ) : TodosRestEndpoint {
		return new TodosRestEndpoint(
			$container->get( 'settings.data.todos' ),
			$container->get( 'settings.data.definition.todos' ),
			$container->get( 'settings.rest.settings' )
		);
	},
	'settings.data.todos'                         => static function ( ContainerInterface $container ) : TodosModel {
		return new TodosModel();
	},
	'settings.data.definition.todos'              => static function ( ContainerInterface $container ) : TodosDefinition {
		return new TodosDefinition(
			$container->get( 'settings.service.todos_eligibilities' ),
			$container->get( 'settings.data.general' )
		);
	},
	'settings.service.todos_eligibilities'        => static function( ContainerInterface $container ): TodosEligibilityService {
		$features = apply_filters(
			'woocommerce_paypal_payments_rest_common_merchant_features',
			array()
		);

		$payment_endpoint = $container->get( 'settings.rest.payment' );
		$settings = $payment_endpoint->get_details()->get_data();

		// Settings status.
		$gateways = array(
			'apple_pay'   => $settings['data']['ppcp-applepay']['enabled'] ?? false,
			'google_pay'  => $settings['data']['ppcp-googlepay']['enabled'] ?? false,
			'axo'         => $settings['data']['ppcp-axo-gateway']['enabled'] ?? false,
			'card-button' => $settings['data']['ppcp-card-button-gateway']['enabled'] ?? false,
		);

		// Merchant eligibility.
		$capabilities = array(
			'apple_pay'   => $features['apple_pay']['enabled'] ?? false,
			'google_pay'  => $features['google_pay']['enabled'] ?? false,
			'acdc'        => $features['advanced_credit_and_debit_cards']['enabled'] ?? false,
			'save_paypal' => $features['save_paypal_and_venmo']['enabled'] ?? false,
			'apm'         => $features['alternative_payment_methods']['enabled'] ?? false,
			'paylater'    => $features['pay_later_messaging']['enabled'] ?? false,
		);

		return new TodosEligibilityService(
			$capabilities['acdc'] && ! $gateways['axo'], // Enable Fastlane.
			$capabilities['acdc'] && ! $gateways['card-button'], // Enable Credit and Debit Cards on your checkout.
			true,                         // Enable Pay Later messaging.
			true,                              // Add Pay Later messaging.
			true,                      // Configure a PayPal Subscription.
			true,                     // Add PayPal buttons.
			true,                                              // Register Domain for Apple Pay.
			$capabilities['acdc'] && ! ( $capabilities['apple_pay'] && $capabilities['google_pay'] ), // Add digital wallets to your account.
			$capabilities['acdc'] && ! $capabilities['apple_pay'],      // Add Apple Pay to your account.
			$capabilities['acdc'] && ! $capabilities['google_pay'],     // Add Google Pay to your account.
			true,                      // Configure a PayPal Subscription.
			$capabilities['apple_pay'] && ! $gateways['apple_pay'],     // Enable Apple Pay.
			$capabilities['google_pay'] && ! $gateways['google_pay']    // Enable Google Pay.
		);
	},
	'settings.rest.reset_dismissed_todos'         => static function( ContainerInterface $container ): ResetDismissedTodosEndpoint {
		return new ResetDismissedTodosEndpoint();
	},
	'settings.rest.complete_onclick'              => static function( ContainerInterface $container ): CompleteOnClickEndpoint {
		return new CompleteOnClickEndpoint();
	},
);
