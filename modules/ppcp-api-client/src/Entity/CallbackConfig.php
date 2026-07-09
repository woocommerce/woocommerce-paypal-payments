<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * The config for experience_context.order_update_callback_config.
 */
class CallbackConfig
{
    public const EVENT_SHIPPING_ADDRESS = 'SHIPPING_ADDRESS';
    public const EVENT_SHIPPING_OPTIONS = 'SHIPPING_OPTIONS';
    /**
     * The events.
     *
     * @var string[]
     */
    private array $events;
    /**
     * The URL that will be called when the events occur.
     */
    private string $url;
    /**
     * @param string[] $events The events.
     * @param string   $url The URL that will be called when the events occur.
     */
    public function __construct(array $events, string $url)
    {
        $this->events = $events;
        $this->url = $url;
    }
    /**
     * Returns the object as array.
     */
    public function to_array(): array
    {
        return array('callback_events' => $this->events, 'callback_url' => $this->url);
    }
}
