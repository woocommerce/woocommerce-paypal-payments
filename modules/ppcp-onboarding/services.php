<?php
/**
 * The onboarding module services.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingOptionsRenderer;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Endpoint\UpdateSignupLinksEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingSendOnlyNoticeRenderer;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'api.paypal-host'                    => function( ContainerInterface $container ) : string {
		$environment = $container->get( 'settings.environment' );
		/**
		 * The current environment.
		 *
		 * @var Environment $environment
		 */
		if ( $environment->current_environment_is( Environment::SANDBOX ) ) {
			return $container->get( 'api.paypal-host-sandbox' );
		}
		return $container->get( 'api.paypal-host-production' );

	},
	'api.paypal-website-url'             => function( ContainerInterface $container ) : string {
		$environment = $container->get( 'settings.environment' );
		assert( $environment instanceof Environment );
		if ( $environment->current_environment_is( Environment::SANDBOX ) ) {
			return $container->get( 'api.paypal-website-url-sandbox' );
		}
		return $container->get( 'api.paypal-website-url-production' );

	},
	'onboarding.state'                   => function( ContainerInterface $container ) : State {
		$settings    = $container->get( 'wcgateway.settings' );
		return new State( $settings );
	},
	/**
	 * Checks if the onboarding process is completed and the merchant API can be used.
	 * This service is overwritten by the ppcp-settings module, when it's active.
	 */
	'settings.flag.is-connected'         => static function ( ContainerInterface $container ) : bool {
		$state = $container->get( 'onboarding.state' );
		assert( $state instanceof State );

		return $state->current_state() >= State::STATE_ONBOARDED;
	},
	'settings.flag.is-sandbox'           => static function ( ContainerInterface $container ) : bool {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		return $settings->has( 'sandbox_on' ) && $settings->get( 'sandbox_on' );
	},
	'settings.environment'               => function ( ContainerInterface $container ) : Environment {
		return new Environment(
			$container->get( 'settings.flag.is-sandbox' )
		);
	},

	'onboarding.assets'                  => function( ContainerInterface $container ) : OnboardingAssets {
		$state                 = $container->get( 'onboarding.state' );
		$login_seller_endpoint = $container->get( 'onboarding.endpoint.login-seller' );
		return new OnboardingAssets(
			$container->get( 'onboarding.url' ),
			$container->get( 'ppcp.asset-version' ),
			$state,
			$container->get( 'settings.environment' ),
			$login_seller_endpoint,
			$container->get( 'wcgateway.current-ppcp-settings-page-id' )
		);
	},

	'onboarding.url'                     => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules/ppcp-onboarding/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'onboarding.endpoint.login-seller'   => static function ( ContainerInterface $container ) : LoginSellerEndpoint {

		$request_data            = $container->get( 'button.request-data' );
		$login_seller_production = $container->get( 'api.endpoint.login-seller-production' );
		$login_seller_sandbox    = $container->get( 'api.endpoint.login-seller-sandbox' );
		$partner_referrals_data  = $container->get( 'api.repository.partner-referrals-data' );
		$settings                = $container->get( 'wcgateway.settings' );
		$cache                   = $container->get( 'api.paypal-bearer-cache' );
		$logger                  = $container->get( 'woocommerce.logger.woocommerce' );
		return new LoginSellerEndpoint(
			$request_data,
			$login_seller_production,
			$login_seller_sandbox,
			$partner_referrals_data,
			$settings,
			$cache,
			$logger,
			new Cache( 'ppcp-client-credentials-cache' )
		);
	},
	'onboarding.endpoint.pui'            => static function( ContainerInterface $container ) : UpdateSignupLinksEndpoint {
		return new UpdateSignupLinksEndpoint(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'button.request-data' ),
			$container->get( 'onboarding.signup-link-cache' ),
			$container->get( 'onboarding.render' ),
			$container->get( 'onboarding.signup-link-ids' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'onboarding.signup-link-cache'       => static function( ContainerInterface $container ): Cache {
		return new Cache( 'ppcp-paypal-signup-link' );
	},
	'onboarding.signup-link-ids'         => static function ( ContainerInterface $container ): array {
		return array(
			'production-ppcp',
			'production-express_checkout',
			'sandbox-ppcp',
			'sandbox-express_checkout',
		);
	},
	'onboarding.render-send-only-notice' => static function( ContainerInterface $container ) {
		return new OnboardingSendOnlyNoticeRenderer(
			$container->get( 'wcgateway.send-only-message' )
		);
	},
	'onboarding.render'                  => static function ( ContainerInterface $container ) : OnboardingRenderer {
		$partner_referrals         = $container->get( 'api.endpoint.partner-referrals-production' );
		$partner_referrals_sandbox = $container->get( 'api.endpoint.partner-referrals-sandbox' );
		$partner_referrals_data    = $container->get( 'api.repository.partner-referrals-data' );
		$settings                  = $container->get( 'wcgateway.settings' );
		$signup_link_cache         = $container->get( 'onboarding.signup-link-cache' );
		$logger                    = $container->get( 'woocommerce.logger.woocommerce' );
		return new OnboardingRenderer(
			$settings,
			$partner_referrals,
			$partner_referrals_sandbox,
			$partner_referrals_data,
			$signup_link_cache,
			$logger
		);
	},
	'onboarding.render-options'          => static function ( ContainerInterface $container ) : OnboardingOptionsRenderer {
		return new OnboardingOptionsRenderer(
			$container->get( 'onboarding.url' ),
			$container->get( 'api.shop.country' ),
			$container->get( 'wcgateway.settings' )
		);
	},
	'onboarding.rest'                    => static function( $container ) : OnboardingRESTController {
		return new OnboardingRESTController( $container );
	},
);
