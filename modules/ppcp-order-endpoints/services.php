<?php
/**
 * The order endpoints module services.
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints;

use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\FrontendLogEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'order-endpoints.request-data'                         => static function ( ContainerInterface $container ): RequestData {
		return new RequestData();
	},
	'order-endpoints.endpoint.change-cart'                 => static function ( ContainerInterface $container ): ChangeCartEndpoint {
		if ( ! \WC()->cart ) {
			throw new RuntimeException( 'cant initialize endpoint at this moment' );
		}
		$cart                  = WC()->cart;
		$shipping              = WC()->shipping();
		$request_data          = $container->get( 'order-endpoints.request-data' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$cart_products         = $container->get( 'order-endpoints.helper.cart-products' );
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		return new ChangeCartEndpoint( $cart, $shipping, $request_data, $purchase_unit_factory, $cart_products, $logger );
	},
	'order-endpoints.endpoint.create-order'                => static function ( ContainerInterface $container ): CreateOrderEndpoint {
		$request_data          = $container->get( 'order-endpoints.request-data' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$order_endpoint        = $container->get( 'api.endpoint.order' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		$session_handler       = $container->get( 'session.handler' );
		$settings_provider     = $container->get( 'settings.settings-provider' );
		$early_order_handler   = $container->get( 'order-endpoints.helper.early-order-handler' );
		$registration_needed   = $container->get( 'order-endpoints.current-user-must-register' );
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		return new CreateOrderEndpoint(
			$request_data,
			$purchase_unit_factory,
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'api.factory.return-url' ),
			$container->get( 'api.factory.contact-preference' ),
			$container->get( 'wcgateway.builder.experience-context' ),
			$order_endpoint,
			$payer_factory,
			$session_handler,
			$settings_provider,
			$early_order_handler,
			$container->get( 'button.session.factory.card-data' ),
			$container->get( 'button.session.storage.card-data.transient' ),
			$registration_needed,
			$container->get( 'wcgateway.settings.card_billing_data_mode' ),
			$container->get( 'order-endpoints.early-wc-checkout-validation-enabled' ),
			$container->get( 'order-endpoints.pay-now-contexts' ),
			$container->get( 'order-endpoints.handle-shipping-in-paypal' ),
			$container->get( 'wcgateway.server-side-shipping-callback-enabled' ),
			$container->get( 'wcgateway.funding-sources-without-redirect' ),
			$logger
		);
	},
	'order-endpoints.endpoint.approve-order'               => static function ( ContainerInterface $container ): ApproveOrderEndpoint {
		$request_data         = $container->get( 'order-endpoints.request-data' );
		$order_endpoint       = $container->get( 'api.endpoint.order' );
		$session_handler      = $container->get( 'session.handler' );
		$three_d_secure       = $container->get( 'button.helper.three-d-secure' );
		$settings_provider    = $container->get( 'settings.settings-provider' );
		$settings_model       = $container->get( 'settings.data.settings' );
		$dcc_applies          = $container->get( 'api.helpers.dccapplies' );
		$order_helper         = $container->get( 'api.order-helper' );
		$final_review_enabled = $container->get( 'blocks.settings.final_review_enabled' );
		$wc_order_creator     = $container->get( 'order-endpoints.helper.wc-order-creator' );
		$gateway              = $container->get( 'wcgateway.paypal-gateway' );
		$logger               = $container->get( 'woocommerce.logger.woocommerce' );
		$context              = $container->get( 'button.helper.context' );

		return new ApproveOrderEndpoint(
			$request_data,
			$order_endpoint,
			$session_handler,
			$three_d_secure,
			$settings_provider,
			$settings_model,
			$dcc_applies,
			$order_helper,
			$final_review_enabled,
			$gateway,
			$wc_order_creator,
			$logger,
			$context
		);
	},
	'order-endpoints.endpoint.update-shipping'             => static function ( ContainerInterface $container ): UpdateShippingEndpoint {
		return new UpdateShippingEndpoint(
			$container->get( 'order-endpoints.request-data' ),
			$container->get( 'api.endpoint.order' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'session.handler' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'order-endpoints.endpoint.frontend-log'                => static function ( ContainerInterface $container ): FrontendLogEndpoint {
		return new FrontendLogEndpoint(
			$container->get( 'order-endpoints.request-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'order-endpoints.is-logged-in'                         => static function ( ContainerInterface $container ): bool {
		return is_user_logged_in();
	},
	'order-endpoints.registration-required'                => static function ( ContainerInterface $container ): bool {
		return WC()->checkout()->is_registration_required();
	},
	'order-endpoints.current-user-must-register'           => static function ( ContainerInterface $container ): bool {
		return ! $container->get( 'order-endpoints.is-logged-in' ) &&
			$container->get( 'order-endpoints.registration-required' );
	},
	'order-endpoints.early-wc-checkout-validation-enabled' => static function ( ContainerInterface $container ): bool {
		/**
		 * The filter allowing to disable the WC validation of the checkout form
		 * when the PayPal button is clicked.
		 * The validation is triggered in a non-standard way and may cause issues on some sites.
		 */
		return (bool) apply_filters( 'woocommerce_paypal_payments_early_wc_checkout_validation_enabled', true );
	},
	'order-endpoints.pay-now-contexts'                     => static function ( ContainerInterface $container ): array {
		$defaults = array( 'checkout', 'pay-now' );

		if ( $container->get( 'order-endpoints.handle-shipping-in-paypal' ) ) {
			return array_merge( $defaults, array( 'cart', 'product', 'mini-cart' ) );
		}

		return $defaults;
	},

	/**
	 * If true, the shipping methods are sent to PayPal allowing the customer to select it inside the popup.
	 * May result in slower popup performance, additional loading.
	 */
	'order-endpoints.handle-shipping-in-paypal'            => static function ( ContainerInterface $container ): bool {
		return ! $container->get( 'blocks.settings.final_review_enabled' );
	},
	'order-endpoints.helper.cart-products'                 => static function ( ContainerInterface $container ): CartProductsHelper {
		$data_store = \WC_Data_Store::load( 'product' );
		return new CartProductsHelper( $data_store );
	},
	'order-endpoints.helper.early-order-handler'           => static function ( ContainerInterface $container ): EarlyOrderHandler {
		return new EarlyOrderHandler(
			$container->get( 'settings.flag.is-connected' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'session.handler' )
		);
	},
	'order-endpoints.helper.wc-order-creator'              => static function ( ContainerInterface $container ): WooCommerceOrderCreator {
		return new WooCommerceOrderCreator(
			$container->get( 'wcgateway.funding-source.renderer' ),
			$container->get( 'session.handler' ),
			$container->get( 'wc-subscriptions.helper' ),
			$container->get( 'button.session.factory.card-data' ),
			$container->get( 'api.factory.shipping' ),
			$container->get( 'api.factory.payer' )
		);
	},
);
