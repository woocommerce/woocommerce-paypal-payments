<?php

/**
 * Stores the info about webhook events.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
/**
 * Class WebhookEventStorage
 */
class WebhookEventStorage
{
    /**
     * The WP option key.
     *
     * @var string
     */
    private $key;
    /**
     * WebhookInfoStorage constructor.
     *
     * @param string $key The WP option key.
     */
    public function __construct(string $key)
    {
        $this->key = $key;
    }
    /**
     * Saves the info about webhook event.
     *
     * @param WebhookEvent $webhook_event The webhook event to save.
     */
    public function save(WebhookEvent $webhook_event): void
    {
        $data = array('id' => $webhook_event->id(), 'received_time' => time());
        update_option($this->key, $data);
    }
    /**
     * Returns the stored data or null.
     */
    public function get_data(): ?array
    {
        $data = get_option($this->key);
        if (!$data || !is_array($data)) {
            return null;
        }
        return $data;
    }
    /**
     * Checks if there is any stored data.
     */
    public function is_empty(): bool
    {
        $data = get_option($this->key);
        return !$data || !is_array($data);
    }
    /**
     * Removes the stored data.
     */
    public function clear(): void
    {
        delete_option($this->key);
    }
}
