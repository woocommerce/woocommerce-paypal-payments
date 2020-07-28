<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Subscription;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentToken;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use Inpsyde\PayPalCommerce\Subscription\Repository\PaymentTokenRepository;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Psr\Log\LoggerInterface;

class RenewalHandler
{

    private $logger;
    private $repository;
    private $orderEndpoint;
    private $purchaseUnitFactory;
    private $payerFactory;
    public function __construct(
        LoggerInterface $logger,
        PaymentTokenRepository $repository,
        OrderEndpoint $orderEndpoint,
        PurchaseUnitFactory $purchaseUnitFactory,
        PayerFactory $payerFactory
    ) {

        $this->logger = $logger;
        $this->repository = $repository;
        $this->orderEndpoint = $orderEndpoint;
        $this->purchaseUnitFactory = $purchaseUnitFactory;
        $this->payerFactory = $payerFactory;
    }

    public function renew(\WC_Order $wcOrder)
    {

        $this->logger->log(
            'info',
            sprintf(
                // translators: %d is the id of the order
                __('Start moneytransfer for order %d', 'woocommerce-paypal-commerce-gateway'),
                (int) $wcOrder->get_id()
            ),
            [
                'order' => $wcOrder,
            ]
        );

        try {
            $this->processOrder($wcOrder);
        } catch (\Exception $error) {
            $this->logger->log(
                'error',
                sprintf(
                    // translators: %1$d is the order number, %2$s the error message
                    __(
                        'An error occured while trying to renew the subscription for order %1$d: %2$s',
                        'woocommerce-paypal-commerce-gateway'
                    ),
                    (int) $wcOrder->get_id(),
                    $error->getMessage()
                ),
                [
                    'order' => $wcOrder,
                ]
            );
            \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($wcOrder);
            return;
        }
        $this->logger->log(
            'info',
            sprintf(
                // translators: %d is the order number
                __('Moneytransfer for order %d is completed.', 'woocommerce-paypal-commerce-gateway'),
                (int) $wcOrder->get_id()
            ),
            [
                'order' => $wcOrder,
            ]
        );
    }

    private function processOrder(\WC_Order $wcOrder)
    {

        $userId = (int)$wcOrder->get_customer_id();
        $customer = new \WC_Customer($userId);
        $token = $this->getTokenForCustomer($customer, $wcOrder);
        if (! $token) {
            return;
        }
        $purchaseUnits = $this->purchaseUnitFactory->fromWcOrder($wcOrder);
        $payer = $this->payerFactory->fromCustomer($customer);
        $order = $this->orderEndpoint->createForPurchaseUnits(
            [$purchaseUnits],
            $payer,
            $token,
            (string) $wcOrder->get_id()
        );
        $this->captureOrder($order, $wcOrder);
    }

    private function getTokenForCustomer(\WC_Customer $customer, \WC_Order $wcOrder): ?PaymentToken
    {

        $token = $this->repository->forUserId((int) $customer->get_id());
        if (!$token) {
            $this->logger->log(
                'error',
                sprintf(
                    // translators: %d is the customer id
                    __('No payment token found for customer %d', 'woocommerce-paypal-commerce-gateway'),
                    (int) $customer->get_id()
                ),
                [
                    'customer' => $customer,
                    'order' => $wcOrder,
                ]
            );
            \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($wcOrder);
        }
        return $token;
    }

    private function captureOrder(Order $order, \WC_Order $wcOrder)
    {

        if ($order->intent() === 'CAPTURE' && $order->status()->is(OrderStatus::COMPLETED)) {
            $wcOrder->update_status(
                'processing',
                __('Payment received.', 'woocommerce-paypal-commerce-gateway')
            );
            \WC_Subscriptions_Manager::process_subscription_payments_on_order($wcOrder);
        }

        if ($order->intent() === 'AUTHORIZE') {
            $this->orderEndpoint->authorize($order);
            $wcOrder->update_meta_data(WcGateway::CAPTURED_META_KEY, 'false');
            \WC_Subscriptions_Manager::process_subscription_payments_on_order($wcOrder);
        }
    }
}
