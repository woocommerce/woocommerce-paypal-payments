<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Webhook;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class WebhookFactory
{

    public function forUrlAndEvents(string $url, array $eventTypes): Webhook
    {
        $eventTypes = array_map(
            static function (string $type): array {
                return ["name" => $type];
            },
            $eventTypes
        );
        return new Webhook(
            $url,
            $eventTypes
        );
    }

    public function fromArray(array $data): Webhook
    {
        return $this->fromPayPalResponse((object) $data);
    }

    public function fromPayPalResponse(\stdClass $data): Webhook
    {
        if (! isset($data->id)) {
            throw new RuntimeException(
                __("No id for webhook given.", "woocommerce-paypal-commerce-gateway")
            );
        }
        if (! isset($data->url)) {
            throw new RuntimeException(
                __("No URL for webhook given.", "woocommerce-paypal-commerce-gateway")
            );
        }
        if (! isset($data->event_types)) {
            throw new RuntimeException(
                __("No event types for webhook given.", "woocommerce-paypal-commerce-gateway")
            );
        }

        return new Webhook(
            (string) $data->url,
            (array) $data->event_types,
            (string) $data->id
        );
    }
}
