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
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulationStateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Handler\BillingPlanPricingChangeActivated;
use WooCommerce\PayPalCommerce\Webhooks\Handler\BillingPlanUpdated;
use WooCommerce\PayPalCommerce\Webhooks\Handler\BillingSubscriptionCancelled;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CatalogProductUpdated;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderApproved;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutPaymentApprovalReversed;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCapturePending;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureRefunded;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureReversed;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentSaleCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentSaleRefunded;
use WooCommerce\PayPalCommerce\Webhooks\Handler\VaultPaymentTokenCreated;
use WooCommerce\PayPalCommerce\Webhooks\Handler\VaultPaymentTokenDeleted;
use WooCommerce\PayPalCommerce\Webhooks\Status\Assets\WebhooksStatusPageAssets;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;

return array(

	'webhook.registrar'                       => function( ContainerInterface $container ) : WebhookRegistrar {
		$factory      = $container->get( 'api.factory.webhook' );
		$endpoint     = $container->get( 'api.endpoint.webhook' );
		$rest_endpoint = $container->get( 'webhook.endpoint.controller' );
		$last_webhook_storage = $container->get( 'webhook.last-webhook-storage' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		return new WebhookRegistrar(
			$factory,
			$endpoint,
			$rest_endpoint,
			$last_webhook_storage,
			$logger
		);
	},
	'webhook.endpoint.controller'             => function( ContainerInterface $container ) : IncomingWebhookEndpoint {
		$webhook_endpoint = $container->get( 'api.endpoint.webhook' );
		$webhook  = $container->get( 'webhook.current' );
		$handler          = $container->get( 'webhook.endpoint.handler' );
		$logger           = $container->get( 'woocommerce.logger.woocommerce' );
		$verify_request   = ! defined( 'PAYPAL_WEBHOOK_REQUEST_VERIFICATION' ) || PAYPAL_WEBHOOK_REQUEST_VERIFICATION;
		$webhook_event_factory      = $container->get( 'api.factory.webhook-event' );
		$simulation      = $container->get( 'webhook.status.simulation' );
		$last_webhook_storage = $container->get( 'webhook.last-webhook-storage' );

		return new IncomingWebhookEndpoint(
			$webhook_endpoint,
			$webhook,
			$logger,
			$verify_request,
			$webhook_event_factory,
			$simulation,
			$last_webhook_storage,
			... $handler
		);
	},
	'webhook.endpoint.handler'                => function( ContainerInterface $container ) : array {
		$logger         = $container->get( 'woocommerce.logger.woocommerce' );
		$prefix         = $container->get( 'api.prefix' );
		$order_endpoint = $container->get( 'api.endpoint.order' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		$payment_token_factory = $container->get( 'vaulting.payment-token-factory' );
		$payment_token_helper = $container->get( 'vaulting.payment-token-helper' );
		$refund_fees_updater = $container->get( 'wcgateway.helper.refund-fees-updater' );

		return array(
			new CheckoutOrderApproved(
				$logger,
				$order_endpoint,
				$container->get( 'session.handler' ),
				$container->get( 'wcgateway.funding-source.renderer' ),
				$container->get( 'wcgateway.order-processor' )
			),
			new CheckoutOrderCompleted( $logger ),
			new CheckoutPaymentApprovalReversed( $logger ),
			new PaymentCaptureRefunded( $logger, $refund_fees_updater ),
			new PaymentCaptureReversed( $logger ),
			new PaymentCaptureCompleted( $logger, $order_endpoint ),
			new VaultPaymentTokenCreated( $logger, $prefix, $authorized_payments_processor, $payment_token_factory, $payment_token_helper ),
			new VaultPaymentTokenDeleted( $logger ),
			new PaymentCapturePending( $logger ),
			new PaymentSaleCompleted( $logger, $container->get( 'paypal-subscriptions.renewal-handler' ) ),
			new PaymentSaleRefunded( $logger, $refund_fees_updater ),
			new BillingSubscriptionCancelled( $logger ),
			new BillingPlanPricingChangeActivated( $logger ),
			new CatalogProductUpdated( $logger ),
			new BillingPlanUpdated( $logger ),
		);
	},

	'webhook.current'                         => function( ContainerInterface $container ) : ?Webhook {
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

	'webhook.is-registered'                   => function( ContainerInterface $container ) : bool {
		return $container->get( 'webhook.current' ) !== null;
	},

	'webhook.status.registered-webhooks-data' => function( ContainerInterface $container ) : array {
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

	'webhook.status.simulation'               => function( ContainerInterface $container ) : WebhookSimulation {
		$webhook_endpoint = $container->get( 'api.endpoint.webhook' );
		$webhook  = $container->get( 'webhook.current' );
		return new WebhookSimulation(
			$webhook_endpoint,
			$webhook,
			'CHECKOUT.ORDER.APPROVED',
			'2.0'
		);
	},

	'webhook.status.assets'                   => function( ContainerInterface $container ) : WebhooksStatusPageAssets {
		return new WebhooksStatusPageAssets(
			$container->get( 'webhook.module-url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'onboarding.environment' )
		);
	},

	'webhook.endpoint.resubscribe'            => static function ( ContainerInterface $container ) : ResubscribeEndpoint {
		$registrar = $container->get( 'webhook.registrar' );
		$request_data            = $container->get( 'button.request-data' );

		return new ResubscribeEndpoint(
			$registrar,
			$request_data
		);
	},

	'webhook.endpoint.simulate'               => static function ( ContainerInterface $container ) : SimulateEndpoint {
		$simulation = $container->get( 'webhook.status.simulation' );
		$request_data = $container->get( 'button.request-data' );

		return new SimulateEndpoint(
			$simulation,
			$request_data
		);
	},
	'webhook.endpoint.simulation-state'       => static function ( ContainerInterface $container ) : SimulationStateEndpoint {
		$simulation = $container->get( 'webhook.status.simulation' );

		return new SimulationStateEndpoint(
			$simulation
		);
	},

	'webhook.last-webhook-storage'            => static function ( ContainerInterface $container ): WebhookEventStorage {
		return new WebhookEventStorage( $container->get( 'webhook.last-webhook-storage.key' ) );
	},
	'webhook.last-webhook-storage.key'        => static function ( ContainerInterface $container ): string {
		return 'ppcp-last-webhook';
	},

	'webhook.module-url'                      => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules/ppcp-webhooks/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
);
