<?php
/**
 * The button module services.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\DisabledSmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Endpoint\StartPayPalVaultingEndpoint;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;

return array(
	'button.client_id'                            => static function ( ContainerInterface $container ): string {

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
	'button.client_id_for_admin'                  => static function ( ContainerInterface $container ): string {
		$dummy_ids = array(
			'AU' => 'AQ5yx7aGjD0fWKDMQrngSznDlfSfWvio9j1fCeuLC5foFoimaM_d1AbeRmEvc9jVuJU7BbopMSd4aNPG',
			'DE' => 'AYZigu5BLwbJ_QKNasp_1k0kJUon7NRyazh8Lo-bthJuKetzXRBEzUlbeUIvUfsBxrcN-K0UEk-V6Lq9',
			'ES' => 'Aa3A3B4MvF2_Xwoj7kG_4qI_hh2pRmuvjRefIgp8B0HSIIGnqsx2Wd8IGOvhyX1G2WLOMl0xGJsiHpXl',
			'FR' => 'AYIb1W_LbKGlgpOwk64dGk8PPQnIx0H4wdmQfdNt8M6cCaAsSgQ6O-TwTDF6y9Jpp_5BxtHoYYMQDCb5',
			'GB' => 'AZvAtq7qHoM0yefv-ptnmAvN3gDm9oNj2A7oDqhw_d-pEdWW5q68b7_xd-U2-dQs_kipnmLhV3-7vWkU',
			'IT' => 'AZm7Rq3sLabDbtq2vRCRVtMRJ09SLi6HeoRy4JuUdFQ6j0D_x-wEZtRzjBhY4NzAcFC_T7GTBdvSYEwK',
			'US' => 'Ad5dKzVsWZvPD3YgjhZ24LKNKmJqg2Xo3uKx7yuazPiARFc9xJWg1mM-vy-eJhb1V7xn5mPnp_QjAMaM',
		);

		$shop_country = $container->get( 'api.shop.country' );

		return $dummy_ids[ $shop_country ] ?? $container->get( 'button.client_id' );
	},
	'button.smart-button'                         => static function ( ContainerInterface $container ): SmartButtonInterface {

		$state = $container->get( 'onboarding.state' );
		/**
		 * The state.
		 *
		 * @var State $state
		 */
		if ( $state->current_state() !== State::STATE_ONBOARDED ) {
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
		$payment_token_repository = $container->get( 'vaulting.repository.payment-token' );
		$settings_status = $container->get( 'wcgateway.settings.status' );
		$currency = $container->get( 'api.shop.currency' );
		return new SmartButton(
			$container->get( 'button.url' ),
			$container->get( 'ppcp.asset-version' ),
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
			$settings_status,
			$currency,
			$container->get( 'wcgateway.all-funding-sources' ),
			$container->get( 'button.basic-checkout-validation-enabled' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'button.url'                                  => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules/ppcp-button/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'button.request-data'                         => static function ( ContainerInterface $container ): RequestData {
		return new RequestData();
	},
	'button.endpoint.change-cart'                 => static function ( ContainerInterface $container ): ChangeCartEndpoint {
		if ( ! \WC()->cart ) {
			throw new RuntimeException( 'cant initialize endpoint at this moment' );
		}
		$cart        = WC()->cart;
		$shipping    = WC()->shipping();
		$request_data = $container->get( 'button.request-data' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$data_store   = \WC_Data_Store::load( 'product' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		return new ChangeCartEndpoint( $cart, $shipping, $request_data, $purchase_unit_factory, $data_store, $logger );
	},
	'button.endpoint.create-order'                => static function ( ContainerInterface $container ): CreateOrderEndpoint {
		$request_data          = $container->get( 'button.request-data' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$order_endpoint        = $container->get( 'api.endpoint.order' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		$session_handler       = $container->get( 'session.handler' );
		$settings              = $container->get( 'wcgateway.settings' );
		$early_order_handler   = $container->get( 'button.helper.early-order-handler' );
		$registration_needed    = $container->get( 'button.current-user-must-register' );
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		return new CreateOrderEndpoint(
			$request_data,
			$purchase_unit_factory,
			$container->get( 'api.factory.shipping-preference' ),
			$order_endpoint,
			$payer_factory,
			$session_handler,
			$settings,
			$early_order_handler,
			$registration_needed,
			$container->get( 'wcgateway.settings.card_billing_data_mode' ),
			$container->get( 'button.early-wc-checkout-validation-enabled' ),
			$logger
		);
	},
	'button.helper.early-order-handler'           => static function ( ContainerInterface $container ) : EarlyOrderHandler {

		$state          = $container->get( 'onboarding.state' );
		$order_processor = $container->get( 'wcgateway.order-processor' );
		$session_handler = $container->get( 'session.handler' );
		$prefix         = $container->get( 'api.prefix' );
		return new EarlyOrderHandler( $state, $order_processor, $session_handler, $prefix );
	},
	'button.endpoint.approve-order'               => static function ( ContainerInterface $container ): ApproveOrderEndpoint {
		$request_data    = $container->get( 'button.request-data' );
		$order_endpoint  = $container->get( 'api.endpoint.order' );
		$session_handler = $container->get( 'session.handler' );
		$three_d_secure  = $container->get( 'button.helper.three-d-secure' );
		$settings        = $container->get( 'wcgateway.settings' );
		$dcc_applies     = $container->get( 'api.helpers.dccapplies' );
		$order_helper = $container->get( 'api.order-helper' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		return new ApproveOrderEndpoint(
			$request_data,
			$order_endpoint,
			$session_handler,
			$three_d_secure,
			$settings,
			$dcc_applies,
			$order_helper,
			$logger
		);
	},
	'button.endpoint.data-client-id'              => static function( ContainerInterface $container ) : DataClientIdEndpoint {
		$request_data   = $container->get( 'button.request-data' );
		$identity_token = $container->get( 'api.endpoint.identity-token' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new DataClientIdEndpoint(
			$request_data,
			$identity_token,
			$logger
		);
	},
	'button.endpoint.vault-paypal'                => static function( ContainerInterface $container ) : StartPayPalVaultingEndpoint {
		return new StartPayPalVaultingEndpoint(
			$container->get( 'button.request-data' ),
			$container->get( 'api.endpoint.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'button.helper.three-d-secure'                => static function ( ContainerInterface $container ): ThreeDSecure {
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new ThreeDSecure( $logger );
	},
	'button.helper.messages-apply'                => static function ( ContainerInterface $container ): MessagesApply {
		return new MessagesApply(
			$container->get( 'api.shop.country' )
		);
	},

	'button.is-logged-in'                         => static function ( ContainerInterface $container ): bool {
		return is_user_logged_in();
	},
	'button.registration-required'                => static function ( ContainerInterface $container ): bool {
		return WC()->checkout()->is_registration_required();
	},
	'button.current-user-must-register'           => static function ( ContainerInterface $container ): bool {
		return ! $container->get( 'button.is-logged-in' ) &&
			$container->get( 'button.registration-required' );
	},

	'button.basic-checkout-validation-enabled'    => static function ( ContainerInterface $container ): bool {
		/**
		 * The filter allowing to disable the basic client-side validation of the checkout form
		 * when the PayPal button is clicked.
		 */
		return (bool) apply_filters( 'woocommerce_paypal_payments_basic_checkout_validation_enabled', false );
	},
	'button.early-wc-checkout-validation-enabled' => static function ( ContainerInterface $container ): bool {
		/**
		 * The filter allowing to disable the WC validation of the checkout form
		 * when the PayPal button is clicked.
		 * The validation is triggered in a non-standard way and may cause issues on some sites.
		 */
		return (bool) apply_filters( 'woocommerce_paypal_payments_early_wc_checkout_validation_enabled', true );
	},
);
