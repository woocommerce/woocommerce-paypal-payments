<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'fraud-protection.url'                    => static function ( ContainerInterface $container ): string {
		return plugins_url( '/modules/fraud-protection/', $container->get( 'ppcp.path-to-plugin-main-file' ) );
	},

	'fraud-protection.settings.section.id'    => static fn(): string => 'ppcp_fraud_protection',
	'fraud-protection.settings.section.title' => static function (): string {
		return __( 'PayPal fraud protection', 'woocommerce-paypal-payments' );
	},

	'fraud-protection.settings.prefix'        => static fn(): string => 'ppcp_fraud_protection_',

	'fraud-protection.settings.fields'        => static function ( ContainerInterface $container ): array {
		$prefix = $container->get( 'fraud-protection.settings.prefix' );

		return array(
			array(
				'type' => 'title',
				'id'   => $prefix . 'title',
				'name' => $container->get( 'fraud-protection.settings.section.title' ),
				'desc' => '',
			),

			array(
				'type' => 'checkbox',
				'id'   => $prefix . 'enabled',
				'name' => __( 'Enable', 'woocommerce-paypal-payments' ),
				'desc' => __( 'Enable fraud protection', 'woocommerce-paypal-payments' ),
			),

			array(
				'type' => 'sectionend',
				'id'   => $prefix . 'end',
			),
		);
	},
);
