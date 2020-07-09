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
    public function __construct(
        WebhookEndpoint $webhookEndpoint,
        WebhookFactory $webhookFactory,
        LoggerInterface $logger,
        RequestHandler ...$handlers
    ) {

        $this->webhookEndpoint = $webhookEndpoint;
        $this->webhookFactory = $webhookFactory;
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
                'permission_callback' => [
                    $this,
                    'verifyRequest',
                ],

            ]
        );
    }

    public function verifyRequest() : bool {
        try {
            $data = (array) get_option(WebhookRegistrar::KEY, []);
            $webhook = $this->webhookFactory->fromArray($data);
            return $this->webhookEndpoint->verifyCurrentRequestForWebhook($webhook);
        } catch (RuntimeException $exception) {
            return false;
        }
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

        $response = ['success' => false];
        return rest_ensure_response($response);
    }

    public function url() : string {
        return str_replace('http', 'https', rest_url(self::NAMESPACE . '/' . self::ROUTE));
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
