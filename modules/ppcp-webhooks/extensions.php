<?php
/**
 * The webhook module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'wcgateway.settings.fields' => static function ( array $fields, ContainerInterface $container ): array {
		$status_page_fields = array(
			'webhook_status_heading' => array(
				'heading'      => __( 'Webhook Status', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => Settings::CONNECTION_TAB_ID,
				'description'  => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Status of the webhooks subscription. More information about the webhooks is available in the %1$sWebhook Status documentation%2$s.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#webhook-status" target="_blank">',
					'</a>'
				),
			),
			'webhooks_list'          => array(
				'title'        => __( 'Subscribed webhooks', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-table',
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => Settings::CONNECTION_TAB_ID,
				'classes'      => array( 'ppcp-webhooks-table' ),
				'value'        => function () use ( $container ) : array {
					return $container->get( 'webhook.status.registered-webhooks-data' );
				},
			),
			'webhooks_resubscribe'   => array(
				'title'        => __( 'Resubscribe webhooks', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-text',
				'text'         => '<button type="button" class="button ppcp-webhooks-resubscribe">' . esc_html__( 'Resubscribe', 'woocommerce-paypal-payments' ) . '</button>',
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => Settings::CONNECTION_TAB_ID,
				'description'  => __( 'Click to remove the current webhook subscription and subscribe again, for example, if the website domain or URL structure changed.', 'woocommerce-paypal-payments' ),
			),
		);

		$is_registered = $container->get( 'webhook.is-registered' );
		if ( $is_registered ) {
			$status_page_fields = array_merge(
				$status_page_fields,
				array(
					'webhooks_simulate' => array(
						'title'        => __( 'Webhook simulation', 'woocommerce-paypal-payments' ),
						'type'         => 'ppcp-text',
						'text'         => '<button type="button" class="button ppcp-webhooks-simulate">' . esc_html__( 'Simulate', 'woocommerce-paypal-payments' ) . '</button>',
						'screens'      => array(
							State::STATE_ONBOARDED,
						),
						'requirements' => array(),
						'gateway'      => Settings::CONNECTION_TAB_ID,
						'description'  => __( 'Click to request a sample webhook payload from PayPal, allowing to check that your server can successfully receive webhooks.', 'woocommerce-paypal-payments' ),
					),
				)
			);
		}

		return array_merge( $fields, $status_page_fields );
	},
);
