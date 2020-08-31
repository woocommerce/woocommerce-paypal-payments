<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Exception;

class PayPalApiException extends RuntimeException
{

    private $response;
    private $statusCode;
    public function __construct(\stdClass $response = null, int $statusCode = 0)
    {
        if (is_null($response)) {
            $response = new \stdClass();
        }
        if (! isset($response->message)) {
            $response->message = __(
                'Unknown error while connecting to PayPal.',
                'woocommerce-paypal-commerce-gateway'
            );
        }
        if (! isset($response->name)) {
            $response->name = __('Error', 'woocommerce-paypal-commerce-gateway');
        }
        if (! isset($response->details)) {
            $response->details = [];
        }
        if (! isset($response->links) || ! is_array($response->links)) {
            $response->links = [];
        }

        $this->response = $response;
        $this->statusCode = $statusCode;
        $message = $response->message;
        if ($response->name) {
            $message = '[' . $response->name . '] ' . $message;
        }
        foreach ($response->links as $link) {
            if (isset($link->rel) && $link->rel === 'information_link') {
                $message .= ' ' . $link->href;
            }
        }
        parent::__construct($message, $statusCode);
    }

    public function name(): string
    {
        return $this->response->name;
    }

    public function details(): array
    {
        return $this->response->details;
    }

    public function hasDetail(string $issue): bool
    {
        foreach ($this->details() as $detail) {
            if (isset($detail->issue) && $detail->issue === $issue) {
                return true;
            }
        }
        return false;
    }
}
