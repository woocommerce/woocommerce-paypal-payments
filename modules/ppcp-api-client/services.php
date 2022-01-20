<?php
/**
 * The services of the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingAgreementsEndpoint;
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
use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'api.host'                                  => function( ContainerInterface $container ) : string {
		return PAYPAL_API_URL;
	},
	'api.paypal-host'                           => function( ContainerInterface $container ) : string {
		return PAYPAL_API_URL;
	},
	'api.partner_merchant_id'                   => static function () : string {
		return '';
	},
	'api.merchant_email'                        => function () : string {
		return '';
	},
	'api.merchant_id'                           => function () : string {
		return '';
	},
	'api.key'                                   => static function (): string {
		return '';
	},
	'api.secret'                                => static function (): string {
		return '';
	},
	'api.prefix'                                => static function (): string {
		return 'WC-';
	},
	'api.bearer'                                => static function ( ContainerInterface $container ): Bearer {
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
	'api.endpoint.partners'                     => static function ( ContainerInterface $container ) : PartnersEndpoint {
		return new PartnersEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'api.factory.sellerstatus' ),
			$container->get( 'api.partner_merchant_id' ),
			$container->get( 'api.merchant_id' )
		);
	},
	'api.factory.sellerstatus'                  => static function ( ContainerInterface $container ) : SellerStatusFactory {
		return new SellerStatusFactory();
	},
	'api.endpoint.payment-token'                => static function ( ContainerInterface $container ) : PaymentTokenEndpoint {
		return new PaymentTokenEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'api.repository.customer' )
		);
	},
	'api.endpoint.webhook'                      => static function ( ContainerInterface $container ) : WebhookEndpoint {

		return new WebhookEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.webhook' ),
			$container->get( 'api.factory.webhook-event' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.endpoint.partner-referrals'            => static function ( ContainerInterface $container ) : PartnerReferrals {

		return new PartnerReferrals(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.repository.partner-referrals-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.endpoint.identity-token'               => static function ( ContainerInterface $container ) : IdentityToken {
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$settings = $container->get( 'wcgateway.settings' );
		$customer_repository = $container->get( 'api.repository.customer' );
		return new IdentityToken(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$logger,
			$settings,
			$customer_repository
		);
	},
	'api.endpoint.payments'                     => static function ( ContainerInterface $container ): PaymentsEndpoint {
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
	'api.endpoint.login-seller'                 => static function ( ContainerInterface $container ) : LoginSeller {

		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new LoginSeller(
			$container->get( 'api.paypal-host' ),
			$container->get( 'api.partner_merchant_id' ),
			$logger
		);
	},
	'api.endpoint.order'                        => static function ( ContainerInterface $container ): OrderEndpoint {
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
		$subscription_helper = $container->get( 'subscription.helper' );
		return new OrderEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$order_factory,
			$patch_collection_factory,
			$intent,
			$logger,
			$application_context_repository,
			$paypal_request_id,
			$subscription_helper
		);
	},
	'api.endpoint.billing-agreements'           => static function ( ContainerInterface $container ): BillingAgreementsEndpoint {
		return new BillingAgreementsEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'api.repository.paypal-request-id'          => static function( ContainerInterface $container ) : PayPalRequestIdRepository {
		return new PayPalRequestIdRepository();
	},
	'api.repository.application-context'        => static function( ContainerInterface $container ) : ApplicationContextRepository {

		$settings = $container->get( 'wcgateway.settings' );
		return new ApplicationContextRepository( $settings );
	},
	'api.repository.partner-referrals-data'     => static function ( ContainerInterface $container ) : PartnerReferralsData {

		$merchant_email = $container->get( 'api.merchant_email' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		return new PartnerReferralsData( $merchant_email, $dcc_applies );
	},
	'api.repository.cart'                       => static function ( ContainerInterface $container ): CartRepository {
		$factory = $container->get( 'api.factory.purchase-unit' );
		return new CartRepository( $factory );
	},
	'api.repository.payee'                      => static function ( ContainerInterface $container ): PayeeRepository {
		$merchant_email = $container->get( 'api.merchant_email' );
		$merchant_id    = $container->get( 'api.merchant_id' );
		return new PayeeRepository( $merchant_email, $merchant_id );
	},
	'api.repository.customer'                   => static function( ContainerInterface $container ): CustomerRepository {
		$prefix           = $container->get( 'api.prefix' );
		return new CustomerRepository( $prefix );
	},
	'api.factory.application-context'           => static function ( ContainerInterface $container ) : ApplicationContextFactory {
		return new ApplicationContextFactory();
	},
	'api.factory.payment-token'                 => static function ( ContainerInterface $container ) : PaymentTokenFactory {
		return new PaymentTokenFactory();
	},
	'api.factory.webhook'                       => static function ( ContainerInterface $container ): WebhookFactory {
		return new WebhookFactory();
	},
	'api.factory.webhook-event'                 => static function ( ContainerInterface $container ): WebhookEventFactory {
		return new WebhookEventFactory();
	},
	'api.factory.capture'                       => static function ( ContainerInterface $container ): CaptureFactory {

		$amount_factory   = $container->get( 'api.factory.amount' );
		return new CaptureFactory( $amount_factory );
	},
	'api.factory.purchase-unit'                 => static function ( ContainerInterface $container ): PurchaseUnitFactory {

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
	'api.factory.patch-collection-factory'      => static function ( ContainerInterface $container ): PatchCollectionFactory {
		return new PatchCollectionFactory();
	},
	'api.factory.payee'                         => static function ( ContainerInterface $container ): PayeeFactory {
		return new PayeeFactory();
	},
	'api.factory.item'                          => static function ( ContainerInterface $container ): ItemFactory {
		return new ItemFactory(
			$container->get( 'api.shop.currency' )
		);
	},
	'api.factory.shipping'                      => static function ( ContainerInterface $container ): ShippingFactory {
		$address_factory = $container->get( 'api.factory.address' );
		return new ShippingFactory( $address_factory );
	},
	'api.factory.amount'                        => static function ( ContainerInterface $container ): AmountFactory {
		$item_factory = $container->get( 'api.factory.item' );
		return new AmountFactory(
			$item_factory,
			$container->get( 'api.shop.currency' )
		);
	},
	'api.factory.payer'                         => static function ( ContainerInterface $container ): PayerFactory {
		$address_factory = $container->get( 'api.factory.address' );
		return new PayerFactory( $address_factory );
	},
	'api.factory.address'                       => static function ( ContainerInterface $container ): AddressFactory {
		return new AddressFactory();
	},
	'api.factory.payment-source'                => static function ( ContainerInterface $container ): PaymentSourceFactory {
		return new PaymentSourceFactory();
	},
	'api.factory.order'                         => static function ( ContainerInterface $container ): OrderFactory {
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
	'api.factory.payments'                      => static function ( ContainerInterface $container ): PaymentsFactory {
		$authorizations_factory = $container->get( 'api.factory.authorization' );
		$capture_factory        = $container->get( 'api.factory.capture' );
		return new PaymentsFactory( $authorizations_factory, $capture_factory );
	},
	'api.factory.authorization'                 => static function ( ContainerInterface $container ): AuthorizationFactory {
		return new AuthorizationFactory();
	},
	'api.helpers.dccapplies'                    => static function ( ContainerInterface $container ) : DccApplies {
		return new DccApplies(
			$container->get( 'api.dcc-supported-country-currency-matrix' ),
			$container->get( 'api.dcc-supported-country-card-matrix' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.shop.country' )
		);
	},

	'api.shop.currency'                         => static function ( ContainerInterface $container ) : string {
		// We use option instead of get_woocommerce_currency
		// because it will not be overridden by currency switching plugins.
		$currency = get_option( 'woocommerce_currency' );
		if ( ! $currency ) {
			return 'NO_CURRENCY'; // Unlikely to happen.
		}
		return $currency;
	},
	'api.shop.country'                          => static function ( ContainerInterface $container ) : string {
		$location = wc_get_base_location();
		return $location['country'];
	},
	'api.shop.is-psd2-country'                  => static function ( ContainerInterface $container ) : bool {
		return in_array(
			$container->get( 'api.shop.country' ),
			$container->get( 'api.psd2-countries' ),
			true
		);
	},
	'api.shop.is-currency-supported'            => static function ( ContainerInterface $container ) : bool {
		return in_array(
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.supported-currencies' ),
			true
		);
	},

	/**
	 * Currencies supported by PayPal.
	 *
	 * From https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/
	 */
	'api.supported-currencies'                  => static function ( ContainerInterface $container ) : array {
		return array(
			'AUD',
			'BRL',
			'CAD',
			'CNY',
			'CZK',
			'DKK',
			'EUR',
			'HKD',
			'HUF',
			'ILS',
			'JPY',
			'MYR',
			'MXN',
			'TWD',
			'NZD',
			'NOK',
			'PHP',
			'PLN',
			'GBP',
			'RUB',
			'SGD',
			'SEK',
			'CHF',
			'THB',
			'USD',
		);
	},

	/**
	 * The matrix which countries and currency combinations can be used for DCC.
	 */
	'api.dcc-supported-country-currency-matrix' => static function ( ContainerInterface $container ) : array {
		return array(
			'AU' => array(
				'AUD',
				'CAD',
				'CHF',
				'CZK',
				'DKK',
				'EUR',
				'GBP',
				'HKD',
				'HUF',
				'JPY',
				'NOK',
				'NZD',
				'PLN',
				'SEK',
				'SGD',
				'USD',
			),
			'ES' => array(
				'AUD',
				'CAD',
				'CHF',
				'CZK',
				'DKK',
				'EUR',
				'GBP',
				'HKD',
				'HUF',
				'JPY',
				'NOK',
				'NZD',
				'PLN',
				'SEK',
				'SGD',
				'USD',
			),
			'FR' => array(
				'AUD',
				'CAD',
				'CHF',
				'CZK',
				'DKK',
				'EUR',
				'GBP',
				'HKD',
				'HUF',
				'JPY',
				'NOK',
				'NZD',
				'PLN',
				'SEK',
				'SGD',
				'USD',
			),
			'GB' => array(
				'AUD',
				'CAD',
				'CHF',
				'CZK',
				'DKK',
				'EUR',
				'GBP',
				'HKD',
				'HUF',
				'JPY',
				'NOK',
				'NZD',
				'PLN',
				'SEK',
				'SGD',
				'USD',
			),
			'IT' => array(
				'AUD',
				'CAD',
				'CHF',
				'CZK',
				'DKK',
				'EUR',
				'GBP',
				'HKD',
				'HUF',
				'JPY',
				'NOK',
				'NZD',
				'PLN',
				'SEK',
				'SGD',
				'USD',
			),
			'US' => array(
				'AUD',
				'CAD',
				'EUR',
				'GBP',
				'JPY',
				'USD',
			),
			'CA' => array(
				'AUD',
				'CAD',
				'CHF',
				'CZK',
				'DKK',
				'EUR',
				'GBP',
				'HKD',
				'HUF',
				'JPY',
				'NOK',
				'NZD',
				'PLN',
				'SEK',
				'SGD',
				'USD',
			),
		);
	},

	/**
	 * Which countries support which credit cards. Empty credit card arrays mean no restriction on currency.
	 */
	'api.dcc-supported-country-card-matrix'     => static function ( ContainerInterface $container ) : array {
		return array(
			'AU' => array(
				'mastercard' => array(),
				'visa'       => array(),
				'amex'       => array( 'AUD' ),
			),
			'ES' => array(
				'mastercard' => array(),
				'visa'       => array(),
				'amex'       => array( 'EUR' ),
			),
			'FR' => array(
				'mastercard' => array(),
				'visa'       => array(),
				'amex'       => array( 'EUR' ),
			),
			'GB' => array(
				'mastercard' => array(),
				'visa'       => array(),
				'amex'       => array( 'GBP', 'USD' ),
			),
			'IT' => array(
				'mastercard' => array(),
				'visa'       => array(),
				'amex'       => array( 'EUR' ),
			),
			'US' => array(
				'mastercard' => array(),
				'visa'       => array(),
				'amex'       => array( 'USD' ),
				'discover'   => array( 'USD' ),
			),
			'CA' => array(
				'mastercard' => array(),
				'visa'       => array(),
				'amex'       => array( 'CAD' ),
				'jcb'        => array( 'CAD' ),
			),
		);
	},

	'api.psd2-countries'                        => static function ( ContainerInterface $container ) : array {
		return array(
			'AT',
			'BE',
			'BG',
			'CY',
			'CZ',
			'DK',
			'EE',
			'FI',
			'FR',
			'DE',
			'GB',
			'GR',
			'HU',
			'IE',
			'IT',
			'LV',
			'LT',
			'LU',
			'MT',
			'NL',
			'NO',
			'PL',
			'PT',
			'RO',
			'SK',
			'SI',
			'ES',
			'SE',
		);
	},
);
