<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
/**
 * The WebhookRegistrar registers and unregisters webhooks with PayPal.
 */
class WebhookRegistrar
{
    const EVENT_HOOK = 'ppcp-register-event';
    const KEY = 'ppcp-webhook';
    private WebhookFactory $webhook_factory;
    private WebhookEndpoint $endpoint;
    private \WooCommerce\PayPalCommerce\Webhooks\IncomingWebhookEndpoint $incoming_webhook_endpoint;
    private \WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage $last_webhook_event_storage;
    private WebhookSimulation $webhook_simulation;
    private \WooCommerce\PayPalCommerce\Webhooks\WebhookOrchestrator $webhook_orchestrator;
    private LoggerInterface $logger;
    public function __construct(WebhookFactory $webhook_factory, WebhookEndpoint $endpoint, \WooCommerce\PayPalCommerce\Webhooks\IncomingWebhookEndpoint $incoming_webhook_endpoint, \WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage $last_webhook_event_storage, WebhookSimulation $webhook_simulation, \WooCommerce\PayPalCommerce\Webhooks\WebhookOrchestrator $webhook_orchestrator, LoggerInterface $logger)
    {
        $this->webhook_factory = $webhook_factory;
        $this->endpoint = $endpoint;
        $this->incoming_webhook_endpoint = $incoming_webhook_endpoint;
        $this->last_webhook_event_storage = $last_webhook_event_storage;
        $this->webhook_simulation = $webhook_simulation;
        $this->webhook_orchestrator = $webhook_orchestrator;
        $this->logger = $logger;
    }
    /**
     * Register Webhooks with PayPal.
     *
     * @return bool
     */
    public function register(): bool
    {
        $result = $this->webhook_orchestrator->with_lock('register', fn() => $this->do_register());
        // If locked (null), treat as failure.
        return $result ?? \false;
    }
    /**
     * Unregister webhooks with PayPal.
     */
    public function unregister(): void
    {
        $this->webhook_orchestrator->with_lock('unregister', fn() => $this->do_unregister());
    }
    /**
     * Internal registration logic.
     *
     * @return bool
     */
    private function do_register(): bool
    {
        $this->do_unregister();
        $webhook = $this->webhook_factory->for_url_and_events($this->incoming_webhook_endpoint->url(), $this->incoming_webhook_endpoint->handled_event_types());
        try {
            $created = $this->endpoint->create($webhook);
            if (empty($created->id())) {
                return \false;
            }
            update_option(self::KEY, $created->to_array());
            $this->last_webhook_event_storage->clear();
            // Check whether webhooks are arriving (e.g. for the Status page).
            $this->webhook_simulation->start($created);
            $this->logger->info('Webhooks subscribed.');
            return \true;
        } catch (RuntimeException $error) {
            $this->logger->error('Failed to subscribe webhooks: ' . $error->getMessage());
            return \false;
        }
    }
    /**
     * Internal unregister logic.
     */
    private function do_unregister(): void
    {
        try {
            $webhooks = $this->endpoint->list();
            foreach ($webhooks as $webhook) {
                try {
                    $this->endpoint->delete($webhook);
                } catch (RuntimeException $deletion_error) {
                    $this->logger->error("Failed to delete webhook {$webhook->id()}: {$deletion_error->getMessage()}");
                }
            }
        } catch (RuntimeException $error) {
            $this->logger->error('Failed to delete webhooks: ' . $error->getMessage());
        }
        delete_option(self::KEY);
        $this->last_webhook_event_storage->clear();
        $this->logger->info('Webhooks deleted.');
    }
}
