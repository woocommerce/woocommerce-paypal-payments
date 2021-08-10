<?php
/**
 * The button module services.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button;

use WooCommerce\PayPalCommerce\Button\Assets\DisabledSmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;

return array(
	'button.client_id'                  => static function ( $container ): string {

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

		return $env->current_environment_is( Environment::SANDBOX ) ?
			CONNECT_WOO_SANDBOX_CLIENT_ID : CONNECT_WOO_CLIENT_ID;
	},
	'button.smart-button'               => static function ( $container ): SmartButtonInterface {

		$state = $container->get( 'onboarding.state' );
		/**
		 * The state.
		 *
		 * @var State $state
		 */
		if ( $state->current_state() <= State::STATE_PROGRESSIVE ) {
			return new DisabledSmartButton();
		}
		$settings           = $container->get( 'wcgateway.settings' );
		$paypal_disabled     = ! $settings->has( 'enabled' ) || ! $settings->get( 'enabled' );
		if ( $paypal_disabled ) {
			return new DisabledSmartButton();
		}
		$payer_factory    = $container->get( 'api.factory.payer' );
		$request_data     = $container->get( 'button.request-data' );

		$client_id           = $container->get( 'button.client_id' );
		$dcc_applies         = $container->get( 'api.helpers.dccapplies' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$messages_apply      = $container->get( 'button.helper.messages-apply' );
		$environment         = $container->get( 'onboarding.environment' );
		$payment_token_repository = $container->get( 'subscription.repository.payment-token' );
		$settings_status = $container->get( 'wcgateway.settings.status' );
		return new SmartButton(
			$container->get( 'button.url' ),
			$container->get( 'session.handler' ),
			$settings,
			$payer_factory,
			$client_id,
			$request_data,
			$dcc_applies,
			$subscription_helper,
			$messages_apply,
			$environment,
			$payment_token_repository,
			$settings_status
		);
	},
	'button.url'                        => static function ( $container ): string {
		return plugins_url(
			'/modules/ppcp-button/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'button.request-data'               => static function ( $container ): RequestData {
		return new RequestData();
	},
	'button.endpoint.change-cart'       => static function ( $container ): ChangeCartEndpoint {
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
	'button.endpoint.create-order'      => static function ( $container ): CreateOrderEndpoint {
		$request_data          = $container->get( 'button.request-data' );
		$cart_repository       = $container->get( 'api.repository.cart' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$order_endpoint        = $container->get( 'api.endpoint.order' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		$session_handler       = $container->get( 'session.handler' );
		$settings              = $container->get( 'wcgateway.settings' );
		$early_order_handler   = $container->get( 'button.helper.early-order-handler' );
		return new CreateOrderEndpoint(
			$request_data,
			$cart_repository,
			$purchase_unit_factory,
			$order_endpoint,
			$payer_factory,
			$session_handler,
			$settings,
			$early_order_handler
		);
	},
	'button.helper.early-order-handler' => static function ( $container ) : EarlyOrderHandler {

		$state          = $container->get( 'onboarding.state' );
		$order_processor = $container->get( 'wcgateway.order-processor' );
		$session_handler = $container->get( 'session.handler' );
		$prefix         = $container->get( 'api.prefix' );
		return new EarlyOrderHandler( $state, $order_processor, $session_handler, $prefix );
	},
	'button.endpoint.approve-order'     => static function ( $container ): ApproveOrderEndpoint {
		$request_data    = $container->get( 'button.request-data' );
		$order_endpoint  = $container->get( 'api.endpoint.order' );
		$session_handler = $container->get( 'session.handler' );
		$three_d_secure  = $container->get( 'button.helper.three-d-secure' );
		$settings        = $container->get( 'wcgateway.settings' );
		$dcc_applies     = $container->get( 'api.helpers.dccapplies' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		return new ApproveOrderEndpoint(
			$request_data,
			$order_endpoint,
			$session_handler,
			$three_d_secure,
			$settings,
			$dcc_applies,
			$logger
		);
	},
	'button.endpoint.data-client-id'    => static function( $container ) : DataClientIdEndpoint {
		$request_data   = $container->get( 'button.request-data' );
		$identity_token = $container->get( 'api.endpoint.identity-token' );
		return new DataClientIdEndpoint(
			$request_data,
			$identity_token
		);
	},
	'button.helper.three-d-secure'      => static function ( $container ): ThreeDSecure {
		return new ThreeDSecure();
	},
	'button.helper.messages-apply'      => static function ( $container ): MessagesApply {
		return new MessagesApply();
	},
);
