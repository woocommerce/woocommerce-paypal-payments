<?php
/**
 * The Axo module services.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Assets\AxoManager;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Axo\Helper\ApmApplies;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(

	// If AXO can be configured.
	'axo.eligible'                          => static function ( ContainerInterface $container ): bool {
		$apm_applies = $container->get( 'axo.helpers.apm-applies' );
		assert( $apm_applies instanceof ApmApplies );

		return $apm_applies->for_country_currency() && $apm_applies->for_settings();
	},

	'axo.helpers.apm-applies'               => static function ( ContainerInterface $container ) : ApmApplies {
		return new ApmApplies(
			$container->get( 'axo.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.shop.country' )
		);
	},

	// If AXO is configured and onboarded.
	'axo.available'                         => static function ( ContainerInterface $container ): bool {
		return true;
	},

	'axo.url'                               => static function ( ContainerInterface $container ): string {
		$path = realpath( __FILE__ );
		if ( false === $path ) {
			return '';
		}
		return plugins_url(
			'/modules/ppcp-axo/',
			dirname( $path, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'axo.manager'                           => static function ( ContainerInterface $container ): AxoManager {
		return new AxoManager(
			$container->get( 'axo.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.settings.status' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	'axo.gateway'                           => static function ( ContainerInterface $container ): AxoGateway {
		return new AxoGateway(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'axo.card_icons' ),
			$container->get( 'api.endpoint.order' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	'axo.card_icons'                        => static function ( ContainerInterface $container ): array {
		return array(
			array(
				'title' => 'Visa',
				'file'  => 'visa-dark.svg',
			),
			array(
				'title' => 'MasterCard',
				'file'  => 'mastercard-dark.svg',
			),
			array(
				'title' => 'American Express',
				'file'  => 'amex.svg',
			),
			array(
				'title' => 'Discover',
				'file'  => 'discover.svg',
			),
		);
	},

	/**
	 * The matrix which countries and currency combinations can be used for AXO.
	 */
	'axo.supported-country-currency-matrix' => static function ( ContainerInterface $container ) : array {
		/**
		 * Returns which countries and currency combinations can be used for AXO.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_axo_supported_country_currency_matrix',
			array(
				'US' => array(
					'USD',
				),
			)
		);
	},

);
