<?php
/**
 * The button module services.
 *
 * @package Inpsyde\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Button\Assets\DisabledSmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButtonInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use Inpsyde\PayPalCommerce\Button\Helper\MessagesApply;
use Inpsyde\PayPalCommerce\Button\Helper\ThreeDSecure;
use Inpsyde\PayPalCommerce\Onboarding\Environment;
use Inpsyde\PayPalCommerce\Onboarding\State;

return array(
	'button.client_id'                  => static function ( ContainerInterface $container ): string {

		$settings = $container->get( 'wcgateway.settings' );
		$client_id = $settings->has( 'client_id' ) ? $settings->get( 'client_id' ) : '';
		if ( $client_id ) {
			return $client_id;
		}

		$env = $container->get( 'onboarding.environment' );
		/**
		 * The environment.
		 *
		 * @var Environment $env
		 */

		/**
		 * ToDo: Add production platform client Id.
		 */
		return $env->current_environment_is( Environment::SANDBOX ) ?
			'AQB97CzMsd58-It1vxbcDAGvMuXNCXRD9le_XUaMlHB_U7XsU9IiItBwGQOtZv9sEeD6xs2vlIrL4NiD' : '';
	},
	'button.smart-button'               => static function ( ContainerInterface $container ): SmartButtonInterface {

		$state = $container->get( 'onboarding.state' );
		/**
		 * The state.
		 *
		 * @var State $state
		 */
		if ( $state->current_state() < State::STATE_PROGRESSIVE ) {
			return new DisabledSmartButton();
		}
		$settings           = $container->get( 'wcgateway.settings' );
		$paypal_disabled     = ! $settings->has( 'enabled' ) || ! $settings->get( 'enabled' );
		$credit_card_disabled = ! $settings->has( 'dcc_gateway_enabled' ) || ! $settings->get( 'dcc_gateway_enabled' );
		if ( $paypal_disabled && $credit_card_disabled ) {
			return new DisabledSmartButton();
		}
		$payee_repository = $container->get( 'api.repository.payee' );
		$identity_token   = $container->get( 'api.endpoint.identity-token' );
		$payer_factory    = $container->get( 'api.factory.payer' );
		$request_data     = $container->get( 'button.request-data' );

		$client_id           = $container->get( 'button.client_id' );
		$dcc_applies         = $container->get( 'api.helpers.dccapplies' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$messages_apply      = $container->get( 'button.helper.messages-apply' );
		return new SmartButton(
			$container->get( 'button.url' ),
			$container->get( 'session.handler' ),
			$settings,
			$payee_repository,
			$identity_token,
			$payer_factory,
			$client_id,
			$request_data,
			$dcc_applies,
			$subscription_helper,
			$messages_apply
		);
	},
	'button.url'                        => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules.local/ppcp-button/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-commerce-gateway.php'
		);
	},
	'button.request-data'               => static function ( ContainerInterface $container ): RequestData {
		return new RequestData();
	},
	'button.endpoint.change-cart'       => static function ( ContainerInterface $container ): ChangeCartEndpoint {
		if ( ! \WC()->cart ) {
			throw new RuntimeException( 'cant initialize endpoint at this moment' );
		}
		$cart        = WC()->cart;
		$shipping    = WC()->shipping();
		$request_data = $container->get( 'button.request-data' );
		$repository  = $container->get( 'api.repository.cart' );
		$data_store   = \WC_Data_Store::load( 'product' );
		return new ChangeCartEndpoint( $cart, $shipping, $request_data, $repository, $data_store );
	},
	'button.endpoint.create-order'      => static function ( ContainerInterface $container ): CreateOrderEndpoint {
		$request_data       = $container->get( 'button.request-data' );
		$repository        = $container->get( 'api.repository.cart' );
		$order_endpoint         = $container->get( 'api.endpoint.order' );
		$payer_factory      = $container->get( 'api.factory.payer' );
		$session_handler    = $container->get( 'session.handler' );
		$settings          = $container->get( 'wcgateway.settings' );
		$early_order_handler = $container->get( 'button.helper.early-order-handler' );
		return new CreateOrderEndpoint(
			$request_data,
			$repository,
			$order_endpoint,
			$payer_factory,
			$session_handler,
			$settings,
			$early_order_handler
		);
	},
	'button.helper.early-order-handler' => static function ( ContainerInterface $container ) : EarlyOrderHandler {

		$state          = $container->get( 'onboarding.state' );
		$order_processor = $container->get( 'wcgateway.order-processor' );
		$session_handler = $container->get( 'session.handler' );
		$prefix         = $container->get( 'api.prefix' );
		return new EarlyOrderHandler( $state, $order_processor, $session_handler, $prefix );
	},
	'button.endpoint.approve-order'     => static function ( ContainerInterface $container ): ApproveOrderEndpoint {
		$request_data    = $container->get( 'button.request-data' );
		$order_endpoint      = $container->get( 'api.endpoint.order' );
		$session_handler = $container->get( 'session.handler' );
		$three_d_secure   = $container->get( 'button.helper.three-d-secure' );
		return new ApproveOrderEndpoint( $request_data, $order_endpoint, $session_handler, $three_d_secure );
	},
	'button.endpoint.data-client-id'    => static function( ContainerInterface $container ) : DataClientIdEndpoint {
		$request_data   = $container->get( 'button.request-data' );
		$identity_token = $container->get( 'api.endpoint.identity-token' );
		return new DataClientIdEndpoint(
			$request_data,
			$identity_token
		);
	},
	'button.helper.three-d-secure'      => static function ( ContainerInterface $container ): ThreeDSecure {
		return new ThreeDSecure();
	},
	'button.helper.messages-apply'      => static function ( ContainerInterface $container ): MessagesApply {
		return new MessagesApply();
	},
);
