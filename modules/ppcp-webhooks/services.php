<?php
/**
 * The webhook module services.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderApproved;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureRefunded;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureReversed;
use Psr\Container\ContainerInterface;

return array(

	'webhook.registrar'           => function( $container ) : WebhookRegistrar {
		$factory      = $container->get( 'api.factory.webhook' );
		$endpoint     = $container->get( 'api.endpoint.webhook' );
		$rest_endpoint = $container->get( 'webhook.endpoint.controller' );
		return new WebhookRegistrar(
			$factory,
			$endpoint,
			$rest_endpoint
		);
	},
	'webhook.endpoint.controller' => function( $container ) : IncomingWebhookEndpoint {
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
	'webhook.endpoint.handler'    => function( $container ) : array {
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
);
