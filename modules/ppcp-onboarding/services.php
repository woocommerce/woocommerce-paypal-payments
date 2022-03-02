<?php
/**
 * The onboarding module services.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingOptionsRenderer;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use WooCommerce\PayPalCommerce\Onboarding\OnboardingRESTController;

return array(
	'api.sandbox-host'                          => static function ( ContainerInterface $container ): string {

		$state       = $container->get( 'onboarding.state' );

		/**
		 * The State object.
		 *
		 * @var State $state
		 */
		if ( $state->current_state() >= State::STATE_ONBOARDED ) {
			return PAYPAL_SANDBOX_API_URL;
		}
		return CONNECT_WOO_SANDBOX_URL;
	},
	'api.production-host'                       => static function ( ContainerInterface $container ): string {

		$state       = $container->get( 'onboarding.state' );

		/**
		 * The Environment and State variables.
		 *
		 * @var Environment $environment
		 * @var State $state
		 */
		if ( $state->current_state() >= State::STATE_ONBOARDED ) {
			return PAYPAL_API_URL;
		}
		return CONNECT_WOO_URL;
	},
	'api.host'                                  => static function ( ContainerInterface $container ): string {
		$environment = $container->get( 'onboarding.environment' );

		/**
		 * The Environment and State variables.
		 *
		 * @var Environment $environment
		 */
		return $environment->current_environment_is( Environment::SANDBOX )
			? (string) $container->get( 'api.sandbox-host' ) : (string) $container->get( 'api.production-host' );

	},
	'api.paypal-host-production'                => static function( ContainerInterface $container ) : string {
		return PAYPAL_API_URL;
	},
	'api.paypal-host-sandbox'                   => static function( ContainerInterface $container ) : string {
		return PAYPAL_SANDBOX_API_URL;
	},
	'api.partner_merchant_id-production'        => static function( ContainerInterface $container ) : string {
		return CONNECT_WOO_MERCHANT_ID;
	},
	'api.partner_merchant_id-sandbox'           => static function( ContainerInterface $container ) : string {
		return CONNECT_WOO_SANDBOX_MERCHANT_ID;
	},
	'api.paypal-host'                           => function( ContainerInterface $container ) : string {
		$environment = $container->get( 'onboarding.environment' );
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

	'api.bearer'                                => static function ( ContainerInterface $container ): Bearer {

		$state = $container->get( 'onboarding.state' );

		/**
		 * The State.
		 *
		 * @var State $state
		 */
		if ( $state->current_state() < State::STATE_ONBOARDED ) {
			return new ConnectBearer();
		}
		$cache  = new Cache( 'ppcp-paypal-bearer' );
		$key    = $container->get( 'api.key' );
		$secret = $container->get( 'api.secret' );
		$host   = $container->get( 'api.host' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$settings = $container->get( 'wcgateway.settings' );
		return new PayPalBearer(
			$cache,
			$host,
			$key,
			$secret,
			$logger,
			$settings
		);
	},
	'onboarding.state'                          => function( ContainerInterface $container ) : State {
		$settings    = $container->get( 'wcgateway.settings' );
		return new State( $settings );
	},
	'onboarding.environment'                    => function( ContainerInterface $container ) : Environment {
		$settings = $container->get( 'wcgateway.settings' );
		return new Environment( $settings );
	},

	'onboarding.assets'                         => function( ContainerInterface $container ) : OnboardingAssets {
		$state                 = $container->get( 'onboarding.state' );
		$login_seller_endpoint = $container->get( 'onboarding.endpoint.login-seller' );
		return new OnboardingAssets(
			$container->get( 'onboarding.url' ),
			$container->get( 'ppcp.asset-version' ),
			$state,
			$container->get( 'onboarding.environment' ),
			$login_seller_endpoint,
			$container->get( 'wcgateway.current-ppcp-settings-page-id' )
		);
	},

	'onboarding.url'                            => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules/ppcp-onboarding/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'api.endpoint.login-seller-production'      => static function ( ContainerInterface $container ) : LoginSeller {

		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new LoginSeller(
			$container->get( 'api.paypal-host-production' ),
			$container->get( 'api.partner_merchant_id-production' ),
			$logger
		);
	},

	'api.endpoint.login-seller-sandbox'         => static function ( ContainerInterface $container ) : LoginSeller {

		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new LoginSeller(
			$container->get( 'api.paypal-host-sandbox' ),
			$container->get( 'api.partner_merchant_id-sandbox' ),
			$logger
		);
	},

	'onboarding.endpoint.login-seller'          => static function ( ContainerInterface $container ) : LoginSellerEndpoint {

		$request_data            = $container->get( 'button.request-data' );
		$login_seller_production = $container->get( 'api.endpoint.login-seller-production' );
		$login_seller_sandbox    = $container->get( 'api.endpoint.login-seller-sandbox' );
		$partner_referrals_data  = $container->get( 'api.repository.partner-referrals-data' );
		$settings                = $container->get( 'wcgateway.settings' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );

		$cache = new Cache( 'ppcp-paypal-bearer' );
		return new LoginSellerEndpoint(
			$request_data,
			$login_seller_production,
			$login_seller_sandbox,
			$partner_referrals_data,
			$settings,
			$cache,
			$logger
		);
	},
	'api.endpoint.partner-referrals-sandbox'    => static function ( ContainerInterface $container ) : PartnerReferrals {

		return new PartnerReferrals(
			CONNECT_WOO_SANDBOX_URL,
			new ConnectBearer(),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.endpoint.partner-referrals-production' => static function ( ContainerInterface $container ) : PartnerReferrals {

		return new PartnerReferrals(
			CONNECT_WOO_URL,
			new ConnectBearer(),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'onboarding.render'                         => static function ( ContainerInterface $container ) : OnboardingRenderer {

		$partner_referrals         = $container->get( 'api.endpoint.partner-referrals-production' );
		$partner_referrals_sandbox = $container->get( 'api.endpoint.partner-referrals-sandbox' );
		$partner_referrals_data    = $container->get( 'api.repository.partner-referrals-data' );
		$settings                  = $container->get( 'wcgateway.settings' );
		return new OnboardingRenderer(
			$settings,
			$partner_referrals,
			$partner_referrals_sandbox,
			$partner_referrals_data
		);
	},
	'onboarding.render-options'                 => static function ( ContainerInterface $container ) : OnboardingOptionsRenderer {
		return new OnboardingOptionsRenderer(
			$container->get( 'onboarding.url' ),
			$container->get( 'api.shop.country' )
		);
	},
	'onboarding.rest'                           => static function( $container ) : OnboardingRESTController {
		return new OnboardingRESTController( $container );
	},
);
