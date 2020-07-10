<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use Inpsyde\PayPalCommerce\Webhooks\Handler\RequestHandler;
use Psr\Log\LoggerInterface;

class IncomingWebhookEndpoint
{

    public const NAMESPACE = 'paypal/v1';
    public const ROUTE = 'incoming';
    private $webhookEndpoint;
    private $webhookFactory;
    private $handlers;
    private $logger;
    private $verifyRequest;
    public function __construct(
        WebhookEndpoint $webhookEndpoint,
        WebhookFactory $webhookFactory,
        LoggerInterface $logger,
        bool $verifyRequest,
        RequestHandler ...$handlers
    ) {

        $this->webhookEndpoint = $webhookEndpoint;
        $this->webhookFactory = $webhookFactory;
        $this->handlers = $handlers;
        $this->logger = $logger;
        $this->verifyRequest = $verifyRequest;
    }

    public function register(): bool
    {
        return (bool) register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods' => [
                    'POST',
                ],
                'callback' => [
                    $this,
                    'handleRequest',
                ],
                'permission_callback' => [
                    $this,
                    'verifyRequest',
                ],

            ]
        );
    }

    public function verifyRequest(): bool
    {
        if ( ! $this->verifyRequest) {
            return true;
        }
        try {
            $data = (array) get_option(WebhookRegistrar::KEY, []);
            $webhook = $this->webhookFactory->fromArray($data);
            return $this->webhookEndpoint->verifyCurrentRequestForWebhook($webhook);
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {

        foreach ($this->handlers as $handler) {
            if ($handler->responsibleForRequest($request)) {
                $response = $handler->handleRequest($request);
                $this->logger->log(
                    'info',
                    sprintf(
                        // translators: %s is the event type
                        __('Webhook has been handled by %s', 'woocommerce-paypal-commerce-gateway'),
                        ($handler->eventTypes()) ? current($handler->eventTypes()) : ''
                    ),
                    [
                        'request' => $request,
                        'response' => $response,
                    ]
                );
                return $response;
            }
        }

        $response = ['success' => false];
        return rest_ensure_response($response);
    }

    public function url(): string
    {
        return rest_url(self::NAMESPACE . '/' . self::ROUTE);
    }

    public function handledEventTypes(): array
    {
        $eventTypes = [];
        foreach ($this->handlers as $handler) {
            $eventTypes = array_merge($eventTypes, $handler->eventTypes());
        }
        return array_unique($eventTypes);
    }
}
