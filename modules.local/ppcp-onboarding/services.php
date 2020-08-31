<?php
/**
 * The onboarding module services.
 *
 * @package Inpsyde\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use Inpsyde\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use WpOop\TransientCache\CachePoolFactory;

return array(

	'api.host'                         => static function ( ContainerInterface $container ): string {
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
			return 'http://connect-woo.wpcust.com';
		}
		return 'http://connect-woo.wpcust.com';

	},
	'api.paypal-host'                  => function( ContainerInterface $container ) : string {
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

	'api.bearer'                       => static function ( ContainerInterface $container ): Bearer {

		$state = $container->get( 'onboarding.state' );
		if ( $state->currentState() < State::STATE_ONBOARDED ) {
			return new ConnectBearer();
		}
		global $wpdb;
		$cache_pool_factory = new CachePoolFactory( $wpdb );
		$pool               = $cache_pool_factory->createCachePool( 'ppcp-token' );
		$key                = $container->get( 'api.key' );
		$secret             = $container->get( 'api.secret' );

		$host   = $container->get( 'api.host' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new PayPalBearer(
			$pool,
			$host,
			$key,
			$secret,
			$logger
		);
	},
	'onboarding.state'                 => function( ContainerInterface $container ) : State {
		$environment = $container->get( 'onboarding.environment' );
		$settings    = $container->get( 'wcgateway.settings' );
		return new State( $environment, $settings );
	},
	'onboarding.environment'           => function( ContainerInterface $container ) : Environment {
		$settings = $container->get( 'wcgateway.settings' );
		return new Environment( $settings );
	},

	'onboarding.assets'                => function( ContainerInterface $container ) : OnboardingAssets {
		$state                 = $container->get( 'onboarding.state' );
		$login_seller_endpoint = $container->get( 'onboarding.endpoint.login-seller' );
		return new OnboardingAssets(
			$container->get( 'onboarding.url' ),
			$state,
			$login_seller_endpoint
		);
	},

	'onboarding.url'                   => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules.local/ppcp-onboarding/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-commerce-gateway.php'
		);
	},

	'onboarding.endpoint.login-seller' => static function ( ContainerInterface $container ) : LoginSellerEndpoint {

		$request_data           = $container->get( 'button.request-data' );
		$login_seller           = $container->get( 'api.endpoint.login-seller' );
		$partner_referrals_data = $container->get( 'api.repository.partner-referrals-data' );
		$settings               = $container->get( 'wcgateway.settings' );

		global $wpdb;
		$cache_pool_factory = new CachePoolFactory( $wpdb );
		$pool         = $cache_pool_factory->createCachePool( 'ppcp-token' );
		return new LoginSellerEndpoint(
			$request_data,
			$login_seller,
			$partner_referrals_data,
			$settings,
			$pool
		);
	},
	'onboarding.render'                => static function ( ContainerInterface $container ) : OnboardingRenderer {

		$partner_referrals = $container->get( 'api.endpoint.partner-referrals' );
		return new OnboardingRenderer(
			$partner_referrals
		);
	},
);
