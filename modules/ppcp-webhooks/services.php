<?php
/**
 * The webhook module services.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\WcGateway\Assets\WebhooksStatusPageAssets;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderApproved;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureRefunded;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureReversed;
use Psr\Container\ContainerInterface;

return array(

	'webhook.registrar'                       => function( $container ) : WebhookRegistrar {
		$factory      = $container->get( 'api.factory.webhook' );
		$endpoint     = $container->get( 'api.endpoint.webhook' );
		$rest_endpoint = $container->get( 'webhook.endpoint.controller' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new WebhookRegistrar(
			$factory,
			$endpoint,
			$rest_endpoint,
			$logger
		);
	},
	'webhook.endpoint.controller'             => function( $container ) : IncomingWebhookEndpoint {
		$webhook_endpoint = $container->get( 'api.endpoint.webhook' );
		$webhook_factory  = $container->get( 'api.factory.webhook' );
		$handler          = $container->get( 'webhook.endpoint.handler' );
		$logger           = $container->get( 'woocommerce.logger.woocommerce' );
		$verify_request   = ! defined( 'PAYPAL_WEBHOOK_REQUEST_VERIFICATION' ) || PAYPAL_WEBHOOK_REQUEST_VERIFICATION;

		return new IncomingWebhookEndpoint(
			$webhook_endpoint,
			$webhook_factory,
			$logger,
			$verify_request,
			... $handler
		);
	},
	'webhook.endpoint.handler'                => function( $container ) : array {
		$logger         = $container->get( 'woocommerce.logger.woocommerce' );
		$prefix         = $container->get( 'api.prefix' );
		$order_endpoint = $container->get( 'api.endpoint.order' );
		return array(
			new CheckoutOrderApproved( $logger, $prefix, $order_endpoint ),
			new CheckoutOrderCompleted( $logger, $prefix ),
			new PaymentCaptureRefunded( $logger, $prefix ),
			new PaymentCaptureReversed( $logger, $prefix ),
			new PaymentCaptureCompleted( $logger, $prefix ),
		);
	},

	'webhook.status.registered-webhooks'      => function( $container ) : array {
		$endpoint = $container->get( 'api.endpoint.webhook' );
		assert( $endpoint instanceof WebhookEndpoint );

		return $endpoint->list();
	},

	'webhook.status.registered-webhooks-data' => function( $container ) : array {
		$empty_placeholder = __( 'No webhooks found.', 'woocommerce-paypal-payments' );

		$webhooks = array();
		try {
			$webhooks = $container->get( 'webhook.status.registered-webhooks' );
		} catch ( Exception $exception ) {
			$empty_placeholder = sprintf(
				'<span class="error">%s</span>',
				__( 'Failed to load webhooks.', 'woocommerce-paypal-payments' )
			);
		}

		return array(
			'headers'           => array(
				__( 'URL', 'woocommerce-paypal-payments' ),
				__( 'Tracked events', 'woocommerce-paypal-payments' ),
			),
			'data'              => array_map(
				function ( Webhook $webhook ): array {
					return array(
						esc_html( $webhook->url() ),
						implode(
							',<br/>',
							array_map(
								'esc_html',
								$webhook->humanfriendly_event_names()
							)
						),
					);
				},
				$webhooks
			),
			'empty_placeholder' => $empty_placeholder,
		);
	},

	'webhook.status.assets'                   => function( $container ) : WebhooksStatusPageAssets {
		return new WebhooksStatusPageAssets(
			$container->get( 'webhook.module-url' )
		);
	},

	'webhook.module-url'                      => static function ( $container ): string {
		return plugins_url(
			'/modules/ppcp-webhooks/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
);
