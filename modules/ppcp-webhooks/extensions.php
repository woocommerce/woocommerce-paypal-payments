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
			'webhooks_list' => array(
				'title'        => __( 'Subscribed webhooks', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-table',
				'screens'      => array(
					State::STATE_PROGRESSIVE,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => WebhooksStatusPage::ID,
				'classes'      => array( 'ppcp-webhooks-table' ),
				'value'        => function () use ( $container ) : array {
					return $container->get( 'webhook.status.registered-webhooks-data' );
				},
			),
		);

		return array_merge( $fields, $status_page_fields );
	},
);
