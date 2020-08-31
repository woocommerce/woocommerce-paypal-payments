<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class Webhook
{

    private $id;
    private $url;
    private $eventTypes;
    public function __construct(string $url, array $eventTypes, string $id = '')
    {
        $this->url = $url;
        $this->eventTypes = $eventTypes;
        $this->id = $id;
    }

    public function id(): string
    {

        return $this->id;
    }

    public function url(): string
    {

        return $this->url;
    }

    public function eventTypes(): array
    {

        return $this->eventTypes;
    }

    public function toArray(): array
    {

        $data = [
            'url' => $this->url(),
            'event_types' => $this->eventTypes(),
        ];
        if ($this->id()) {
            $data['id'] = $this->id();
        }
        return $data;
    }
}
