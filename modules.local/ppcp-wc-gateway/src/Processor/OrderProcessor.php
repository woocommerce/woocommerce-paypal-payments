<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Processor;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\Button\Helper\ThreeDSecure;
use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class OrderProcessor
{
    private $sessionHandler;
    private $cartRepository;
    private $orderEndpoint;
    private $paymentsEndpoint;
    private $orderFactory;
    private $threedSecure;

    private $lastError = '';

    public function __construct(
        SessionHandler $sessionHandler,
        CartRepository $cartRepository,
        OrderEndpoint $orderEndpoint,
        PaymentsEndpoint $paymentsEndpoint,
        OrderFactory $orderFactory,
        ThreeDSecure $threedSecure
    ) {

        $this->sessionHandler = $sessionHandler;
        $this->cartRepository = $cartRepository;
        $this->orderEndpoint = $orderEndpoint;
        $this->paymentsEndpoint = $paymentsEndpoint;
        $this->orderFactory = $orderFactory;
        $this->threedSecure = $threedSecure;
    }

    public function process(\WC_Order $wcOrder, \WooCommerce $woocommerce): bool
    {
        $order = $this->sessionHandler->order();
        $wcOrder->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $order->id());
        $wcOrder->update_meta_data(PayPalGateway::INTENT_META_KEY, $order->intent());

        $errorMessage = null;
        if (!$order || ! $this->orderIsApproved($order)) {
            $errorMessage = __(
                'The payment has not been approved yet.',
                'woocommerce-paypal-commerce-gateway'
            );
        }
        if ($errorMessage) {
            $this->lastError = sprintf(
                // translators: %s is the message of the error.
                __('Payment error: %s', 'woocommerce-paypal-commerce-gateway'),
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
            $wcOrder->update_meta_data(PayPalGateway::CAPTURED_META_KEY, 'false');
        }

        $wcOrder->update_status(
            'on-hold',
            __('Awaiting payment.', 'woocommerce-paypal-commerce-gateway')
        );
        if ($order->status()->is(OrderStatus::COMPLETED) && $order->intent() === 'CAPTURE') {
            $wcOrder->update_status(
                'processing',
                __('Payment received.', 'woocommerce-paypal-commerce-gateway')
            );
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

    public function patchOrder(\WC_Order $wcOrder, Order $order): Order
    {
        $updatedOrder = $this->orderFactory->fromWcOrder($wcOrder, $order);
        $order = $this->orderEndpoint->patchOrderWith($order, $updatedOrder);
        return $order;
    }

    private function orderIsApproved(Order $order): bool
    {

        if ($order->status()->is(OrderStatus::APPROVED)) {
            return true;
        }

        if (! $order->paymentSource() || ! $order->paymentSource()->card()) {
            return false;
        }

        $isApproved = in_array(
            $this->threedSecure->proceedWithOrder($order),
            [
                ThreeDSecure::NO_DECISION,
                ThreeDSecure::PROCCEED,
            ],
            true
        );
        return $isApproved;
    }
}
