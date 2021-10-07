<?php
/**
 * The services of the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AddressFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ApplicationContextFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CaptureFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ItemFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayeeFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentsFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentSourceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerStatusFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookEventFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CartRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'api.host'                              => function( $container ) : string {
		return PAYPAL_API_URL;
	},
	'api.paypal-host'                       => function( $container ) : string {
		return PAYPAL_API_URL;
	},
	'api.partner_merchant_id'               => static function () : string {
		return '';
	},
	'api.merchant_email'                    => function () : string {
		return '';
	},
	'api.merchant_id'                       => function () : string {
		return '';
	},
	'api.key'                               => static function (): string {
		return '';
	},
	'api.secret'                            => static function (): string {
		return '';
	},
	'api.prefix'                            => static function (): string {
		return 'WC-';
	},
	'api.bearer'                            => static function ( $container ): Bearer {
		$cache              = new Cache( 'ppcp-paypal-bearer' );
		$key                = $container->get( 'api.key' );
		$secret             = $container->get( 'api.secret' );
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
	'api.endpoint.partners'                 => static function ( $container ) : PartnersEndpoint {
		return new PartnersEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'api.factory.sellerstatus' ),
			$container->get( 'api.partner_merchant_id' ),
			$container->get( 'api.merchant_id' )
		);
	},
	'api.factory.sellerstatus'              => static function ( $container ) : SellerStatusFactory {
		return new SellerStatusFactory();
	},
	'api.endpoint.payment-token'            => static function ( $container ) : PaymentTokenEndpoint {
		return new PaymentTokenEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'api.prefix' )
		);
	},
	'api.endpoint.webhook'                  => static function ( $container ) : WebhookEndpoint {

		return new WebhookEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.webhook' ),
			$container->get( 'api.factory.webhook-event' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.endpoint.partner-referrals'        => static function ( $container ) : PartnerReferrals {

		return new PartnerReferrals(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.repository.partner-referrals-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.endpoint.identity-token'           => static function ( $container ) : IdentityToken {

		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$prefix = $container->get( 'api.prefix' );
		return new IdentityToken(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$logger,
			$prefix
		);
	},
	'api.endpoint.payments'                 => static function ( $container ): PaymentsEndpoint {
		$authorizations_factory = $container->get( 'api.factory.authorization' );
		$capture_factory = $container->get( 'api.factory.capture' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );

		return new PaymentsEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$authorizations_factory,
			$capture_factory,
			$logger
		);
	},
	'api.endpoint.login-seller'             => static function ( $container ) : LoginSeller {

		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new LoginSeller(
			$container->get( 'api.paypal-host' ),
			$container->get( 'api.partner_merchant_id' ),
			$logger
		);
	},
	'api.endpoint.order'                    => static function ( $container ): OrderEndpoint {
		$order_factory            = $container->get( 'api.factory.order' );
		$patch_collection_factory = $container->get( 'api.factory.patch-collection-factory' );
		$logger                   = $container->get( 'woocommerce.logger.woocommerce' );

		/**
		 * The settings.
		 *
		 * @var Settings $settings
		 */
		$settings                       = $container->get( 'wcgateway.settings' );
		$intent                         = $settings->has( 'intent' ) && strtoupper( (string) $settings->get( 'intent' ) ) === 'AUTHORIZE' ? 'AUTHORIZE' : 'CAPTURE';
		$application_context_repository = $container->get( 'api.repository.application-context' );
		$paypal_request_id              = $container->get( 'api.repository.paypal-request-id' );
		return new OrderEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$order_factory,
			$patch_collection_factory,
			$intent,
			$logger,
			$application_context_repository,
			$paypal_request_id
		);
	},
	'api.repository.paypal-request-id'      => static function( $container ) : PayPalRequestIdRepository {
		return new PayPalRequestIdRepository();
	},
	'api.repository.application-context'    => static function( $container ) : ApplicationContextRepository {

		$settings = $container->get( 'wcgateway.settings' );
		return new ApplicationContextRepository( $settings );
	},
	'api.repository.partner-referrals-data' => static function ( $container ) : PartnerReferralsData {

		$merchant_email = $container->get( 'api.merchant_email' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		return new PartnerReferralsData( $merchant_email, $dcc_applies );
	},
	'api.repository.cart'                   => static function ( $container ): CartRepository {
		$factory = $container->get( 'api.factory.purchase-unit' );
		return new CartRepository( $factory );
	},
	'api.repository.payee'                  => static function ( $container ): PayeeRepository {
		$merchant_email = $container->get( 'api.merchant_email' );
		$merchant_id    = $container->get( 'api.merchant_id' );
		return new PayeeRepository( $merchant_email, $merchant_id );
	},
	'api.factory.application-context'       => static function ( $container ) : ApplicationContextFactory {
		return new ApplicationContextFactory();
	},
	'api.factory.payment-token'             => static function ( $container ) : PaymentTokenFactory {
		return new PaymentTokenFactory();
	},
	'api.factory.webhook'                   => static function ( $container ): WebhookFactory {
		return new WebhookFactory();
	},
	'api.factory.webhook-event'             => static function ( $container ): WebhookEventFactory {
		return new WebhookEventFactory();
	},
	'api.factory.capture'                   => static function ( $container ): CaptureFactory {

		$amount_factory   = $container->get( 'api.factory.amount' );
		return new CaptureFactory( $amount_factory );
	},
	'api.factory.purchase-unit'             => static function ( $container ): PurchaseUnitFactory {

		$amount_factory   = $container->get( 'api.factory.amount' );
		$payee_repository = $container->get( 'api.repository.payee' );
		$payee_factory    = $container->get( 'api.factory.payee' );
		$item_factory     = $container->get( 'api.factory.item' );
		$shipping_factory = $container->get( 'api.factory.shipping' );
		$payments_factory = $container->get( 'api.factory.payments' );
		$prefix           = $container->get( 'api.prefix' );

		return new PurchaseUnitFactory(
			$amount_factory,
			$payee_repository,
			$payee_factory,
			$item_factory,
			$shipping_factory,
			$payments_factory,
			$prefix
		);
	},
	'api.factory.patch-collection-factory'  => static function ( $container ): PatchCollectionFactory {
		return new PatchCollectionFactory();
	},
	'api.factory.payee'                     => static function ( $container ): PayeeFactory {
		return new PayeeFactory();
	},
	'api.factory.item'                      => static function ( $container ): ItemFactory {
		return new ItemFactory();
	},
	'api.factory.shipping'                  => static function ( $container ): ShippingFactory {
		$address_factory = $container->get( 'api.factory.address' );
		return new ShippingFactory( $address_factory );
	},
	'api.factory.amount'                    => static function ( $container ): AmountFactory {
		$item_factory = $container->get( 'api.factory.item' );
		return new AmountFactory( $item_factory );
	},
	'api.factory.payer'                     => static function ( $container ): PayerFactory {
		$address_factory = $container->get( 'api.factory.address' );
		return new PayerFactory( $address_factory );
	},
	'api.factory.address'                   => static function ( $container ): AddressFactory {
		return new AddressFactory();
	},
	'api.factory.payment-source'            => static function ( $container ): PaymentSourceFactory {
		return new PaymentSourceFactory();
	},
	'api.factory.order'                     => static function ( $container ): OrderFactory {
		$purchase_unit_factory          = $container->get( 'api.factory.purchase-unit' );
		$payer_factory                  = $container->get( 'api.factory.payer' );
		$application_context_repository = $container->get( 'api.repository.application-context' );
		$application_context_factory    = $container->get( 'api.factory.application-context' );
		$payment_source_factory         = $container->get( 'api.factory.payment-source' );
		return new OrderFactory(
			$purchase_unit_factory,
			$payer_factory,
			$application_context_repository,
			$application_context_factory,
			$payment_source_factory
		);
	},
	'api.factory.payments'                  => static function ( $container ): PaymentsFactory {
		$authorizations_factory = $container->get( 'api.factory.authorization' );
		$capture_factory        = $container->get( 'api.factory.capture' );
		return new PaymentsFactory( $authorizations_factory, $capture_factory );
	},
	'api.factory.authorization'             => static function ( $container ): AuthorizationFactory {
		return new AuthorizationFactory();
	},
	'api.helpers.dccapplies'                => static function ( $container ) : DccApplies {
		return new DccApplies();
	},
);
