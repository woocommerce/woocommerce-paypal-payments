<?php
/**
 * The services of the API client.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AddressFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AmountFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ApplicationContextFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ItemFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayeeFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentsFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentSourceFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use Inpsyde\PayPalCommerce\ApiClient\Helper\DccApplies;
use Inpsyde\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use WpOop\TransientCache\CachePoolFactory;

return array(
	'api.host'                              => function( ContainerInterface $container ) : string {
		return 'https://api.paypal.com';
	},
	'api.paypal-host'                       => function( ContainerInterface $container ) : string {
		return 'https://api.paypal.com';
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
	'api.bearer'                            => static function ( ContainerInterface $container ): Bearer {
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
	'api.endpoint.payment-token'            => static function ( ContainerInterface $container ) : PaymentTokenEndpoint {
		return new PaymentTokenEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'api.prefix' )
		);
	},
	'api.endpoint.webhook'                  => static function ( ContainerInterface $container ) : WebhookEndpoint {

		return new WebhookEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.webhook' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.endpoint.partner-referrals'        => static function ( ContainerInterface $container ) : PartnerReferrals {

		return new PartnerReferrals(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.repository.partner-referrals-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.endpoint.identity-token'           => static function ( ContainerInterface $container ) : IdentityToken {

		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$prefix = $container->get( 'api.prefix' );
		return new IdentityToken(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$logger,
			$prefix
		);
	},
	'api.endpoint.payments'                 => static function ( ContainerInterface $container ): PaymentsEndpoint {
		$authorizations_factory = $container->get( 'api.factory.authorization' );
		$logger                 = $container->get( 'woocommerce.logger.woocommerce' );

		return new PaymentsEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$authorizations_factory,
			$logger
		);
	},
	'api.endpoint.login-seller'             => static function ( ContainerInterface $container ) : LoginSeller {

		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new LoginSeller(
			$container->get( 'api.paypal-host' ),
			$container->get( 'api.partner_merchant_id' ),
			$logger
		);
	},
	'api.endpoint.order'                    => static function ( ContainerInterface $container ): OrderEndpoint {
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
	'api.repository.paypal-request-id'      => static function( ContainerInterface $container ) : PayPalRequestIdRepository {
		return new PayPalRequestIdRepository();
	},
	'api.repository.application-context'    => static function( ContainerInterface $container ) : ApplicationContextRepository {

		$settings = $container->get( 'wcgateway.settings' );
		return new ApplicationContextRepository( $settings );
	},
	'api.repository.partner-referrals-data' => static function ( ContainerInterface $container ) : PartnerReferralsData {

		$merchant_email = $container->get( 'api.merchant_email' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		return new PartnerReferralsData( $merchant_email, $dcc_applies );
	},
	'api.repository.cart'                   => static function ( ContainerInterface $container ): CartRepository {
		$factory = $container->get( 'api.factory.purchase-unit' );
		return new CartRepository( $factory );
	},
	'api.repository.payee'                  => static function ( ContainerInterface $container ): PayeeRepository {
		$merchant_email = $container->get( 'api.merchant_email' );
		$merchant_id    = $container->get( 'api.merchant_id' );
		return new PayeeRepository( $merchant_email, $merchant_id );
	},
	'api.factory.application-context'       => static function ( ContainerInterface $container ) : ApplicationContextFactory {
		return new ApplicationContextFactory();
	},
	'api.factory.payment-token'             => static function ( ContainerInterface $container ) : PaymentTokenFactory {
		return new PaymentTokenFactory();
	},
	'api.factory.webhook'                   => static function ( ContainerInterface $container ): WebhookFactory {
		return new WebhookFactory();
	},
	'api.factory.purchase-unit'             => static function ( ContainerInterface $container ): PurchaseUnitFactory {

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
	'api.factory.patch-collection-factory'  => static function ( ContainerInterface $container ): PatchCollectionFactory {
		return new PatchCollectionFactory();
	},
	'api.factory.payee'                     => static function ( ContainerInterface $container ): PayeeFactory {
		return new PayeeFactory();
	},
	'api.factory.item'                      => static function ( ContainerInterface $container ): ItemFactory {
		return new ItemFactory();
	},
	'api.factory.shipping'                  => static function ( ContainerInterface $container ): ShippingFactory {
		$address_factory = $container->get( 'api.factory.address' );
		return new ShippingFactory( $address_factory );
	},
	'api.factory.amount'                    => static function ( ContainerInterface $container ): AmountFactory {
		$item_factory = $container->get( 'api.factory.item' );
		return new AmountFactory( $item_factory );
	},
	'api.factory.payer'                     => static function ( ContainerInterface $container ): PayerFactory {
		$address_factory = $container->get( 'api.factory.address' );
		return new PayerFactory( $address_factory );
	},
	'api.factory.address'                   => static function ( ContainerInterface $container ): AddressFactory {
		return new AddressFactory();
	},
	'api.factory.payment-source'            => static function ( ContainerInterface $container ): PaymentSourceFactory {
		return new PaymentSourceFactory();
	},
	'api.factory.order'                     => static function ( ContainerInterface $container ): OrderFactory {
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
	'api.factory.payments'                  => static function ( ContainerInterface $container ): PaymentsFactory {
		$authorizations_factory = $container->get( 'api.factory.authorization' );
		return new PaymentsFactory( $authorizations_factory );
	},
	'api.factory.authorization'             => static function ( ContainerInterface $container ): AuthorizationFactory {
		return new AuthorizationFactory();
	},
	'api.helpers.dccapplies'                => static function ( ContainerInterface $container ) : DccApplies {
		return new DccApplies();
	},
);
