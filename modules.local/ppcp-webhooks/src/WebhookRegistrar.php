<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;


use Inpsyde\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\WebhookFactory;

class WebhookRegistrar
{

    public const EVENT_HOOK = 'ppcp-register-event';
    public const KEY = 'ppcp-webhook';

    private $webhookFactory;
    private $endpoint;
    private $restEndpoint;
    public function __construct(
        WebhookFactory $webhookFactory,
        WebhookEndpoint $endpoint,
        IncomingWebhookEndpoint $restEndpoint
    ) {
        $this->webhookFactory = $webhookFactory;
        $this->endpoint = $endpoint;
        $this->restEndpoint = $restEndpoint;
    }

    public function register() : bool
    {
        $webhook = $this->webhookFactory->forUrlAndEvents(
            $this->restEndpoint->url(),
            $this->restEndpoint->handledEventTypes()
        );

        try {
            $created = $this->endpoint->create($webhook);
            if(empty($created->id())) {
                return false;
            }
            update_option(
                self::KEY,
                $webhook->toArray()
            );
            return true;
        } catch (RuntimeException $error) {
            wp_schedule_single_event(
                time() - 1,
                self::EVENT_HOOK
            );
            return false;
        }
    }
}