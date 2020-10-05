<?php
/**
 * The onboarding module services.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use Dhii\Data\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use WpOop\TransientCache\CachePoolFactory;

return array(

	'api.host'                         => static function ( $container ): string {
		$state       = $container->get( 'onboarding.state' );
		$environment = $container->get( 'onboarding.environment' );

		// ToDo: Correct the URLs.
		/**
		 * The Environment and State variables.
		 *
		 * @var Environment $environment
		 * @var State $state
		 */
		if ( $state->current_state() >= State::STATE_ONBOARDED ) {
			if ( $environment->current_environment_is( Environment::SANDBOX ) ) {
				return 'https://api.sandbox.paypal.com';
			}
			return 'https://api.sandbox.paypal.com';
		}

		// ToDo: Real connect.woocommerce.com.
		if ( $environment->current_environment_is( Environment::SANDBOX ) ) {
			return 'http://connect-woo.wpcust.com/ppcsandbox';
		}
		return 'http://connect-woo.wpcust.com/ppc';

	},
	'api.paypal-host'                  => function( $container ) : string {
		$environment = $container->get( 'onboarding.environment' );
		/**
		 * The current environment.
		 *
		 * @var Environment $environment
		 */
		if ( $environment->current_environment_is( Environment::SANDBOX ) ) {
			return 'https://api.sandbox.paypal.com';
		}
		return 'https://api.paypal.com';
	},

	'api.bearer'                       => static function ( $container ): Bearer {

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
		return new PayPalBearer(
			$cache,
			$host,
			$key,
			$secret,
			$logger
		);
	},
	'onboarding.state'                 => function( $container ) : State {
		$environment = $container->get( 'onboarding.environment' );
		$settings    = $container->get( 'wcgateway.settings' );
		return new State( $environment, $settings );
	},
	'onboarding.environment'           => function( $container ) : Environment {
		$settings = $container->get( 'wcgateway.settings' );
		return new Environment( $settings );
	},

	'onboarding.assets'                => function( $container ) : OnboardingAssets {
		$state                 = $container->get( 'onboarding.state' );
		$login_seller_endpoint = $container->get( 'onboarding.endpoint.login-seller' );
		return new OnboardingAssets(
			$container->get( 'onboarding.url' ),
			$state,
			$login_seller_endpoint
		);
	},

	'onboarding.url'                   => static function ( $container ): string {
		return plugins_url(
			'/modules/ppcp-onboarding/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-commerce-gateway.php'
		);
	},

	'onboarding.endpoint.login-seller' => static function ( $container ) : LoginSellerEndpoint {

		$request_data           = $container->get( 'button.request-data' );
		$login_seller           = $container->get( 'api.endpoint.login-seller' );
		$partner_referrals_data = $container->get( 'api.repository.partner-referrals-data' );
		$settings               = $container->get( 'wcgateway.settings' );

		$cache = new Cache( 'ppcp-paypal-bearer' );
		return new LoginSellerEndpoint(
			$request_data,
			$login_seller,
			$partner_referrals_data,
			$settings,
			$cache
		);
	},
	'onboarding.render'                => static function ( $container ) : OnboardingRenderer {

		$partner_referrals = $container->get( 'api.endpoint.partner-referrals' );
		return new OnboardingRenderer(
			$partner_referrals
		);
	},
);
