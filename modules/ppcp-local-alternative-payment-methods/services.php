<?php
/**
 * The local alternative payment methods module services.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'ppcp-local-apms.asset_getter'              => static function ( ContainerInterface $container ): AssetGetter {
		$factory = $container->get( 'assets.asset_getter_factory' );
		assert( $factory instanceof AssetGetterFactory );

		return $factory->for_module( 'ppcp-local-alternative-payment-methods' );
	},
	'ppcp-local-apms.payment-methods'           => static function ( ContainerInterface $container ): array {
		return array(
			'pwc'        => array(
				'id'         => PWCGateway::ID,
				'countries'  => array(),
				'currencies' => array( 'USD' ),
			),
			'bancontact' => array(
				'id'         => BancontactGateway::ID,
				'countries'  => array( 'BE' ),
				'currencies' => array( 'EUR' ),
			),
			'blik'       => array(
				'id'         => BlikGateway::ID,
				'countries'  => array( 'PL' ),
				'currencies' => array( 'PLN' ),
			),
			'eps'        => array(
				'id'         => EPSGateway::ID,
				'countries'  => array( 'AT' ),
				'currencies' => array( 'EUR' ),
			),
			'ideal'      => array(
				'id'         => IDealGateway::ID,
				'countries'  => array( 'NL' ),
				'currencies' => array( 'EUR' ),
			),
			'mybank'     => array(
				'id'         => MyBankGateway::ID,
				'countries'  => array( 'IT' ),
				'currencies' => array( 'EUR' ),
			),
			'p24'        => array(
				'id'         => P24Gateway::ID,
				'countries'  => array( 'PL' ),
				'currencies' => array( 'EUR', 'PLN' ),
			),
			'trustly'    => array(
				'id'         => TrustlyGateway::ID,
				'countries'  => array( 'AT', 'DE', 'DK', 'EE', 'ES', 'FI', 'GB', 'LT', 'LV', 'NL', 'NO', 'SE' ),
				'currencies' => array( 'EUR', 'DKK', 'SEK', 'GBP', 'NOK' ),
			),
			'multibanco' => array(
				'id'         => MultibancoGateway::ID,
				'countries'  => array( 'PT' ),
				'currencies' => array( 'EUR' ),
			),
		);
	},
	'ppcp-local-apms.product-status'            => static function ( ContainerInterface $container ): LocalApmProductStatus {
		return new LocalApmProductStatus(
			$container->get( 'settings.flag.is-connected' ),
			$container->get( 'api.endpoint.partners' ),
			$container->get( 'api.helper.failure-registry' ),
			$container->get( 'api.helper.product-status-result-cache' )
		);
	},
	'ppcp-local-apms.pwc.wc-gateway'            => static function ( ContainerInterface $container ): PWCGateway {
		return new PWCGateway(
			$container->get( 'wcgateway.asset_getter' ),
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.bancontact.wc-gateway'     => static function ( ContainerInterface $container ): BancontactGateway {
		return new BancontactGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.blik.wc-gateway'           => static function ( ContainerInterface $container ): BlikGateway {
		return new BlikGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.eps.wc-gateway'            => static function ( ContainerInterface $container ): EPSGateway {
		return new EPSGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.ideal.wc-gateway'          => static function ( ContainerInterface $container ): IDealGateway {
		return new IDealGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.mybank.wc-gateway'         => static function ( ContainerInterface $container ): MyBankGateway {
		return new MyBankGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.p24.wc-gateway'            => static function ( ContainerInterface $container ): P24Gateway {
		return new P24Gateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.trustly.wc-gateway'        => static function ( ContainerInterface $container ): TrustlyGateway {
		return new TrustlyGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.multibanco.wc-gateway'     => static function ( ContainerInterface $container ): MultibancoGateway {
		return new MultibancoGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'wcgateway.builder.experience-context' )
		);
	},
	'ppcp-local-apms.pwc.payment-method'        => static function ( ContainerInterface $container ): PWCPaymentMethod {
		return new PWCPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.pwc.wc-gateway' )
		);
	},
	'ppcp-local-apms.bancontact.payment-method' => static function ( ContainerInterface $container ): BancontactPaymentMethod {
		return new BancontactPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.bancontact.wc-gateway' )
		);
	},
	'ppcp-local-apms.blik.payment-method'       => static function ( ContainerInterface $container ): BlikPaymentMethod {
		return new BlikPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.blik.wc-gateway' )
		);
	},
	'ppcp-local-apms.eps.payment-method'        => static function ( ContainerInterface $container ): EPSPaymentMethod {
		return new EPSPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.eps.wc-gateway' )
		);
	},
	'ppcp-local-apms.ideal.payment-method'      => static function ( ContainerInterface $container ): IDealPaymentMethod {
		return new IDealPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.ideal.wc-gateway' )
		);
	},
	'ppcp-local-apms.mybank.payment-method'     => static function ( ContainerInterface $container ): MyBankPaymentMethod {
		return new MyBankPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.mybank.wc-gateway' )
		);
	},
	'ppcp-local-apms.p24.payment-method'        => static function ( ContainerInterface $container ): P24PaymentMethod {
		return new P24PaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.p24.wc-gateway' )
		);
	},
	'ppcp-local-apms.trustly.payment-method'    => static function ( ContainerInterface $container ): TrustlyPaymentMethod {
		return new TrustlyPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.trustly.wc-gateway' )
		);
	},
	'ppcp-local-apms.multibanco.payment-method' => static function ( ContainerInterface $container ): MultibancoPaymentMethod {
		return new MultibancoPaymentMethod(
			$container->get( 'ppcp-local-apms.asset_getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.multibanco.wc-gateway' )
		);
	},
	'ppcp-local-apms.eligibility.check'         => static function ( ContainerInterface $container ): bool {
		$general_settings = $container->get( 'settings.data.general' );
		assert( $general_settings instanceof GeneralSettings );

		$merchant_data    = $general_settings->get_merchant_data();
		$merchant_country = $merchant_data->merchant_country;
		$ineligible_countries = array( 'RU', 'BR', 'JP' );
		return ! in_array( $merchant_country, $ineligible_countries, true );
	},
	'ppcp-local-apms.pwc.currency.check'        => static function ( ContainerInterface $container ): bool {
		return 'USD' === $container->get( 'api.shop.currency.getter' )->get();
	},
	'ppcp-local-apms.pwc.eligibility.check'     => static function ( ContainerInterface $container ): bool {
		return $container->get( 'ppcp-local-apms.eligibility.check' ) && $container->get( 'ppcp-local-apms.pwc.currency.check' );
	},
	'ppcp-local-apms.pui.country.check'         => static function ( ContainerInterface $container ): bool {
		return 'DE' === $container->get( 'api.merchant.country' );
	},
	'ppcp-local-apms.pui.currency.check'        => static function ( ContainerInterface $container ): bool {
		return 'EUR' === $container->get( 'api.shop.currency.getter' )->get();
	},
	'ppcp-local-apms.pui.eligibility.check'     => static function ( ContainerInterface $container ): bool {
		return $container->get( 'ppcp-local-apms.pui.country.check' ) && $container->get( 'ppcp-local-apms.pui.currency.check' );
	},
);
