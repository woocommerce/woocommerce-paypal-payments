<?php
/**
 * The WebhookRegistrar registers and unregisters webhooks with PayPal.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;

/**
 * Class WebhookRegistrar
 */
class WebhookRegistrar {


	const EVENT_HOOK = 'ppcp-register-event';
	const KEY        = 'ppcp-webhook';

	/**
	 * The Webhook factory.
	 *
	 * @var WebhookFactory
	 */
	private $webhook_factory;

	/**
	 * The Webhook endpoint.
	 *
	 * @var WebhookEndpoint
	 */
	private $endpoint;

	/**
	 * The WordPress Rest API endpoint.
	 *
	 * @var IncomingWebhookEndpoint
	 */
	private $rest_endpoint;

	/**
	 * The last webhook event storage.
	 *
	 * @var WebhookEventStorage
	 */
	private $last_webhook_event_storage;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * WebhookRegistrar constructor.
	 *
	 * @param WebhookFactory          $webhook_factory The Webhook factory.
	 * @param WebhookEndpoint         $endpoint The Webhook endpoint.
	 * @param IncomingWebhookEndpoint $rest_endpoint The WordPress Rest API endpoint.
	 * @param WebhookEventStorage     $last_webhook_event_storage The last webhook event storage.
	 * @param LoggerInterface         $logger The logger.
	 */
	public function __construct(
		WebhookFactory $webhook_factory,
		WebhookEndpoint $endpoint,
		IncomingWebhookEndpoint $rest_endpoint,
		WebhookEventStorage $last_webhook_event_storage,
		LoggerInterface $logger
	) {

		$this->webhook_factory            = $webhook_factory;
		$this->endpoint                   = $endpoint;
		$this->rest_endpoint              = $rest_endpoint;
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
			$this->rest_endpoint->url(),
			$this->rest_endpoint->handled_event_types()
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
