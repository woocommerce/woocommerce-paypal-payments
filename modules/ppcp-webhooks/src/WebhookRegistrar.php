<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;

/**
 * The WebhookRegistrar registers and unregisters webhooks with PayPal.
 */
class WebhookRegistrar {
	const EVENT_HOOK = 'ppcp-register-event';
	const KEY        = 'ppcp-webhook';

	private WebhookFactory $webhook_factory;

	private WebhookEndpoint $endpoint;

	private IncomingWebhookEndpoint $incoming_webhook_endpoint;

	private WebhookEventStorage $last_webhook_event_storage;

	private LoggerInterface $logger;

	public function __construct(
		WebhookFactory $webhook_factory,
		WebhookEndpoint $endpoint,
		IncomingWebhookEndpoint $incoming_webhook_endpoint,
		WebhookEventStorage $last_webhook_event_storage,
		LoggerInterface $logger
	) {

		$this->webhook_factory            = $webhook_factory;
		$this->endpoint                   = $endpoint;
		$this->incoming_webhook_endpoint  = $incoming_webhook_endpoint;
		$this->last_webhook_event_storage = $last_webhook_event_storage;
		$this->logger                     = $logger;
	}

	/**
	 * Register Webhooks with PayPal.
	 *
	 * @return bool
	 */
	public function register(): bool {
		$this->unregister();

		$webhook = $this->webhook_factory->for_url_and_events(
			$this->incoming_webhook_endpoint->url(),
			$this->incoming_webhook_endpoint->handled_event_types()
		);

		try {
			$created = $this->endpoint->create( $webhook );
			if ( empty( $created->id() ) ) {
				return false;
			}
			update_option(
				self::KEY,
				$created->to_array()
			);
			$this->last_webhook_event_storage->clear();
			$this->logger->info( 'Webhooks subscribed.' );
			return true;
		} catch ( RuntimeException $error ) {
			$this->logger->error( 'Failed to subscribe webhooks: ' . $error->getMessage() );
			return false;
		}
	}

	/**
	 * Unregister webhooks with PayPal.
	 */
	public function unregister(): void {
		try {
			$webhooks = $this->endpoint->list();
			foreach ( $webhooks as $webhook ) {
				try {
					$this->endpoint->delete( $webhook );
				} catch ( RuntimeException $deletion_error ) {
					$this->logger->error( "Failed to delete webhook {$webhook->id()}: {$deletion_error->getMessage()}" );
				}
			}
		} catch ( RuntimeException $error ) {
			$this->logger->error( 'Failed to delete webhooks: ' . $error->getMessage() );
		}

		delete_option( self::KEY );
		$this->last_webhook_event_storage->clear();
		$this->logger->info( 'Webhooks deleted.' );
	}
}
