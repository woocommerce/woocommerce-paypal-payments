<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentMethod;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentToken;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ApplicationContextFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\Helper\ErrorResponse;
use Inpsyde\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use Psr\Log\LoggerInterface;

//phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong

class OrderEndpoint
{
    use RequestTrait;

    private $host;
    private $bearer;
    private $orderFactory;
    private $patchCollectionFactory;
    private $intent;
    private $logger;
    private $applicationContextRepository;
    private $bnCode;
    private $payPalRequestIdRepository;

    public function __construct(
        string $host,
        Bearer $bearer,
        OrderFactory $orderFactory,
        PatchCollectionFactory $patchCollectionFactory,
        string $intent,
        LoggerInterface $logger,
        ApplicationContextRepository $applicationContextRepository,
        PayPalRequestIdRepository $payPalRequestIdRepository,
        string $bnCode = ''
    ) {

        $this->host = $host;
        $this->bearer = $bearer;
        $this->orderFactory = $orderFactory;
        $this->patchCollectionFactory = $patchCollectionFactory;
        $this->intent = $intent;
        $this->logger = $logger;
        $this->applicationContextRepository = $applicationContextRepository;
        $this->bnCode = $bnCode;
        $this->payPalRequestIdRepository = $payPalRequestIdRepository;
    }

    public function withBnCode(string $bnCode): OrderEndpoint
    {

        $this->bnCode = $bnCode;
        return $this;
    }

    /**
     * @param PurchaseUnit[] $items
     */
    public function createForPurchaseUnits(
        array $items,
        Payer $payer = null,
        PaymentToken $paymentToken = null,
        PaymentMethod $paymentMethod = null,
        string $paypalRequestId = ''
    ): Order {

        $containsPhysicalGoods = false;
        $items = array_filter(
            $items,
            //phpcs:ignore Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
            static function ($item) use (&$containsPhysicalGoods): bool {
                $isPurchaseUnit = is_a($item, PurchaseUnit::class);
                if ($isPurchaseUnit && $item->containsPhysicalGoodsItems()) {
                    $containsPhysicalGoods = true;
                }

                return $isPurchaseUnit;
            }
        );
        $shippingPreference = $containsPhysicalGoods
            ? ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE
            : ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING;
        $bearer = $this->bearer->bearer();
        $data = [
            'intent' => $this->intent,
            'purchase_units' => array_map(
                static function (PurchaseUnit $item): array {
                    return $item->toArray();
                },
                $items
            ),
            'application_context' => $this->applicationContextRepository
                ->currentContext($shippingPreference)->toArray(),
        ];
        if ($payer) {
            $data['payer'] = $payer->toArray();
        }
        if ($paymentToken) {
            $data['payment_source']['token'] = $paymentToken->toArray();
        }
        if ($paymentMethod) {
            $data['payment_method'] = $paymentMethod->toArray();
        }
        $url = trailingslashit($this->host) . 'v2/checkout/orders';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => json_encode($data),
        ];

        $paypalRequestId = $paypalRequestId ? $paypalRequestId : uniqid('ppcp-', true);
        $args['headers']['PayPal-Request-Id'] = $paypalRequestId;
        if ($this->bnCode) {
            $args['headers']['PayPal-Partner-Attribution-Id'] = $this->bnCode;
        }
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not create order.', 'woocommerce-paypal-commerce-gateway')
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
        $order = $this->orderFactory->fromPayPalResponse($json);
        $this->payPalRequestIdRepository->setForOrder($order, $paypalRequestId);
        return $order;
    }

    public function capture(Order $order): Order
    {
        if ($order->status()->is(OrderStatus::COMPLETED)) {
            return $order;
        }
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $order->id() . '/capture';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
                'PayPal-Request-Id' => $this->payPalRequestIdRepository->getForOrder($order),
            ],
        ];
        if ($this->bnCode) {
            $args['headers']['PayPal-Partner-Attribution-Id'] = $this->bnCode;
        }
        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not capture order.', 'woocommerce-paypal-commerce-gateway')
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
            // If the order has already been captured, we return the updated order.
            if (strpos($response['body'], ErrorResponse::ORDER_ALREADY_CAPTURED) !== false) {
                return $this->order($order->id());
            }
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
        $order = $this->orderFactory->fromPayPalResponse($json);
        return $order;
    }

    public function authorize(Order $order): Order
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $order->id() . '/authorize';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
                'PayPal-Request-Id' => $this->payPalRequestIdRepository->getForOrder($order),
            ],
        ];
        if ($this->bnCode) {
            $args['headers']['PayPal-Partner-Attribution-Id'] = $this->bnCode;
        }
        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __(
                    'Could not authorize order.',
                    'woocommerce-paypal-commerce-gateway'
                )
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
            if (strpos($response['body'], ErrorResponse::ORDER_ALREADY_AUTHORIZED) !== false) {
                return $this->order($order->id());
            }
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
        $order = $this->orderFactory->fromPayPalResponse($json);
        return $order;
    }

    public function order(string $id): Order
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $id;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => $this->payPalRequestIdRepository->getForOrderId($id),
            ],
        ];
        if ($this->bnCode) {
            $args['headers']['PayPal-Partner-Attribution-Id'] = $this->bnCode;
        }
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not retrieve order.', 'woocommerce-paypal-commerce-gateway')
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
        if ($statusCode === 404 || empty($response['body'])) {
            $error = new RuntimeException(
                __('Could not retrieve order.', 'woocommerce-paypal-commerce-gateway'),
                404
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
        if ($statusCode !== 200) {
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
        $order = $this->orderFactory->fromPayPalResponse($json);
        return $order;
    }

    public function patchOrderWith(Order $orderToUpdate, Order $orderToCompare): Order
    {
        $patches = $this->patchCollectionFactory->fromOrders($orderToUpdate, $orderToCompare);
        if (! count($patches->patches())) {
            return $orderToUpdate;
        }

        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $orderToUpdate->id();
        $args = [
            'method' => 'PATCH',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
                'PayPal-Request-Id' => $this->payPalRequestIdRepository->getForOrder(
                    $orderToUpdate
                ),
            ],
            'body' => json_encode($patches->toArray()),
        ];
        if ($this->bnCode) {
            $args['headers']['PayPal-Partner-Attribution-Id'] = $this->bnCode;
        }
        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not retrieve order.', 'woocommerce-paypal-commerce-gateway')
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
        if ($statusCode !== 204) {
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

        $newOrder = $this->order($orderToUpdate->id());
        return $newOrder;
    }
}
