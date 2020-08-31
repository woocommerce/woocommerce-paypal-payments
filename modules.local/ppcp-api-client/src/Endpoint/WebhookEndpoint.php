<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Webhook;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use Psr\Log\LoggerInterface;

class WebhookEndpoint
{
    use RequestTrait;

    private $host;
    private $bearer;
    private $webhookFactory;
    private $logger;
    public function __construct(
        string $host,
        Bearer $bearer,
        WebhookFactory $webhookFactory,
        LoggerInterface $logger
    ) {

        $this->host = $host;
        $this->bearer = $bearer;
        $this->webhookFactory = $webhookFactory;
        $this->logger = $logger;
    }

    public function create(Webhook $hook): Webhook
    {
        /**
         * An hook, which has an ID has already been created.
         */
        if ($hook->id()) {
            return $hook;
        }
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v1/notifications/webhooks';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($hook->toArray()),
        ];
        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Not able to create a webhook.', 'woocommerce-paypal-commerce-gateway')
            );
            $this->logger->log(
                'warning',
                $error->getMessage(),
                [
                    'args' => $args,
                    'response' => $response,
                ]
            );
            throw $error;
        }

        $json = json_decode($response['body']);
        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode !== 201) {
            $error = new PayPalApiException(
                $json,
                $statusCode
            );
            $this->logger->log(
                'warning',
                $error->getMessage(),
                [
                    'args' => $args,
                    'response' => $response,
                ]
            );
            throw $error;
        }

        $hook = $this->webhookFactory->fromPayPalResponse($json);
        return $hook;
    }

    public function delete(Webhook $hook): bool
    {
        if (! $hook->id()) {
            return false;
        }

        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v1/notifications/webhooks/' . $hook->id();
        $args = [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
            ],
        ];
        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Not able to delete the webhook.', 'woocommerce-paypal-commerce-gateway')
            );
            $this->logger->log(
                'warning',
                $error->getMessage(),
                [
                    'args' => $args,
                    'response' => $response,
                ]
            );
            throw $error;
        }
        return wp_remote_retrieve_response_code($response) === 204;
    }

    public function verifyEvent(
        string $authAlgo,
        string $certUrl,
        string $transmissionId,
        string $transmissionSig,
        string $transmissionTime,
        string $webhookId,
        \stdClass $webhookEvent
    ): bool {

        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v1/notifications/verify-webhook-signature';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(
                [
                    'transmission_id' => $transmissionId,
                    'transmission_time' => $transmissionTime,
                    'cert_url' => $certUrl,
                    'auth_algo' => $authAlgo,
                    'transmission_sig' => $transmissionSig,
                    'webhook_id' => $webhookId,
                    'webhook_event' => $webhookEvent,
                ]
            ),
        ];
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Not able to verify webhook event.', 'woocommerce-paypal-commerce-gateway')
            );
            $this->logger->log(
                'warning',
                $error->getMessage(),
                ['args' => $args, 'response' => $response]
            );
            throw $error;
        }
        $json = json_decode($response['body']);
        return isset($json->verification_status) && $json->verification_status === "SUCCESS";
    }

    public function verifyCurrentRequestForWebhook(Webhook $webhook): bool
    {

        if (! $webhook->id()) {
            $error = new RuntimeException(
                __('Not a valid webhook to verify.', 'woocommerce-paypal-commerce-gateway')
            );
            $this->logger->log('warning', $error->getMessage(), ['webhook' => $webhook]);
            throw $error;
        }

        $expectedHeaders = [
            'PAYPAL-AUTH-ALGO' => '',
            'PAYPAL-CERT-URL' => '',
            'PAYPAL-TRANSMISSION-ID' => '',
            'PAYPAL-TRANSMISSION-SIG' => '',
            'PAYPAL-TRANSMISSION-TIME' => '',
        ];
        $headers = getallheaders();
        foreach ($headers as $key => $header) {
            $key = strtoupper($key);
            if (isset($expectedHeaders[$key])) {
                $expectedHeaders[$key] = $header;
            }
        };

        foreach ($expectedHeaders as $key => $value) {
            if (! empty($value)) {
                continue;
            }

            $error = new RuntimeException(
                sprintf(
                    // translators: %s is the headers key.
                    __(
                        'Not a valid webhook event. Header %s is missing',
                        'woocommerce-paypal-commerce-gateway'
                    ),
                    $key
                )
            );
            $this->logger->log('warning', $error->getMessage(), ['webhook' => $webhook]);
            throw $error;
        }

        $requestBody = json_decode(file_get_contents("php://input"));
        return $this->verifyEvent(
            $expectedHeaders['PAYPAL-AUTH-ALGO'],
            $expectedHeaders['PAYPAL-CERT-URL'],
            $expectedHeaders['PAYPAL-TRANSMISSION-ID'],
            $expectedHeaders['PAYPAL-TRANSMISSION-SIG'],
            $expectedHeaders['PAYPAL-TRANSMISSION-TIME'],
            $webhook->id(),
            $requestBody ? $requestBody : new \stdClass()
        );
    }
}
