<?php
/**
 * The Pay Later configurator module extensions.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'wcgateway.settings.fields' => function ( array $fields, ContainerInterface $container ): array {
		$old_fields = array(
			'pay_later_messaging_locations',
			'pay_later_enable_styling_per_messaging_location',
			'pay_later_general_message_layout',
			'pay_later_general_message_logo',
			'pay_later_general_message_position',
			'pay_later_general_message_color',
			'pay_later_general_message_flex_color',
			'pay_later_general_message_flex_ratio',
			'pay_later_general_message_preview',
			'pay_later_product_messaging_heading',
			'pay_later_product_message_layout',
			'pay_later_product_message_logo',
			'pay_later_product_message_position',
			'pay_later_product_message_color',
			'pay_later_product_message_flex_color',
			'pay_later_product_message_flex_ratio',
			'pay_later_product_message_preview',
			'pay_later_cart_messaging_heading',
			'pay_later_cart_message_layout',
			'pay_later_cart_message_logo',
			'pay_later_cart_message_position',
			'pay_later_cart_message_color',
			'pay_later_cart_message_flex_color',
			'pay_later_cart_message_flex_ratio',
			'pay_later_cart_message_preview',
			'pay_later_checkout_messaging_heading',
			'pay_later_checkout_message_layout',
			'pay_later_checkout_message_logo',
			'pay_later_checkout_message_position',
			'pay_later_checkout_message_color',
			'pay_later_checkout_message_flex_color',
			'pay_later_checkout_message_flex_ratio',
			'pay_later_checkout_message_preview',
			'pay_later_shop_messaging_heading',
			'pay_later_shop_message_layout',
			'pay_later_shop_message_logo',
			'pay_later_shop_message_position',
			'pay_later_shop_message_color',
			'pay_later_shop_message_flex_color',
			'pay_later_shop_message_flex_ratio',
			'pay_later_shop_message_preview',
			'pay_later_home_messaging_heading',
			'pay_later_home_message_layout',
			'pay_later_home_message_logo',
			'pay_later_home_message_position',
			'pay_later_home_message_color',
			'pay_later_home_message_flex_color',
			'pay_later_home_message_flex_ratio',
			'pay_later_home_message_preview',
		);

		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );
		$vault_enabled = $settings->has( 'vault_enabled' ) && $settings->get( 'vault_enabled' );

		if ( ! $vault_enabled ) {
			$old_fields[] = 'pay_later_messaging_enabled';
		}

		foreach ( $old_fields as $old_field ) {
			unset( $fields[ $old_field ] );
		}

		// If vaulting is enabled, remove the button preview box from PayLater.
		if ( $vault_enabled ) {
			unset( $fields['pay_later_button_preview'] );
		}

		return $fields;
	},
);
