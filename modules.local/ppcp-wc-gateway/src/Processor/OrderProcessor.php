<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Processor;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;

class OrderProcessor
{
    private $sessionHandler;
    private $cartRepository;
    private $orderEndpoint;
    private $paymentsEndpoint;
    private $orderFactory;

    private $lastError = '';

    public function __construct(
        SessionHandler $sessionHandler,
        CartRepository $cartRepository,
        OrderEndpoint $orderEndpoint,
        PaymentsEndpoint $paymentsEndpoint,
        OrderFactory $orderFactory
    ) {

        $this->sessionHandler = $sessionHandler;
        $this->cartRepository = $cartRepository;
        $this->orderEndpoint = $orderEndpoint;
        $this->paymentsEndpoint = $paymentsEndpoint;
        $this->orderFactory = $orderFactory;
    }

    public function process(\WC_Order $wcOrder, $woocommerce): bool
    {
        $order = $this->sessionHandler->order();
        $wcOrder->update_meta_data(WcGateway::ORDER_ID_META_KEY, $order->id());
        $wcOrder->update_meta_data(WcGateway::INTENT_META_KEY, $order->intent());

        $errorMessage = null;
        if (!$order || !$order->status()->is(OrderStatus::APPROVED)) {
            $errorMessage = __('The payment has not been approved yet.', 'woocommerce-paypal-gateway');
        }
        if ($errorMessage) {
            $this->lastError = sprintf(
            // translators %s is the message of the error.
                __('Payment error: %s', 'woocommerce-paypal-gateway'),
                $errorMessage
            );
            return false;
        }

        $order = $this->patchOrder($wcOrder, $order);
        if ($order->intent() === 'CAPTURE') {
            $order = $this->orderEndpoint->capture($order);
        }

        if ($order->intent() === 'AUTHORIZE') {
            $order = $this->orderEndpoint->authorize($order);
            $wcOrder->update_meta_data(WcGateway::CAPTURED_META_KEY, 'false');
        }

        $wcOrder->update_status('on-hold', __('Awaiting payment.', 'woocommerce-paypal-gateway'));
        if ($order->status()->is(OrderStatus::COMPLETED) && $order->intent() === 'CAPTURE') {
            $wcOrder->update_status('processing', __('Payment received.', 'woocommerce-paypal-gateway'));
        }
        $woocommerce->cart->empty_cart();
        $this->sessionHandler->destroySessionData();
        $this->lastError = '';
        return true;
    }

    public function lastError(): string
    {

        return $this->lastError;
    }

    private function patchOrder(\WC_Order $wcOrder, Order $order): Order
    {
        $updatedOrder = $this->orderFactory->fromWcOrder($wcOrder, $order);
        $order = $this->orderEndpoint->patchOrderWith($order, $updatedOrder);
        return $order;
    }
}
