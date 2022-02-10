<?php
/**
 * The webhook module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhooksStatusPage;

return array(
	'wcgateway.settings.fields' => static function ( $container, array $fields ): array {
		$status_page_fields = array(
			'webhooks_list'        => array(
				'title'        => __( 'Subscribed webhooks', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-table',
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => WebhooksStatusPage::ID,
				'classes'      => array( 'ppcp-webhooks-table' ),
				'value'        => function () use ( $container ) : array {
					return $container->get( 'webhook.status.registered-webhooks-data' );
				},
			),
			'webhooks_resubscribe' => array(
				'title'        => __( 'Resubscribe webhooks', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-text',
				'text'         => '<button type="button" class="button ppcp-webhooks-resubscribe">' . esc_html__( 'Resubscribe', 'woocommerce-paypal-payments' ) . '</button>',
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => WebhooksStatusPage::ID,
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
						'gateway'      => WebhooksStatusPage::ID,
						'description'  => __( 'Click to request a sample webhook payload from PayPal, allowing to check that your server can successfully receive webhooks.', 'woocommerce-paypal-payments' ),
					),
				)
			);
		}

		return array_merge( $fields, $status_page_fields );
	},
);
