<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

use WooCommerce\PayPalCommerce\FraudProtection\PersistentCounter;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\Recaptcha;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\RecaptchaIntegration;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

return array(
	'fraud-protection.url'                               => static function ( ContainerInterface $container ): string {
		return plugins_url( '/modules/ppcp-fraud-protection/', $container->get( 'ppcp.path-to-plugin-main-file' ) );
	},

	'fraud-protection.recaptcha'                         => static function ( ContainerInterface $container ): Recaptcha {
		return new Recaptcha(
			$container->get( 'fraud-protection.recaptcha.integration' ),
			$container->get( 'fraud-protection.recaptcha.payment-methods' ),
			$container->get( 'fraud-protection.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'fraud-protection.recaptcha.rejection-counter' ),
			$container->get( 'fraud-protection.recaptcha.rejection-logger.source' )
		);
	},
	'fraud-protection.recaptcha.integration'             => static function (): RecaptchaIntegration {
		return new RecaptchaIntegration();
	},
	'fraud-protection.recaptcha.payment-methods'         => static function (): array {
		return apply_filters(
			'woocommerce_paypal_payments_recaptcha_payment_methods',
			array(
				PayPalGateway::ID,
				CreditCardGateway::ID,
				CardButtonGateway::ID,
			)
		);
	},
	'fraud-protection.recaptcha.rejection-counter'       => static function ( ContainerInterface $container ): PersistentCounter {
		return new PersistentCounter(
			$container->get( 'fraud-protection.recaptcha.rejection-counter.option-id' )
		);
	},
	'fraud-protection.recaptcha.rejection-counter.option-id' => static function (): string {
		return 'ppcp_recaptcha_rejection_counter';
	},
	'fraud-protection.recaptcha.rejection-logger.source' => static function (): string {
		return 'woocommerce-paypal-payments-recaptcha';
	},
);
