<?php
/**
 * The WebhookRegistrar registers and unregisters webhooks with PayPal.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

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
	 * WebhookRegistrar constructor.
	 *
	 * @param WebhookFactory          $webhook_factory The Webhook factory.
	 * @param WebhookEndpoint         $endpoint The Webhook endpoint.
	 * @param IncomingWebhookEndpoint $rest_endpoint The WordPress Rest API endpoint.
	 */
	public function __construct(
		WebhookFactory $webhook_factory,
		WebhookEndpoint $endpoint,
		IncomingWebhookEndpoint $rest_endpoint
	) {

		$this->webhook_factory = $webhook_factory;
		$this->endpoint        = $endpoint;
		$this->rest_endpoint   = $rest_endpoint;
	}

	/**
	 * Register Webhooks with PayPal.
	 *
	 * @return bool
	 */
	public function register(): bool {
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
			return true;
		} catch ( RuntimeException $error ) {
			return false;
		}
	}

	/**
	 * Unregister webhooks with PayPal.
	 *
	 * @return bool
	 */
	public function unregister(): bool {
		$data = (array) get_option( self::KEY, array() );
		if ( ! $data ) {
			return false;
		}
		try {
			$webhook = $this->webhook_factory->from_array( $data );
			$success = $this->endpoint->delete( $webhook );
		} catch ( RuntimeException $error ) {
			return false;
		}

		if ( $success ) {
			delete_option( self::KEY );
		}
		return $success;
	}
}
