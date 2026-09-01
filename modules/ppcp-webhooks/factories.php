<?php
/**
 * The webhook module factories.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'webhook.registrar'                  => static function ( ContainerInterface $container ): WebhookRegistrar {
		return new WebhookRegistrar(
			$container->get( 'api.factory.webhook' ),
			$container->get( 'api.endpoint.webhook' ),
			$container->get( 'webhook.endpoint.controller' ),
			$container->get( 'webhook.last-webhook-storage' ),
			$container->get( 'webhook.status.simulation' ),
			$container->get( 'webhook.orchestration' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'webhook.status.registered-webhooks' => function ( ContainerInterface $container ): array {
		$endpoint = $container->get( 'api.endpoint.webhook' );
		assert( $endpoint instanceof WebhookEndpoint );

		$is_connected = $container->get( 'settings.flag.is-connected' );

		if ( $is_connected ) {
			return $endpoint->list();
		}

		return array();
	},
);
