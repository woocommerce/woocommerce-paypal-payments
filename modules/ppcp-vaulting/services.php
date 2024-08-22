<?php
/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'vaulting.module-url'                 => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules/ppcp-vaulting/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'vaulting.repository.payment-token'   => static function ( ContainerInterface $container ): PaymentTokenRepository {
		$factory  = $container->get( 'api.factory.payment-token' );
		$endpoint = $container->get( 'api.endpoint.payment-token' );
		return new PaymentTokenRepository( $factory, $endpoint );
	},
	'vaulting.customer-approval-listener' => function( ContainerInterface $container ) : CustomerApprovalListener {
		return new CustomerApprovalListener(
			$container->get( 'api.endpoint.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'vaulting.credit-card-handler'        => function( ContainerInterface $container ): VaultedCreditCardHandler {
		return new VaultedCreditCardHandler(
			$container->get( 'wc-subscriptions.helper' ),
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.payer' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'api.endpoint.order' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.processor.authorized-payments' ),
			$container->get( 'wcgateway.settings' )
		);
	},
	'vaulting.payment-token-factory'      => function( ContainerInterface $container ): PaymentTokenFactory {
		return new PaymentTokenFactory();
	},
	'vaulting.payment-token-helper'       => function( ContainerInterface $container ): PaymentTokenHelper {
		return new PaymentTokenHelper();
	},
	'vaulting.payment-tokens-migration'   => function( ContainerInterface $container ): PaymentTokensMigration {
		return new PaymentTokensMigration(
			$container->get( 'vaulting.payment-token-factory' ),
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'vaulting.payment-token-helper' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'vaulting.wc-payment-tokens'          => static function( ContainerInterface $container ): WooCommercePaymentTokens {
		return new WooCommercePaymentTokens(
			$container->get( 'vaulting.payment-token-helper' ),
			$container->get( 'vaulting.payment-token-factory' ),
			$container->get( 'api.endpoint.payment-tokens' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'vaulting.vault-v3-enabled'           => static function( ContainerInterface $container ): bool {
		return $container->has( 'save-payment-methods.eligible' ) && $container->get( 'save-payment-methods.eligible' );
	},
);
