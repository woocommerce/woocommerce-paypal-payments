<?php
/**
 * The SavedPaymentChecker module extensions.
 *
 * @package WooCommerce\PayPalCommerce\SavedPaymentChecker
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavedPaymentChecker;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'wcgateway.settings.fields' => function ( array $fields, ContainerInterface $container ): array {
		$subscription_helper = $container->get( 'wc-subscriptions.helper' );
		assert( $subscription_helper instanceof SubscriptionHelper );

		$insert_after = function( array $array, string $key, array $new ): array {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys, true );
			$pos = false === $index ? count( $array ) : $index + 1;

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		};

		return $insert_after(
			$fields,
			'vault_enabled',
			array(
				'subscription_behavior_when_vault_fails' => array(
					'title'                => __( 'Subscription capture behavior if Vault fails', 'woocommerce-paypal-payments' ),
					'type'                 => 'select',
					'classes'              => $subscription_helper->plugin_is_active() ? array() : array( 'hide' ),
					'input_class'          => array( 'wc-enhanced-select' ),
					'default'              => 'void_auth',
					'desc_tip'             => true,
					'description'          => __( 'By default, subscription payments are captured only when saving the payment method was successful. Without a saved payment method, automatic renewal payments are not possible.', 'woocommerce-paypal-payments' ),
					'description_with_tip' => __( 'Determines whether authorized payments for subscription orders are captured or voided if there is no saved payment method. This only applies when the intent Capture is used for the subscription order.', 'woocommerce-paypal-payments' ),
					'options'              => array(
						'void_auth'           => __( 'Void authorization & fail the order/subscription', 'woocommerce-paypal-payments' ),
						'capture_auth'        => __( 'Capture authorized payment & set subscription to Manual Renewal', 'woocommerce-paypal-payments' ),
						'capture_auth_ignore' => __( 'Capture authorized payment & disregard missing payment method', 'woocommerce-paypal-payments' ),
					),
					'screens'              => array(
						State::STATE_ONBOARDED,
					),
					'requirements'         => array(),
					'gateway'              => array( 'paypal' ),
				),
			)
		);
	},
);
