<?php
/**
 * The webhook module services.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\WcGateway\Assets\WebhooksStatusPageAssets;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulationStateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderApproved;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureRefunded;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureReversed;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;

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
		$webhook  = $container->get( 'webhook.current' );
		$handler          = $container->get( 'webhook.endpoint.handler' );
		$logger           = $container->get( 'woocommerce.logger.woocommerce' );
		$verify_request   = ! defined( 'PAYPAL_WEBHOOK_REQUEST_VERIFICATION' ) || PAYPAL_WEBHOOK_REQUEST_VERIFICATION;
		$webhook_event_factory      = $container->get( 'api.factory.webhook-event' );
		$simulation      = $container->get( 'webhook.status.simulation' );

		return new IncomingWebhookEndpoint(
			$webhook_endpoint,
			$webhook,
			$logger,
			$verify_request,
			$webhook_event_factory,
			$simulation,
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

	'webhook.current'                         => function( $container ) : ?Webhook {
		$data = (array) get_option( WebhookRegistrar::KEY, array() );
		if ( empty( $data ) ) {
			return null;
		}

		$factory = $container->get( 'api.factory.webhook' );
		assert( $factory instanceof WebhookFactory );

		try {
			return $factory->from_array( $data );
		} catch ( Exception $exception ) {
			$logger = $container->get( 'woocommerce.logger.woocommerce' );
			assert( $logger instanceof LoggerInterface );
			$logger->error( 'Failed to parse the stored webhook data: ' . $exception->getMessage() );
			return null;
		}
	},

	'webhook.is-registered'                   => function( $container ) : bool {
		return $container->get( 'webhook.current' ) !== null;
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

	'webhook.status.simulation'               => function( $container ) : WebhookSimulation {
		$webhook_endpoint = $container->get( 'api.endpoint.webhook' );
		$webhook  = $container->get( 'webhook.current' );
		return new WebhookSimulation(
			$webhook_endpoint,
			$webhook,
			'CHECKOUT.ORDER.APPROVED',
			'2.0'
		);
	},

	'webhook.status.assets'                   => function( $container ) : WebhooksStatusPageAssets {
		return new WebhooksStatusPageAssets(
			$container->get( 'webhook.module-url' )
		);
	},

	'webhook.endpoint.resubscribe'            => static function ( $container ) : ResubscribeEndpoint {
		$registrar = $container->get( 'webhook.registrar' );
		$request_data            = $container->get( 'button.request-data' );

		return new ResubscribeEndpoint(
			$registrar,
			$request_data
		);
	},

	'webhook.endpoint.simulate'               => static function ( $container ) : SimulateEndpoint {
		$simulation = $container->get( 'webhook.status.simulation' );
		$request_data = $container->get( 'button.request-data' );

		return new SimulateEndpoint(
			$simulation,
			$request_data
		);
	},
	'webhook.endpoint.simulation-state'       => static function ( $container ) : SimulationStateEndpoint {
		$simulation = $container->get( 'webhook.status.simulation' );

		return new SimulationStateEndpoint(
			$simulation
		);
	},

	'webhook.module-url'                      => static function ( $container ): string {
		return plugins_url(
			'/modules/ppcp-webhooks/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
);
