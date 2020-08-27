<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\WebhookFactory;

class WebhookRegistrar {


	public const EVENT_HOOK = 'ppcp-register-event';
	public const KEY        = 'ppcp-webhook';

	private $webhookFactory;
	private $endpoint;
	private $restEndpoint;
	public function __construct(
		WebhookFactory $webhookFactory,
		WebhookEndpoint $endpoint,
		IncomingWebhookEndpoint $restEndpoint
	) {

		$this->webhookFactory = $webhookFactory;
		$this->endpoint       = $endpoint;
		$this->restEndpoint   = $restEndpoint;
	}

	public function register(): bool {
		$webhook = $this->webhookFactory->forUrlAndEvents(
			$this->restEndpoint->url(),
			$this->restEndpoint->handledEventTypes()
		);

		try {
			$created = $this->endpoint->create( $webhook );
			if ( empty( $created->id() ) ) {
				return false;
			}
			update_option(
				self::KEY,
				$created->toArray()
			);
			return true;
		} catch ( RuntimeException $error ) {
			wp_schedule_single_event(
				time() - 1,
				self::EVENT_HOOK
			);
			return false;
		}
	}

	public function unregister(): bool {
		$data = (array) get_option( self::KEY, array() );
		if ( ! $data ) {
			return false;
		}
		try {
			$webhook = $this->webhookFactory->fromArray( $data );
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
