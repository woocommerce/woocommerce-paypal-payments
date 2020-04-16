<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Processor;

use Exception;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;

class AuthorizedPaymentsProcessor
{
    public const SUCCESSFUL = 'SUCCESSFUL';
    public const ALREADY_CAPTURED = 'ALREADY_CAPTURED';
    public const FAILED = 'FAILED';
    public const INACCESSIBLE = 'INACCESSIBLE';
    private $orderEndpoint;
    private $paymentsEndpoint;

    public function __construct(
        OrderEndpoint $orderEndpoint,
        PaymentsEndpoint $paymentsEndpoint
    ) {
        $this->orderEndpoint = $orderEndpoint;
        $this->paymentsEndpoint = $paymentsEndpoint;
    }

    public function process(\WC_Order $wcOrder): string
    {
        $orderId = $this->getPayPalOrderId($wcOrder);

        try {
            $order = $this->getCurrentOrderInfo($orderId);
        } catch (Exception $exception) {
            return self::INACCESSIBLE;
        }

        $authorizations = $this->getAllAuthorizations($order);

        if (!$this->areAuthorizationToCapture(...$authorizations)) {
            return self::ALREADY_CAPTURED;
        }

        try {
            $this->captureAuthorizations(...$authorizations);
        } catch (Exception $exception) {
            return self::FAILED;
        }

        return self::SUCCESSFUL;
    }

    protected function getPayPalOrderId(\WC_Order $wcOrder): string
    {
        return get_post_meta($wcOrder->get_id(), '_paypal_order_id', true);
    }

    protected function getCurrentOrderInfo(string $orderId): Order
    {
        return $this->orderEndpoint->order($orderId);
    }

    protected function getAllAuthorizations(Order $order): array
    {
        $authorizations = [];
        foreach ($order->purchaseUnits() as $purchaseUnit) {
            foreach ($purchaseUnit->payments()->authorizations() as $authorization) {
                $authorizations[] = $authorization;
            }
        }

        return $authorizations;
    }

    protected function areAuthorizationToCapture(Authorization ...$authorizations): bool
    {
        $alreadyCapturedAuthorizations = $this->authorizationsWithCapturedStatus(...$authorizations);

        return count($alreadyCapturedAuthorizations) !== count($authorizations);
    }

    protected function captureAuthorizations(Authorization ...$authorizations)
    {
        $uncapturedAuthorizations = $this->authorizationsWithCreatedStatus(...$authorizations);

        foreach ($uncapturedAuthorizations as $authorization) {
            $this->paymentsEndpoint->capture($authorization->id());
        }
    }

    /**
     * @return Authorization[]
     */
    protected function authorizationsWithCreatedStatus(Authorization ...$authorizations): array
    {
        return array_filter(
            $authorizations,
            function (Authorization $authorization) {
                return $authorization->status()->is(AuthorizationStatus::CREATED);
            }
        );
    }

    /**
     * @return Authorization[]
     */
    protected function authorizationsWithCapturedStatus(Authorization ...$authorizations): array
    {
        return array_filter(
            $authorizations,
            function (Authorization $authorization) {
                return $authorization->status()->is(AuthorizationStatus::CAPTURED);
            }
        );
    }
}
