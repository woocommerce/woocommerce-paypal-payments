<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Processor;

use Exception;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;

class AuthorizedPaymentsProcessor
{
    public const SUCCESSFUL = 'SUCCESSFUL';
    public const ALREADY_CAPTURED = 'ALREADY_CAPTURED';
    public const FAILED = 'FAILED';
    public const INACCESSIBLE = 'INACCESSIBLE';
    public const NOT_FOUND = 'NOT_FOUND';
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
        try {
            $order = $this->payPalOrderFromWcOrder($wcOrder);
        } catch (Exception $exception) {
            if ($exception->getCode() === 404) {
                return self::NOT_FOUND;
            }
            return self::INACCESSIBLE;
        }

        $authorizations = $this->allAuthorizations($order);

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

    private function payPalOrderFromWcOrder(\WC_Order $wcOrder): Order
    {
        $orderId = $wcOrder->get_meta(WcGateway::ORDER_ID_META_KEY);
        return $this->orderEndpoint->order($orderId);
    }

    private function allAuthorizations(Order $order): array
    {
        $authorizations = [];
        foreach ($order->purchaseUnits() as $purchaseUnit) {
            foreach ($purchaseUnit->payments()->authorizations() as $authorization) {
                $authorizations[] = $authorization;
            }
        }

        return $authorizations;
    }

    private function areAuthorizationToCapture(Authorization ...$authorizations): bool
    {
        $alreadyCapturedAuthorizations = $this->authorizationsWithCapturedStatus(...$authorizations);

        return count($alreadyCapturedAuthorizations) !== count($authorizations);
    }

    private function captureAuthorizations(Authorization ...$authorizations)
    {
        $uncapturedAuthorizations = $this->authorizationsWithCreatedStatus(...$authorizations);

        foreach ($uncapturedAuthorizations as $authorization) {
            $this->paymentsEndpoint->capture($authorization->id());
        }
    }

    /**
     * @return Authorization[]
     */
    private function authorizationsWithCreatedStatus(Authorization ...$authorizations): array
    {
        return array_filter(
            $authorizations,
            static function (Authorization $authorization): bool {
                return $authorization->status()->is(AuthorizationStatus::CREATED);
            }
        );
    }

    /**
     * @return Authorization[]
     */
    private function authorizationsWithCapturedStatus(Authorization ...$authorizations): array
    {
        return array_filter(
            $authorizations,
            static function (Authorization $authorization): bool {
                return $authorization->status()->is(AuthorizationStatus::CAPTURED);
            }
        );
    }
}
