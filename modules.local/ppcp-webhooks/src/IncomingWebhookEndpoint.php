<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;


use Inpsyde\PayPalCommerce\Webhooks\Handler\RequestHandler;

class IncomingWebhookEndpoint
{

    public const NAMESPACE = 'paypal/v1/';
    public const ROUTE = 'incoming';
    private $handlers;
    public function __construct(RequestHandler ...$handlers)
    {
        $this->handlers = $handlers;
    }

    public function register() : bool
    {
        return (bool) register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods' => [
                    'GET',
                ],
                'callback' => [
                    $this,
                    'handleRequest',
                ],
            ]
        );
    }

    public function handleRequest(\WP_REST_Request $request) : \WP_REST_Response {

        foreach ($this->handlers as $handler) {
            if ($handler->responsibleForRequest($request)) {
                return $handler->handleRequest($request);
            }
        }
    }

    public function url() : string {
        return rest_url(self::NAMESPACE . self::ROUTE );
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
