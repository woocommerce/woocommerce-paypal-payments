<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;


use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class OrderFactory
{

    private $purchaseUnitFactory;
    private $payerFactory;
    public function __construct(
        PurchaseUnitFactory $purchaseUnitFactory,
        PayerFactory $payerFactory
    ) {
        $this->purchaseUnitFactory = $purchaseUnitFactory;
        $this->payerFactory = $payerFactory;
    }

    public function fromWcOrder(\WC_Order $wcOrder, Order $order) : Order {

        $purchaseUnits = [$this->purchaseUnitFactory->fromWcOrder($wcOrder)];

        return new Order(
            $order->id(),
            $order->createTime(),
            $purchaseUnits,
            $order->status(),
            $order->payer(),
            $order->intent(),
            $order->updateTime()
        );
    }

    public function fromPayPalResponse(\stdClass $orderData) : Order
    {
        if (! isset($orderData->id)) {
            throw new RuntimeException(__('Order does not contain an id.', 'woocommerce-paypal-commerce-gateway'));
        }
        if (! isset($orderData->create_time)) {
            throw new RuntimeException(__('Order does not contain a create time.', 'woocommerce-paypal-commerce-gateway'));
        }
        if (! isset($orderData->purchase_units) || !is_array($orderData->purchase_units)) {
            throw new RuntimeException(__('Order does not contain items.', 'woocommerce-paypal-commerce-gateway'));
        }
        if (! isset($orderData->status)) {
            throw new RuntimeException(__('Order does not contain status.', 'woocommerce-paypal-commerce-gateway'));
        }
        if (! isset($orderData->intent)) {
            throw new RuntimeException(__('Order does not contain intent.', 'woocommerce-paypal-commerce-gateway'));
        }

        $purchaseUnits = array_map(
            function($data) : PurchaseUnit {
                return $this->purchaseUnitFactory->fromPayPalResponse($data);
            },
            $orderData->purchase_units
        );

        $updateTime = (isset($orderData->update_time)) ? \DateTime::createFromFormat(\DateTime::ISO8601, $orderData->update_time) : null;
        $payer = (isset($orderData->payer)) ? $this->payerFactory->fromPayPalResponse($orderData->payer) : null;

        return new Order(
            $orderData->id,
            \DateTime::createFromFormat(\DateTime::ISO8601, $orderData->create_time),
            $purchaseUnits,
            new OrderStatus($orderData->status),
            $payer,
            $orderData->intent,
            $updateTime
        );
    }
}