<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;


use Inpsyde\PayPalCommerce\Webhooks\Handler\RequestHandler;
use Psr\Log\LoggerInterface;

class IncomingWebhookEndpoint
{

    public const NAMESPACE = 'paypal/v1';
    public const ROUTE = 'incoming';
    private $handlers;
    private $logger;
    public function __construct(LoggerInterface $logger, RequestHandler ...$handlers)
    {
        $this->handlers = $handlers;
        $this->logger = $logger;
    }

    public function register() : bool
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
            ]
        );
    }

    public function handleRequest(\WP_REST_Request $request) : \WP_REST_Response {

        /**
         * ToDo: Ensure Request is valid
         */
        foreach ($this->handlers as $handler) {
            if ($handler->responsibleForRequest($request)) {
                $response = $handler->handleRequest($request);
                $this->logger->log(
                    'info',
                    sprintf(
                        __('Webhook has been handled by %s', 'woocommerce-paypal-commerce-gateway'),
                        $handler->eventType()
                    ),
                    [
                        'request' => $request,
                        'response' => $response,
                    ]
                );
                return $response;
            }
        }
    }

    public function url() : string {
        return rest_url(self::NAMESPACE . '/' . self::ROUTE);
    }

    public function handledEventTypes() : array {
        return array_map(
            function(RequestHandler $handler) : string {
                return $handler->eventType();
            },
            $this->handlers
        );
    }
}
