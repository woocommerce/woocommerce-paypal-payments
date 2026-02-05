<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Status;

use Exception;
use UnexpectedValueException;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
/**
 * Handles the webhook simulation.
 */
class WebhookSimulation
{
    public const STATE_WAITING = 'waiting';
    public const STATE_RECEIVED = 'received';
    public const OPTION_ID = 'ppcp-webhook-simulation';
    private WebhookEndpoint $webhook_endpoint;
    // @phpstan-ignore property.onlyWritten
    /**
     * Our registered webhook.
     */
    private ?Webhook $webhook;
    // @phpstan-ignore property.onlyWritten
    /**
     * The event type that will be simulated, such as CHECKOUT.ORDER.APPROVED.
     */
    private string $event_type;
    // @phpstan-ignore property.onlyWritten
    /**
     * The event resource version, such as 2.0.
     */
    private ?string $resource_version;
    // @phpstan-ignore property.onlyWritten
    /**
     * WebhookSimulation constructor.
     *
     * @param WebhookEndpoint $webhook_endpoint The webhooks endpoint.
     * @param Webhook|null    $webhook Our registered webhook.
     * @param string          $event_type The event type that will be simulated, such as CHECKOUT.ORDER.APPROVED.
     * @param string|null     $resource_version The event resource version, such as 2.0.
     */
    public function __construct(WebhookEndpoint $webhook_endpoint, ?Webhook $webhook, string $event_type, ?string $resource_version)
    {
        $this->webhook_endpoint = $webhook_endpoint;
        $this->webhook = $webhook;
        $this->event_type = $event_type;
        $this->resource_version = $resource_version;
    }
    /**
     * Starts the simulation by sending request to PayPal and saving the simulation data with STATE_WAITING.
     *
     * @throws Exception If failed to start simulation.
     */
    public function start(?Webhook $webhook = null): void
    {
        // Disabled for 3.3.1 release.
        return;
        /** @phpstan-ignore deadCode.unreachable */
        if (!$webhook) {
            $webhook = $this->webhook;
        }
        if (!$webhook) {
            throw new Exception('Webhooks not registered');
        }
        $event = $this->webhook_endpoint->simulate($webhook, $this->event_type, $this->resource_version);
        $this->save(array('id' => $event->id(), 'state' => self::STATE_WAITING));
    }
    /**
     * Returns true if the given event matches the expected simulation event.
     *
     * @param WebhookEvent $event The webhook event.
     * @return bool
     */
    public function is_simulation_event(WebhookEvent $event): bool
    {
        try {
            $data = $this->load();
            return isset($data['id']) && $event->id() === $data['id'];
        } catch (Exception $exception) {
            return \false;
        }
    }
    /**
     * Sets the simulation state to STATE_RECEIVED if the given event matches the expected simulation event.
     *
     * @param WebhookEvent $event The webhook event.
     *
     * @return bool
     * @throws Exception If failed to save new state.
     */
    public function receive(WebhookEvent $event): bool
    {
        if (!$this->is_simulation_event($event)) {
            return \false;
        }
        $this->set_state(self::STATE_RECEIVED);
        return \true;
    }
    /**
     * Returns the current simulation state, one of the STATE_ constants.
     *
     * @return string
     * @throws Exception If failed to load state.
     */
    public function get_state(): string
    {
        $data = $this->load();
        return $data['state'];
    }
    /**
     * Saves the new state.
     *
     * @param string $state One of the STATE_ constants.
     *
     * @throws Exception If failed to load state.
     */
    private function set_state(string $state): void
    {
        $data = $this->load();
        $data['state'] = $state;
        $this->save($data);
    }
    /**
     * Saves the simulation data.
     *
     * @param array $data The simulation data.
     */
    private function save(array $data): void
    {
        update_option(self::OPTION_ID, $data);
    }
    /**
     * Returns the current simulation data.
     *
     * @return array
     * @throws UnexpectedValueException If failed to load.
     */
    private function load(): array
    {
        $data = get_option(self::OPTION_ID);
        if (!$data) {
            throw new UnexpectedValueException('Webhook simulation data not found.');
        }
        return $data;
    }
}
