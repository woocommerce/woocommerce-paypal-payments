<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;

class OrderFactory
{

    private $purchaseUnitFactory;
    private $payerFactory;
    private $applicationContextRepository;
    private $applicationContextFactory;
    private $paymentSourceFactory;
    public function __construct(
        PurchaseUnitFactory $purchaseUnitFactory,
        PayerFactory $payerFactory,
        ApplicationContextRepository $applicationContextRepository,
        ApplicationContextFactory $applicationContextFactory,
        PaymentSourceFactory $paymentSourceFactory
    ) {

        $this->purchaseUnitFactory = $purchaseUnitFactory;
        $this->payerFactory = $payerFactory;
        $this->applicationContextRepository = $applicationContextRepository;
        $this->applicationContextFactory = $applicationContextFactory;
        $this->paymentSourceFactory = $paymentSourceFactory;
    }

    public function fromWcOrder(\WC_Order $wcOrder, Order $order): Order
    {
        $purchaseUnits = [$this->purchaseUnitFactory->fromWcOrder($wcOrder)];

        return new Order(
            $order->id(),
            $purchaseUnits,
            $order->status(),
            $order->applicationContext(),
            $order->paymentSource(),
            $order->payer(),
            $order->intent(),
            $order->createTime(),
            $order->updateTime()
        );
    }

    // phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function fromPayPalResponse(\stdClass $orderData): Order
    {
        if (! isset($orderData->id)) {
            throw new RuntimeException(
                __('Order does not contain an id.', 'woocommerce-paypal-commerce-gateway')
            );
        }
        if (! isset($orderData->purchase_units) || !is_array($orderData->purchase_units)) {
            throw new RuntimeException(
                __('Order does not contain items.', 'woocommerce-paypal-commerce-gateway')
            );
        }
        if (! isset($orderData->status)) {
            throw new RuntimeException(
                __('Order does not contain status.', 'woocommerce-paypal-commerce-gateway')
            );
        }
        if (! isset($orderData->intent)) {
            throw new RuntimeException(
                __('Order does not contain intent.', 'woocommerce-paypal-commerce-gateway')
            );
        }

        $purchaseUnits = array_map(
            function (\stdClass $data): PurchaseUnit {
                return $this->purchaseUnitFactory->fromPayPalResponse($data);
            },
            $orderData->purchase_units
        );

        $createTime = (isset($orderData->create_time)) ?
            \DateTime::createFromFormat(\DateTime::ISO8601, $orderData->create_time)
            : null;
        $updateTime = (isset($orderData->update_time)) ?
            \DateTime::createFromFormat(\DateTime::ISO8601, $orderData->update_time)
            : null;
        $payer = (isset($orderData->payer)) ?
            $this->payerFactory->fromPayPalResponse($orderData->payer)
            : null;
        $applicationContext = (isset($orderData->application_context)) ?
            $this->applicationContextFactory->fromPayPalResponse($orderData->application_context)
            : null;
        $paymentSource = (isset($orderData->payment_source)) ?
            $this->paymentSourceFactory->fromPayPalResponse($orderData->payment_source) :
            null;

        return new Order(
            $orderData->id,
            $purchaseUnits,
            new OrderStatus($orderData->status),
            $applicationContext,
            $paymentSource,
            $payer,
            $orderData->intent,
            $createTime,
            $updateTime
        );
    }
    // phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong
}
