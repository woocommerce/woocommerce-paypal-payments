<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Checkout;

use Inpsyde\PayPalCommerce\Session\SessionHandler;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;

class DisableGateways
{

    private $sessionHandler;
    public function __construct(SessionHandler $sessionHandler)
    {
        $this->sessionHandler = $sessionHandler;
    }

    public function handler(array $methods): array
    {
        if (! $this->needsToDisableGateways()) {
            return $methods;
        }

        return [WcGateway::ID => $methods[WcGateway::ID]];
    }

    private function needsToDisableGateways(): bool
    {
        return $this->sessionHandler->order() !== null;
    }
}
