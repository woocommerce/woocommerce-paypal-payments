<?php
/**
 * The uninstall module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Uninstall;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'wcgateway.settings.fields' => static function ( array $fields, ContainerInterface $container ): array {
		$uninstall_fields = array(
			'uninstall_heading'               => array(
				'heading'      => __( 'Uninstall/Clear Database', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => Settings::CONNECTION_TAB_ID,
				'description'  => __( 'Manage plugin data and scheduled actions stored in database.', 'woocommerce-paypal-payments' ),
			),
			'uninstall_clear_db_on_uninstall' => array(
				'title'        => __( 'Remove PayPal Payments data from Database on uninstall', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Remove options and scheduled actions from database when uninstalling the plugin.', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => Settings::CONNECTION_TAB_ID,
			),
			'uninstall_clear_db_now'          => array(
				'title'        => __( 'Remove PayPal Payments data from Database.', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-text',
				'text'         => '<button type="button" class="button ppcp-clear_db_now">' . esc_html__( 'Clear now', 'woocommerce-paypal-payments' ) . '</button>',
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => Settings::CONNECTION_TAB_ID,
				'description'  => __( 'Click to remove options and scheduled actions from database now.', 'woocommerce-paypal-payments' ),
			),
		);

		return array_merge( $fields, $uninstall_fields );
	},
);
